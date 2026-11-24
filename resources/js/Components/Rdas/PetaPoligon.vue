<script setup lang="ts">
/*
  Peta petak tanam Rehab DAS — poligon digambar dari koordinat sendiri,
  tanpa ubin peta dari internet (jaringan tambang sering tertutup).

  Proyeksi ekuirektangular dengan koreksi cos(lintang) cukup untuk skala
  blok tanam (beberapa kilometer). Warna petak mengikuti fase blok atau
  persen tumbuh Petak Ukur terakhir. Gulir untuk memperbesar, seret untuk
  menggeser, klik petak untuk membuka bloknya.
*/
import { computed, ref } from 'vue';
import { n } from '../../energi';
import { fase, warnaTumbuh, FASE } from '../../rdas';

type Fitur = { id: number; blok_id: number; kode: string; blok: string; nama: string; ppkh?: string | null; luas: number; fase: string;
  tumbuh: number | null; sehat_ha?: number | null; poligon: [number, number][] | null; lat: number | null; lng: number | null; das?: string; fungsi?: string };

const props = withDefaults(defineProps<{ fitur: Fitur[]; mode?: 'fase' | 'tumbuh'; tinggi?: number; label?: boolean }>(), { mode: 'tumbuh', tinggi: 420, label: true });
const emit = defineEmits<{ (e: 'pilih', blokId: number): void }>();

const W = 900;
const H = computed(() => props.tinggi * 900 / 900);
const PAD = 36;

const titikSemua = computed(() => props.fitur.flatMap(f => f.poligon?.length ? f.poligon : (f.lat !== null && f.lng !== null ? [[f.lat, f.lng] as [number, number]] : [])));
const batas = computed(() => {
  const t = titikSemua.value;
  if (!t.length) return { lat0: -1, lat1: 1, lng0: 115, lng1: 117, cos: 1 };
  const lats = t.map(x => x[0]), lngs = t.map(x => x[1]);
  const lat0 = Math.min(...lats), lat1 = Math.max(...lats), lng0 = Math.min(...lngs), lng1 = Math.max(...lngs);
  return { lat0, lat1, lng0, lng1, cos: Math.cos(((lat0 + lat1) / 2) * Math.PI / 180) };
});
const skala = computed(() => {
  const b = batas.value;
  const lebar = Math.max(0.002, (b.lng1 - b.lng0) * b.cos), tinggi = Math.max(0.002, b.lat1 - b.lat0);
  return Math.min((W - PAD * 2) / lebar, (H.value - PAD * 2) / tinggi);
});
const geser0 = computed(() => {
  const b = batas.value;
  const lebar = (b.lng1 - b.lng0) * b.cos * skala.value, tinggi = (b.lat1 - b.lat0) * skala.value;
  return { x: (W - lebar) / 2, y: (H.value - tinggi) / 2 };
});
const px = (lat: number, lng: number) => {
  const b = batas.value;
  return [geser0.value.x + (lng - b.lng0) * b.cos * skala.value, geser0.value.y + (b.lat1 - lat) * skala.value];
};

const warna = (f: Fitur) => (props.mode === 'fase' ? fase(f.fase).warna : warnaTumbuh(f.tumbuh));
const bentuk = computed(() => props.fitur.map(f => {
  if (f.poligon?.length) {
    const pts = f.poligon.map(([la, ln]) => px(la, ln));
    const cx = pts.reduce((a, p) => a + p[0], 0) / pts.length, cy = pts.reduce((a, p) => a + p[1], 0) / pts.length;
    return { f, d: 'M' + pts.map(p => p.map(v => v.toFixed(1)).join(' ')).join('L') + 'Z', cx, cy, titik: false };
  }
  const [cx, cy] = px(f.lat ?? 0, f.lng ?? 0);
  return { f, d: '', cx, cy, titik: true, r: Math.max(5, Math.sqrt(f.luas) * 0.9) };
}));

// Label blok di pusat petak-petaknya.
const labelBlok = computed(() => {
  const g: Record<string, { x: number; y: number; n: number; kode: string }> = {};
  for (const b of bentuk.value) {
    const k = b.f.blok;
    g[k] ??= { x: 0, y: 0, n: 0, kode: k };
    g[k].x += b.cx; g[k].y += b.cy; g[k].n++;
  }
  return Object.values(g).map(v => ({ kode: v.kode, x: v.x / v.n, y: v.y / v.n }));
});

// Skala batang: 1 km dalam piksel peta.
const kmPx = computed(() => skala.value / 111.32);
const panjangSkala = computed(() => {
  const target = 140 / (kmPx.value * zoom.value);
  const pilih = [0.1, 0.2, 0.5, 1, 2, 5, 10, 20, 50].find(v => v >= target * 0.5) ?? 50;
  return { km: pilih, px: pilih * kmPx.value * zoom.value };
});

// Pan & zoom.
const zoom = ref(1), tx = ref(0), ty = ref(0);
const tarik = ref<{ x: number; y: number; tx: number; ty: number } | null>(null);
const svgRef = ref<SVGSVGElement | null>(null);
function keSvg(e: { clientX: number; clientY: number }) {
  const r = svgRef.value!.getBoundingClientRect();
  return { x: (e.clientX - r.left) / r.width * W, y: (e.clientY - r.top) / r.height * H.value };
}
function roda(e: WheelEvent) {
  e.preventDefault();
  const p = keSvg(e);
  const k = Math.min(12, Math.max(1, zoom.value * (e.deltaY < 0 ? 1.2 : 1 / 1.2)));
  tx.value = p.x - (p.x - tx.value) * (k / zoom.value);
  ty.value = p.y - (p.y - ty.value) * (k / zoom.value);
  zoom.value = k;
}
function perbesar(f: number) {
  const k = Math.min(12, Math.max(1, zoom.value * f));
  tx.value = W / 2 - (W / 2 - tx.value) * (k / zoom.value);
  ty.value = H.value / 2 - (H.value / 2 - ty.value) * (k / zoom.value);
  zoom.value = k;
}
function ulang() { zoom.value = 1; tx.value = 0; ty.value = 0; }
function mulai(e: PointerEvent) { const p = keSvg(e); tarik.value = { x: p.x, y: p.y, tx: tx.value, ty: ty.value }; }
function gerak(e: PointerEvent) {
  if (!tarik.value) return;
  const p = keSvg(e);
  tx.value = tarik.value.tx + (p.x - tarik.value.x);
  ty.value = tarik.value.ty + (p.y - tarik.value.y);
}
function lepas() { tarik.value = null; }

const sorot = ref<Fitur | null>(null);
const legendaTumbuh = [['< 60%', '#991B1B'], ['60–75%', '#EF4444'], ['75–80%', '#F59E0B'], ['80–90%', '#65A30D'], ['≥ 90%', '#15803D'], ['Belum dipantau', '#CBD5E1']];
const legendaFase = computed(() => Object.keys(FASE).filter(k => props.fitur.some(f => f.fase === k)).map(k => [FASE[k].pendek, FASE[k].warna]));
</script>

<template>
  <div class="pp">
    <svg ref="svgRef" :viewBox="`0 0 ${W} ${H}`" class="pp-svg" :style="{ cursor: tarik ? 'grabbing' : 'grab' }" role="img" :aria-label="`Peta ${fitur.length} petak tanam`"
         @wheel="roda" @pointerdown="mulai" @pointermove="gerak" @pointerup="lepas" @pointerleave="lepas">
      <defs>
        <pattern id="pp-kisi" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M40 0H0V40" class="pp-kisi" /></pattern>
      </defs>
      <rect :width="W" :height="H" class="pp-latar" />
      <rect :width="W" :height="H" fill="url(#pp-kisi)" />
      <g :transform="`translate(${tx} ${ty}) scale(${zoom})`">
        <template v-for="b in bentuk" :key="b.f.id">
          <path v-if="!b.titik" :d="b.d" :fill="warna(b.f)" class="pp-petak" :class="{ 'pp-sorot': sorot?.id === b.f.id }" :stroke-width="1.2 / zoom"
                @mouseenter="sorot = b.f" @mouseleave="sorot = null" @click="emit('pilih', b.f.blok_id)" />
          <circle v-else :cx="b.cx" :cy="b.cy" :r="(b.r ?? 6) / Math.sqrt(zoom)" :fill="warna(b.f)" class="pp-petak" :stroke-width="1.2 / zoom"
                  @mouseenter="sorot = b.f" @mouseleave="sorot = null" @click="emit('pilih', b.f.blok_id)" />
          <text v-if="zoom >= 2.5" :x="b.cx" :y="b.cy + 3 / zoom" text-anchor="middle" class="pp-teks-petak" :font-size="9 / zoom">{{ b.f.kode }}</text>
        </template>
        <template v-if="label">
          <text v-for="l in labelBlok" :key="l.kode" :x="l.x" :y="l.y - 4 / zoom" text-anchor="middle" class="pp-teks-blok" :font-size="12 / zoom" :stroke-width="3 / zoom">{{ l.kode }}</text>
        </template>
      </g>
      <!-- arah utara & skala -->
      <g :transform="`translate(${W - 34} 30)`" class="pp-utara"><path d="M0 -16 L7 8 L0 3 L-7 8Z" /><text y="22" text-anchor="middle">U</text></g>
      <g :transform="`translate(18 ${H - 18})`" class="pp-skala">
        <rect :width="panjangSkala.px" height="5" /><rect :width="panjangSkala.px / 2" height="5" class="pp-skala-isi" />
        <text :x="panjangSkala.px + 6" y="6">{{ panjangSkala.km }} km</text>
      </g>
    </svg>

    <div class="pp-kendali">
      <button type="button" aria-label="Perbesar" @click="perbesar(1.4)">+</button>
      <button type="button" aria-label="Perkecil" @click="perbesar(1 / 1.4)">−</button>
      <button type="button" aria-label="Kembalikan" @click="ulang">⤾</button>
    </div>

    <div v-if="sorot" class="pp-info">
      <p class="font-extrabold">{{ sorot.blok }} · petak {{ sorot.kode }}</p>
      <p>{{ sorot.nama }}</p>
      <p>{{ n(sorot.luas, 1) }} ha · {{ fase(sorot.fase).pendek }}<template v-if="sorot.fungsi"> · {{ sorot.fungsi }}</template></p>
      <p v-if="sorot.tumbuh !== null">Tumbuh <b :style="{ color: warnaTumbuh(sorot.tumbuh) }">{{ n(sorot.tumbuh, 1) }}%</b><template v-if="sorot.sehat_ha"> · {{ n(sorot.sehat_ha, 0) }} btg sehat/ha</template></p>
      <p v-else>Belum dipantau</p>
    </div>

    <div class="pp-legenda">
      <span v-for="l in (mode === 'fase' ? legendaFase : legendaTumbuh)" :key="l[0]"><i :style="{ background: l[1] }" />{{ l[0] }}</span>
    </div>
  </div>
</template>

<style scoped>
.pp { position: relative; }
.pp-svg { width: 100%; height: auto; display: block; border-radius: 14px; touch-action: none; }
.pp-latar { fill: #F1F5EC; }
.pp-kisi { fill: none; stroke: #DDE5D3; stroke-width: 1; }
.pp-petak { stroke: #FFFFFF; fill-opacity: .88; cursor: pointer; transition: fill-opacity .15s; }
.pp-petak:hover, .pp-sorot { fill-opacity: 1; stroke: #0F172A; }
.pp-teks-petak { fill: #fff; font-weight: 700; pointer-events: none; }
.pp-teks-blok { fill: #1C1917; font-weight: 900; paint-order: stroke; stroke: rgba(255, 255, 255, .9); pointer-events: none; }
.pp-utara { fill: #334155; font-size: 11px; font-weight: 800; }
.pp-skala rect { fill: #fff; stroke: #334155; stroke-width: 1; }
.pp-skala .pp-skala-isi { fill: #334155; }
.pp-skala text { font-size: 11px; font-weight: 700; fill: #334155; }
.pp-kendali { position: absolute; top: 10px; left: 10px; display: grid; gap: 4px; }
.pp-kendali button { width: 30px; height: 30px; border-radius: 9px; background: #fff; border: 1px solid #E2E8F0; font-weight: 800; color: #334155; box-shadow: 0 2px 6px rgba(0, 0, 0, .08); }
.pp-info { position: absolute; top: 10px; right: 46px; max-width: 260px; padding: 9px 12px; border-radius: 12px; background: rgba(255, 255, 255, .96);
  border: 1px solid #E2E8F0; box-shadow: 0 8px 20px -10px rgba(0, 0, 0, .35); font-size: 11.5px; color: #334155; pointer-events: none; }
.pp-legenda { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 8px; font-size: 11px; font-weight: 700; color: #57534E; }
.pp-legenda i { display: inline-block; width: 11px; height: 11px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }
:root[data-tema="gelap"] .pp-latar { fill: #18211C; }
:root[data-tema="gelap"] .pp-kisi { stroke: #24302A; }
:root[data-tema="gelap"] .pp-petak { stroke: #0F1512; }
:root[data-tema="gelap"] .pp-teks-blok { fill: #E8EDEF; stroke: rgba(15, 21, 18, .9); }
:root[data-tema="gelap"] .pp-utara, :root[data-tema="gelap"] .pp-skala text { fill: #CBD5E1; }
:root[data-tema="gelap"] .pp-kendali button, :root[data-tema="gelap"] .pp-info { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .pp-legenda { color: #B4BEC3; }
</style>
