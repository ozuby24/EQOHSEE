<script setup lang="ts">
import { computed } from 'vue';

/**
 * Peta layer tambang, digambar sebagai SVG tanpa pustaka peta.
 *
 * Pustaka peta yang lazim menuntut server ubin, dan ubin dimuat dari
 * internet. Repo ini sudah sekali melepas pustaka dari CDN, dan
 * pemakaiannya mencakup Termux di lokasi yang tidak selalu punya
 * sambungan — peta yang hanya tampil ketika ada internet akan kosong
 * persis ketika ia paling dibutuhkan.
 *
 * Untuk perencanaan tambang, latar peta memang jarang menjadi soal:
 * yang dibaca adalah letak pit terhadap disposal, panjang jalan angkut,
 * dan area mana yang sudah direklamasi. Semuanya sudah terkandung di
 * dalam koordinat layernya sendiri.
 *
 * Proyeksinya sama-persegi (equirectangular) dengan bujur dikoreksi
 * kosinus lintang, sehingga bentuknya tidak memipih pada lintang tinggi.
 * Untuk satu wilayah izin tambang, penyimpangannya tidak terlihat mata.
 */
const props = defineProps<{
  layers: Array<Record<string, any>>;
  tinggi?: number;
}>();

const emit = defineEmits<{ (e: 'pilih', id: number): void }>();

const LEBAR = 800;
const TINGGI = computed(() => props.tinggi ?? 460);
const TEPI = 16;

type Cincin = Array<[number, number]>;
type Bentuk = { id: number; nama: string; warna: string; tipe: string; cincin: Cincin[]; garis: Cincin[] };

/** Mengurai geojson tiap layer menjadi cincin poligon dan garis. */
const bentuk = computed<Bentuk[]>(() => (props.layers || [])
  .filter((l) => l.geojson)
  .map((l) => {
    const cincin: Cincin[] = [];
    const garis: Cincin[] = [];

    const ambil = (g: any) => {
      if (!g || typeof g !== 'object') return;
      if (g.type === 'FeatureCollection') return (g.features || []).forEach(ambil);
      if (g.type === 'Feature') return ambil(g.geometry);
      if (g.type === 'GeometryCollection') return (g.geometries || []).forEach(ambil);

      const c = g.coordinates;
      if (!Array.isArray(c)) return;

      if (g.type === 'Polygon') c.forEach((r: any) => Array.isArray(r) && cincin.push(r));
      else if (g.type === 'MultiPolygon') c.forEach((p: any) => Array.isArray(p) && p.forEach((r: any) => cincin.push(r)));
      else if (g.type === 'LineString') garis.push(c);
      else if (g.type === 'MultiLineString') c.forEach((ln: any) => Array.isArray(ln) && garis.push(ln));
    };

    try { ambil(JSON.parse(l.geojson)); } catch { /* layer rusak dilewati, bukan menggagalkan peta */ }

    return { id: l.id, nama: l.nama, warna: l.warna, tipe: l.tipe, cincin, garis };
  })
  .filter((b) => b.cincin.length || b.garis.length));

/** Kotak batas seluruh layer, dipakai menyetel skala. */
const batas = computed(() => {
  let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;

  for (const b of bentuk.value) {
    for (const set of [b.cincin, b.garis]) {
      for (const ring of set) {
        for (const t of ring) {
          if (!Array.isArray(t)) continue;
          minX = Math.min(minX, t[0]); maxX = Math.max(maxX, t[0]);
          minY = Math.min(minY, t[1]); maxY = Math.max(maxY, t[1]);
        }
      }
    }
  }

  return Number.isFinite(minX) ? { minX, minY, maxX, maxY } : null;
});

/**
 * Skala yang menjaga perbandingan sisi.
 *
 * Bujur dikalikan kosinus lintang tengah supaya jarak timur-barat tidak
 * terlihat lebih panjang daripada sebenarnya; tanpa itu, pit persegi
 * tampak memanjang, dan orang yang membacanya menyimpulkan bentuk yang
 * salah dari gambar yang benar datanya.
 */
const skala = computed(() => {
  const b = batas.value;
  if (!b) return null;

  const kLat = Math.cos(((b.minY + b.maxY) / 2) * Math.PI / 180) || 1;
  const lebarGeo = Math.max((b.maxX - b.minX) * kLat, 1e-9);
  const tinggiGeo = Math.max(b.maxY - b.minY, 1e-9);

  const s = Math.min((LEBAR - TEPI * 2) / lebarGeo, (TINGGI.value - TEPI * 2) / tinggiGeo);
  const geserX = (LEBAR - lebarGeo * s) / 2;
  const geserY = (TINGGI.value - tinggiGeo * s) / 2;

  return {
    x: (lon: number) => geserX + (lon - b.minX) * kLat * s,
    // Sumbu y SVG menghadap ke bawah; lintang menghadap ke atas.
    y: (lat: number) => TINGGI.value - geserY - (lat - b.minY) * s,
    meterPerPiksel: 111_320 / s,
  };
});

const jalur = (ring: Cincin, tutup: boolean) => {
  const s = skala.value;
  if (!s || !ring?.length) return '';

  const d = ring
    .filter((t) => Array.isArray(t))
    .map((t, i) => `${i === 0 ? 'M' : 'L'}${s.x(t[0]).toFixed(1)},${s.y(t[1]).toFixed(1)}`)
    .join(' ');

  return tutup ? `${d} Z` : d;
};

/** Panjang batang skala yang jatuh pada angka bulat. */
const batang = computed(() => {
  const s = skala.value;
  if (!s) return null;

  const kasar = s.meterPerPiksel * 120;
  const pangkat = Math.pow(10, Math.floor(Math.log10(kasar)));
  const meter = [1, 2, 5, 10].map((k) => k * pangkat).find((v) => v >= kasar) ?? pangkat * 10;

  return {
    piksel: meter / s.meterPerPiksel,
    label: meter >= 1000 ? `${(meter / 1000).toLocaleString('id-ID')} km` : `${meter.toLocaleString('id-ID')} m`,
  };
});
</script>

<template>
  <div class="rounded-2xl bg-stone-50 border border-stone-200 overflow-hidden">
    <svg v-if="skala" :viewBox="`0 0 ${LEBAR} ${TINGGI}`" class="w-full block" :style="{ maxHeight: `${TINGGI}px` }">
      <rect :width="LEBAR" :height="TINGGI" fill="#fafaf9" />

      <g v-for="b in bentuk" :key="b.id" class="cursor-pointer" @click="emit('pilih', b.id)">
        <path v-for="(r, i) in b.cincin" :key="`p${i}`" :d="jalur(r, true)"
              :fill="b.warna" fill-opacity="0.28" :stroke="b.warna" stroke-width="1.6" />
        <path v-for="(g, i) in b.garis" :key="`g${i}`" :d="jalur(g, false)"
              fill="none" :stroke="b.warna" stroke-width="2.4" stroke-linecap="round" />
        <title>{{ b.nama }} · {{ b.tipe }}</title>
      </g>

      <!-- Batang skala. Peta tanpa skala tidak dapat dipakai memperkirakan
           jarak, dan orang tetap akan memperkirakannya. -->
      <g v-if="batang" :transform="`translate(${TEPI}, ${TINGGI - TEPI})`">
        <line x1="0" y1="0" :x2="batang.piksel" y2="0" stroke="#57534e" stroke-width="2" />
        <line x1="0" y1="-4" x2="0" y2="4" stroke="#57534e" stroke-width="2" />
        <line :x1="batang.piksel" y1="-4" :x2="batang.piksel" y2="4" stroke="#57534e" stroke-width="2" />
        <text :x="batang.piksel / 2" y="-8" text-anchor="middle" font-size="11" fill="#57534e">{{ batang.label }}</text>
      </g>
    </svg>

    <p v-else class="p-12 text-center text-[12px] text-stone-400">
      Belum ada layer bergeometri untuk digambar. Unggah GeoJSON pit, disposal, atau jalan angkut.
    </p>
  </div>
</template>
