<script setup lang="ts">
/*
  Peta Spasial MERANTI — fitur GeoJSON (poligon, garis, titik) digambar
  dari koordinatnya sendiri, tanpa ubin peta dari internet: jaringan di
  site tambang sering tertutup, dan peta yang menunggu ubin dari luar
  hanya menampilkan kotak abu-abu.

  Proyeksi ekuirektangular dengan koreksi cos(lintang) — cukup untuk
  skala blok reklamasi. Warna mengikuti kategori fitur. Gulir untuk
  memperbesar, seret untuk menggeser, klik fitur untuk memilihnya.
*/
import { computed, ref } from 'vue';
import { n } from '../../energi';
import { warnaKategori } from '../../meranti';

type Geometri = { type: 'Polygon'; coordinates: number[][][] } | { type: 'LineString'; coordinates: number[][] } | { type: 'Point'; coordinates: number[] };
export type FiturPeta = { id: number; nama: string; kategori: string | null; petak?: string | null; week?: string | null; month?: string | null;
  year?: number | null; luas: number | null; sumber?: string | null; geometri: Geometri };

const props = withDefaults(defineProps<{ fitur: FiturPeta[]; tinggi?: number; terpilih?: number | null; label?: boolean }>(),
  { tinggi: 460, terpilih: null, label: true });
const emit = defineEmits<{ (e: 'pilih', id: number): void }>();

const W = 900;
const H = computed(() => props.tinggi);
const PAD = 30;

const titikSemua = computed(() => {
  const t: number[][] = [];
  for (const f of props.fitur) {
    const g = f.geometri;
    if (g.type === 'Polygon') g.coordinates.forEach(r => r.forEach(p => t.push(p)));
    else if (g.type === 'LineString') g.coordinates.forEach(p => t.push(p));
    else t.push(g.coordinates);
  }
  return t;
});
const batas = computed(() => {
  const t = titikSemua.value;
  if (!t.length) return { lng0: 115, lng1: 117, lat0: -1, lat1: 1, cos: 1 };
  let lng0 = Infinity, lng1 = -Infinity, lat0 = Infinity, lat1 = -Infinity;
  for (const [x, y] of t) { if (x < lng0) lng0 = x; if (x > lng1) lng1 = x; if (y < lat0) lat0 = y; if (y > lat1) lat1 = y; }
  return { lng0, lng1, lat0, lat1, cos: Math.cos(((lat0 + lat1) / 2) * Math.PI / 180) };
});
const skala = computed(() => {
  const b = batas.value;
  const lebar = Math.max(0.0015, (b.lng1 - b.lng0) * b.cos), tinggi = Math.max(0.0015, b.lat1 - b.lat0);
  return Math.min((W - PAD * 2) / lebar, (H.value - PAD * 2) / tinggi);
});
const geser0 = computed(() => {
  const b = batas.value;
  return { x: (W - (b.lng1 - b.lng0) * b.cos * skala.value) / 2, y: (H.value - (b.lat1 - b.lat0) * skala.value) / 2 };
});
const px = (lng: number, lat: number): [number, number] => {
  const b = batas.value;
  return [geser0.value.x + (lng - b.lng0) * b.cos * skala.value, geser0.value.y + (b.lat1 - lat) * skala.value];
};
const jalur = (titik: number[][], tutup: boolean) => 'M' + titik.map(([x, y]) => px(x, y).map(v => v.toFixed(1)).join(' ')).join('L') + (tutup ? 'Z' : '');

const bentuk = computed(() => props.fitur.map(f => {
  const g = f.geometri;
  if (g.type === 'Point') {
    const [cx, cy] = px(g.coordinates[0], g.coordinates[1]);
    return { f, jenis: 'titik' as const, d: '', cx, cy };
  }
  const cincin = g.type === 'Polygon' ? g.coordinates : [g.coordinates];
  const luar = cincin[0] ?? [];
  const pts = luar.map(([x, y]) => px(x, y));
  const cx = pts.reduce((a, p) => a + p[0], 0) / Math.max(1, pts.length), cy = pts.reduce((a, p) => a + p[1], 0) / Math.max(1, pts.length);
  return { f, jenis: g.type === 'Polygon' ? 'poligon' as const : 'garis' as const, d: cincin.map(r => jalur(r, g.type === 'Polygon')).join(' '), cx, cy };
}));

// Skala batang: 1 km dalam piksel.
const kmPx = computed(() => skala.value / 111.32);
const panjangSkala = computed(() => {
  const target = 140 / (kmPx.value * zoom.value);
  const pilih = [0.05, 0.1, 0.2, 0.5, 1, 2, 5, 10, 20, 50].find(v => v >= target * 0.5) ?? 50;
  return { km: pilih, px: pilih * kmPx.value * zoom.value };
});

// Geser & perbesar.
const zoom = ref(1), tx = ref(0), ty = ref(0);
const tarik = ref<{ x: number; y: number; tx: number; ty: number } | null>(null);
// Seret yang berakhir di atas fitur juga memicu klik; yang benar-benar bergeser tidak dihitung memilih.
let digeser = false;
const svgRef = ref<SVGSVGElement | null>(null);
function keSvg(e: { clientX: number; clientY: number }) {
  const r = svgRef.value!.getBoundingClientRect();
  return { x: (e.clientX - r.left) / r.width * W, y: (e.clientY - r.top) / r.height * H.value };
}
function setZoom(k: number, p: { x: number; y: number }) {
  k = Math.min(40, Math.max(1, k));
  tx.value = p.x - (p.x - tx.value) * (k / zoom.value);
  ty.value = p.y - (p.y - ty.value) * (k / zoom.value);
  zoom.value = k;
}
function roda(e: WheelEvent) { e.preventDefault(); setZoom(zoom.value * (e.deltaY < 0 ? 1.25 : 1 / 1.25), keSvg(e)); }
function perbesar(f: number) { setZoom(zoom.value * f, { x: W / 2, y: H.value / 2 }); }
function ulang() { zoom.value = 1; tx.value = 0; ty.value = 0; }
function mulai(e: PointerEvent) { const p = keSvg(e); digeser = false; tarik.value = { x: p.x, y: p.y, tx: tx.value, ty: ty.value }; }
function gerak(e: PointerEvent) {
  if (!tarik.value) return;
  const p = keSvg(e);
  if (Math.abs(p.x - tarik.value.x) + Math.abs(p.y - tarik.value.y) > 4) digeser = true;
  tx.value = tarik.value.tx + (p.x - tarik.value.x);
  ty.value = tarik.value.ty + (p.y - tarik.value.y);
}
function lepas() { tarik.value = null; }
function klik(id: number) { if (!digeser) emit('pilih', id); }

/** Pusatkan dan perbesar ke satu fitur — dipanggil dari daftar di samping peta. */
function fokus(id: number) {
  const b = bentuk.value.find(x => x.f.id === id);
  if (!b) return;
  const k = Math.max(zoom.value, 3);
  zoom.value = k;
  tx.value = W / 2 - b.cx * k;
  ty.value = H.value / 2 - b.cy * k;
}
defineExpose({ fokus, ulang });

const sorot = ref<FiturPeta | null>(null);
const legenda = computed(() => [...new Set(props.fitur.map(f => f.kategori || 'Lainnya'))].map(k => ({ k, w: warnaKategori(k) })));
const tampilLabel = computed(() => props.label && (props.fitur.length <= 16 || zoom.value >= 3));
</script>

<template>
  <div class="mp">
    <svg ref="svgRef" :viewBox="`0 0 ${W} ${H}`" class="mp-svg" :style="{ cursor: tarik ? 'grabbing' : 'grab' }" role="img" :aria-label="`Peta ${fitur.length} fitur spasial`"
         @wheel="roda" @pointerdown="mulai" @pointermove="gerak" @pointerup="lepas" @pointerleave="lepas">
      <defs>
        <pattern id="mp-kisi" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M40 0H0V40" class="mp-kisi" /></pattern>
      </defs>
      <rect :width="W" :height="H" class="mp-latar" />
      <rect :width="W" :height="H" fill="url(#mp-kisi)" />
      <g :transform="`translate(${tx} ${ty}) scale(${zoom})`">
        <template v-for="b in bentuk" :key="b.f.id">
          <path v-if="b.jenis === 'poligon'" :d="b.d" :fill="warnaKategori(b.f.kategori)" fill-rule="evenodd" class="mp-fitur"
                :class="{ 'mp-sorot': sorot?.id === b.f.id || terpilih === b.f.id }" :stroke-width="(terpilih === b.f.id ? 2.6 : 1.1) / zoom"
                @mouseenter="sorot = b.f" @mouseleave="sorot = null" @click="klik(b.f.id)" />
          <path v-else-if="b.jenis === 'garis'" :d="b.d" fill="none" :stroke="warnaKategori(b.f.kategori)" class="mp-garis" :stroke-width="2.4 / zoom"
                @mouseenter="sorot = b.f" @mouseleave="sorot = null" @click="klik(b.f.id)" />
          <circle v-else :cx="b.cx" :cy="b.cy" :r="6 / Math.sqrt(zoom)" :fill="warnaKategori(b.f.kategori)" class="mp-fitur mp-titik"
                  :class="{ 'mp-sorot': sorot?.id === b.f.id || terpilih === b.f.id }" :stroke-width="1.4 / zoom"
                  @mouseenter="sorot = b.f" @mouseleave="sorot = null" @click="klik(b.f.id)" />
        </template>
        <template v-if="tampilLabel">
          <text v-for="b in bentuk" :key="'l' + b.f.id" :x="b.cx" :y="b.cy + (b.jenis === 'titik' ? -9 : 3) / zoom" text-anchor="middle" class="mp-teks"
                :font-size="11 / zoom" :stroke-width="3 / zoom">{{ b.f.nama }}</text>
        </template>
      </g>
      <g :transform="`translate(${W - 34} 30)`" class="mp-utara"><path d="M0 -16 L7 8 L0 3 L-7 8Z" /><text y="22" text-anchor="middle">U</text></g>
      <g :transform="`translate(18 ${H - 18})`" class="mp-skala">
        <rect :width="panjangSkala.px" height="5" /><rect :width="panjangSkala.px / 2" height="5" class="mp-skala-isi" />
        <text :x="panjangSkala.px + 6" y="6">{{ panjangSkala.km < 1 ? n(panjangSkala.km * 1000, 0) + ' m' : panjangSkala.km + ' km' }}</text>
      </g>
      <text v-if="!fitur.length" :x="W / 2" :y="H / 2" text-anchor="middle" class="mp-kosong">Belum ada fitur peta — unggah KML, KMZ, atau GeoJSON.</text>
    </svg>

    <div class="mp-kendali">
      <button type="button" aria-label="Perbesar" @click="perbesar(1.5)">+</button>
      <button type="button" aria-label="Perkecil" @click="perbesar(1 / 1.5)">−</button>
      <button type="button" aria-label="Tampilkan seluruh peta" @click="ulang">⤾</button>
    </div>

    <div v-if="sorot" class="mp-info">
      <p class="font-extrabold">{{ sorot.nama }}</p>
      <p>{{ sorot.kategori || 'Lainnya' }}<template v-if="sorot.petak"> · {{ sorot.petak }}</template><template v-if="sorot.luas"> · {{ n(sorot.luas, 2) }} ha</template></p>
      <p v-if="sorot.week || sorot.month || sorot.year">{{ [sorot.week, sorot.month, sorot.year].filter(Boolean).join(' · ') }}</p>
      <p v-if="sorot.sumber" class="text-stone-500">{{ sorot.sumber }}</p>
    </div>

    <div v-if="legenda.length" class="mp-legenda">
      <span v-for="l in legenda" :key="l.k"><i :style="{ background: l.w }" />{{ l.k }}</span>
    </div>
  </div>
</template>

<style scoped>
.mp { position: relative; }
.mp-svg { width: 100%; height: auto; display: block; border-radius: 14px; touch-action: none; }
.mp-latar { fill: #F1F5EC; }
.mp-kisi { fill: none; stroke: #DDE5D3; stroke-width: 1; }
.mp-fitur { stroke: #FFFFFF; fill-opacity: .82; cursor: pointer; transition: fill-opacity .15s; }
.mp-fitur:hover, .mp-sorot { fill-opacity: 1; stroke: #0A3A2A; }
.mp-garis { cursor: pointer; }
.mp-teks { fill: #0A3A2A; font-weight: 800; paint-order: stroke; stroke: rgba(255, 255, 255, .92); pointer-events: none; }
.mp-utara { fill: #334155; font-size: 11px; font-weight: 800; }
.mp-skala rect { fill: #fff; stroke: #334155; stroke-width: 1; }
.mp-skala .mp-skala-isi { fill: #334155; }
.mp-skala text { font-size: 11px; font-weight: 700; fill: #334155; }
.mp-kosong { font-size: 14px; font-weight: 700; fill: #78716C; }
.mp-kendali { position: absolute; top: 10px; left: 10px; display: grid; gap: 4px; }
.mp-kendali button { width: 30px; height: 30px; border-radius: 9px; background: #fff; border: 1px solid #E2E8F0; font-weight: 800; color: #334155; box-shadow: 0 2px 6px rgba(0, 0, 0, .08); }
.mp-info { position: absolute; top: 10px; right: 46px; max-width: 270px; padding: 9px 12px; border-radius: 12px; background: rgba(255, 255, 255, .96);
  border: 1px solid #E2E8F0; box-shadow: 0 8px 20px -10px rgba(0, 0, 0, .35); font-size: 11.5px; color: #334155; pointer-events: none; }
.mp-legenda { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 8px; font-size: 11px; font-weight: 700; color: #57534E; }
.mp-legenda i { display: inline-block; width: 11px; height: 11px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }
:root[data-tema="gelap"] .mp-latar { fill: #18211C; }
:root[data-tema="gelap"] .mp-kisi { stroke: #24302A; }
:root[data-tema="gelap"] .mp-fitur { stroke: #0F1512; }
:root[data-tema="gelap"] .mp-teks { fill: #E8EDEF; stroke: rgba(15, 21, 18, .9); }
:root[data-tema="gelap"] .mp-utara, :root[data-tema="gelap"] .mp-skala text { fill: #CBD5E1; }
:root[data-tema="gelap"] .mp-kendali button, :root[data-tema="gelap"] .mp-info { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .mp-legenda { color: #B4BEC3; }
</style>
