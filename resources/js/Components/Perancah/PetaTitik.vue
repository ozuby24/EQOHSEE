<script setup lang="ts">
/*
  Peta spasial titik perancah — tanpa ubin peta dari luar.

  Jaringan tambang sering tertutup, dan ubin peta dari layanan luar
  adalah permintaan ke luar setiap kali halaman dibuka. Yang digambar
  di sini adalah denah situs dari koordinatnya sendiri: titik diproyeksi
  ekuirektangular (lintang dikoreksi cos φ), latar kontur, label zona di
  pusat kelompoknya, dan penanda berwarna tag. Perancah merah dan
  kedaluwarsa diberi cincin berdenyut supaya terlihat dari jauh.

  Gulir untuk memperbesar, seret untuk menggeser, klik penanda untuk
  membuka panelnya. Koordinat yang sama persis tetap terpisah terlihat
  karena titik ditebar sedikit (bukan datanya — hanya gambarnya).
*/
import { computed, ref } from 'vue';
import { tag } from '../../perancah';

const props = withDefaults(defineProps<{ titik: any[]; terpilih?: number | null; tinggi?: number }>(), { terpilih: null, tinggi: 460 });
const emit = defineEmits<{ (e: 'pilih', id: number): void }>();

const W = 1000;
const H = computed(() => Math.round(props.tinggi * 1.6));
const ada = computed(() => props.titik.filter(t => t.lat !== null && t.lng !== null && t.lat !== undefined));

const batas = computed(() => {
  const la = ada.value.map(t => Number(t.lat)), ln = ada.value.map(t => Number(t.lng));
  if (!la.length) return { la0: -1, la1: 1, ln0: -1, ln1: 1, k: 1 };
  const k = Math.cos(((Math.min(...la) + Math.max(...la)) / 2) * Math.PI / 180);
  return { la0: Math.min(...la), la1: Math.max(...la), ln0: Math.min(...ln), ln1: Math.max(...ln), k };
});

function proyeksi(lat: number, lng: number) {
  const b = batas.value, pad = 90;
  const lebarDeg = Math.max(1e-4, (b.ln1 - b.ln0) * b.k), tinggiDeg = Math.max(1e-4, b.la1 - b.la0);
  const s = Math.min((W - pad * 2) / lebarDeg, (H.value - pad * 2) / tinggiDeg);
  const ox = (W - lebarDeg * s) / 2, oy = (H.value - tinggiDeg * s) / 2;
  return { x: ox + (lng - b.ln0) * b.k * s, y: H.value - (oy + (lat - b.la0) * s) };
}

const titikGambar = computed(() => {
  const terpakai = new Map<string, number>();
  return ada.value.map(t => {
    const p = proyeksi(Number(t.lat), Number(t.lng));
    const kunci = `${Math.round(p.x / 6)}:${Math.round(p.y / 6)}`;
    const ke = terpakai.get(kunci) ?? 0; terpakai.set(kunci, ke + 1);
    const sudut = ke * 2.4;
    return { ...t, x: p.x + (ke ? Math.cos(sudut) * 9 * Math.sqrt(ke) : 0), y: p.y + (ke ? Math.sin(sudut) * 9 * Math.sqrt(ke) : 0), w: tag(t.status) };
  });
});

const zona = computed(() => {
  const g = new Map<string, { x: number; y: number; n: number }>();
  titikGambar.value.forEach(t => { const z = g.get(t.zona) ?? { x: 0, y: 0, n: 0 }; z.x += t.x; z.y += t.y; z.n++; g.set(t.zona, z); });
  return [...g.entries()].map(([nama, z]) => {
    const cx = z.x / z.n, cy = z.y / z.n;
    const r = Math.max(46, ...titikGambar.value.filter(t => t.zona === nama).map(t => Math.hypot(t.x - cx, t.y - cy) + 30));
    return { nama, x: cx, y: cy, r };
  });
});

const kontur = computed(() => {
  const jalur: string[] = [];
  [[0.72, 0.3], [0.22, 0.75]].forEach(([fx, fy], j) => {
    for (let k = 1; k <= 9; k++) {
      const r = 30 + k * 44, cx = W * fx, cy = H.value * fy, pts: string[] = [];
      for (let i = 0; i <= 64; i++) {
        const t = (i / 64) * Math.PI * 2;
        const rr = r * (1 + 0.12 * Math.sin(3 * t + k * 0.6 + j) + 0.06 * Math.cos(5 * t - k * 0.4));
        pts.push(`${(cx + rr * Math.cos(t) * 1.3).toFixed(1)},${(cy + rr * Math.sin(t)).toFixed(1)}`);
      }
      jalur.push('M' + pts.join('L') + 'Z');
    }
  });
  return jalur;
});

/* ── zoom & geser ── */
const z = ref(1), gx = ref(0), gy = ref(0);
const svg = ref<SVGSVGElement | null>(null);
let seret: { x: number; y: number; gx: number; gy: number; pindah: boolean } | null = null;
const ke = (e: { clientX: number; clientY: number }) => {
  const r = svg.value!.getBoundingClientRect();
  return { x: ((e.clientX - r.left) / r.width) * W, y: ((e.clientY - r.top) / r.height) * H.value };
};
function perbesar(f: number, pusat?: { x: number; y: number }) {
  const baru = Math.min(6, Math.max(1, z.value * f));
  const c = pusat ?? { x: W / 2, y: H.value / 2 };
  gx.value = c.x - (c.x - gx.value) * (baru / z.value);
  gy.value = c.y - (c.y - gy.value) * (baru / z.value);
  z.value = baru;
  if (baru === 1) { gx.value = 0; gy.value = 0; }
}
function gulir(e: WheelEvent) { e.preventDefault(); perbesar(e.deltaY < 0 ? 1.25 : 0.8, ke(e)); }
function tekan(e: PointerEvent) { const p = ke(e); seret = { x: p.x, y: p.y, gx: gx.value, gy: gy.value, pindah: false }; }
function geser(e: PointerEvent) {
  if (!seret) return;
  const p = ke(e);
  if (Math.hypot(p.x - seret.x, p.y - seret.y) > 4) seret.pindah = true;
  if (seret.pindah && z.value > 1) { gx.value = seret.gx + (p.x - seret.x); gy.value = seret.gy + (p.y - seret.y); }
}
function lepas() { setTimeout(() => (seret = null), 0); }
function klik(id: number) { if (!seret?.pindah) emit('pilih', id); }

const sorot = ref<any | null>(null);
const skalaPenanda = computed(() => 1 / Math.sqrt(z.value));
</script>

<template>
  <div class="pt" :style="{ height: tinggi + 'px' }">
    <svg ref="svg" :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="xMidYMid meet" class="pt-svg"
         role="img" :aria-label="`Peta ${ada.length} titik perancah`"
         @wheel="gulir" @pointerdown="tekan" @pointermove="geser" @pointerup="lepas" @pointerleave="lepas(); sorot = null">
      <defs>
        <radialGradient id="pt-latar" cx="50%" cy="40%" r="75%">
          <stop offset="0%" stop-color="#FBFAF7" /><stop offset="100%" stop-color="#EEF1EC" />
        </radialGradient>
      </defs>
      <rect :width="W" :height="H" fill="url(#pt-latar)" />
      <g :transform="`translate(${gx} ${gy}) scale(${z})`">
        <path v-for="(d, i) in kontur" :key="'k' + i" :d="d" fill="none" stroke="#D9DED6" :stroke-width="1 / z" />
        <g v-for="zn in zona" :key="zn.nama">
          <circle :cx="zn.x" :cy="zn.y" :r="zn.r" fill="#FFFFFF" fill-opacity=".55" stroke="#CBD5C9" :stroke-width="1.2 / z" stroke-dasharray="6 5" />
          <text :x="zn.x" :y="zn.y - zn.r - 8 / z" text-anchor="middle" :font-size="13 / Math.sqrt(z)" font-weight="800" fill="#57534E">{{ zn.nama }}</text>
        </g>
        <g v-for="t in titikGambar" :key="t.id" class="pt-penanda" :transform="`translate(${t.x} ${t.y}) scale(${skalaPenanda})`"
           @click.stop="klik(t.id)" @pointerenter="sorot = t" @pointerleave="sorot = null">
          <circle v-if="t.status === 'merah' || t.status === 'kedaluwarsa'" r="20" :fill="t.w.warna" class="pt-denyut" />
          <circle v-if="terpilih === t.id" r="19" fill="none" stroke="#0F172A" stroke-width="3" />
          <path d="M0 -26 C-11 -26 -15 -17 -15 -12 C-15 -2 0 8 0 8 C0 8 15 -2 15 -12 C15 -17 11 -26 0 -26Z" :fill="t.w.warna" stroke="#fff" stroke-width="2.5" />
          <circle cy="-13" r="5.5" fill="#fff" />
        </g>
      </g>
    </svg>

    <div v-if="sorot" class="pt-sorot">
      <p class="font-bold text-cam-ink">{{ sorot.kode }} · {{ sorot.judul }}</p>
      <p class="text-stone-500">{{ sorot.zona }} · {{ sorot.dimensi }}</p>
      <span class="pr-pil mt-1" :class="sorot.w.kelas">{{ sorot.w.pendek }}</span>
    </div>

    <div class="pt-kendali">
      <button type="button" aria-label="Perbesar" @click="perbesar(1.4)">+</button>
      <button type="button" aria-label="Perkecil" @click="perbesar(1 / 1.4)">−</button>
      <button type="button" aria-label="Tampilkan semua" class="text-[11px]" @click="z = 1; gx = 0; gy = 0">⤢</button>
    </div>
    <div class="pt-legenda">
      <span v-for="k in ['hijau', 'kuning', 'merah', 'kedaluwarsa']" :key="k"><i :style="{ background: tag(k).warna }" />{{ tag(k).pendek }}</span>
    </div>
    <p v-if="!ada.length" class="absolute inset-0 grid place-items-center text-[12px] text-stone-400">Belum ada perancah berkoordinat.</p>
  </div>
</template>

<style scoped>
.pt { position: relative; border-radius: 16px; overflow: hidden; border: 1px solid #E7E5E4; background: #F4F5F1; touch-action: none; }
.pt-svg { width: 100%; height: 100%; display: block; cursor: grab; }
.pt-svg:active { cursor: grabbing; }
.pt-penanda { cursor: pointer; }
.pt-penanda:hover path { filter: brightness(1.08); }
.pt-denyut { transform-origin: 0 -13px; animation: pt-denyut 1.8s ease-out infinite; opacity: .35; }
@keyframes pt-denyut { 0% { transform: translateY(-13px) scale(.4); opacity: .55; } 100% { transform: translateY(-13px) scale(2.1); opacity: 0; } }
@media (prefers-reduced-motion: reduce) { .pt-denyut { animation: none; } }
.pt-sorot { position: absolute; left: 12px; top: 12px; max-width: 260px; padding: 9px 12px; font-size: 11.5px; border-radius: 12px;
  background: rgba(255,255,255,.96); border: 1px solid #E7E5E4; box-shadow: 0 10px 24px -14px rgba(0,0,0,.4); pointer-events: none; }
.pt-kendali { position: absolute; right: 12px; top: 12px; display: grid; gap: 6px; }
.pt-kendali button { width: 34px; height: 34px; border-radius: 10px; background: #fff; border: 1px solid #E7E5E4; font-size: 18px; font-weight: 800;
  color: #44403C; box-shadow: 0 6px 14px -10px rgba(0,0,0,.4); }
.pt-legenda { position: absolute; left: 12px; bottom: 12px; display: flex; flex-wrap: wrap; gap: 10px; padding: 7px 11px; border-radius: 999px;
  background: rgba(255,255,255,.94); border: 1px solid #E7E5E4; font-size: 11px; font-weight: 700; color: #57534E; }
.pt-legenda span { display: inline-flex; align-items: center; gap: 5px; }
.pt-legenda i { width: 9px; height: 9px; border-radius: 999px; display: inline-block; }
:root[data-tema="gelap"] .pt { background: #1A2023; border-color: #2A3236; }
:root[data-tema="gelap"] .pt-sorot, :root[data-tema="gelap"] .pt-legenda, :root[data-tema="gelap"] .pt-kendali button { background: #20272B; border-color: #2E373C; color: #C9D2D6; }
</style>
