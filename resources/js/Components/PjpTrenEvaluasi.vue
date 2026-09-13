<script setup lang="ts">
import { computed } from 'vue';

type Titik = { tahun: number; semester: number; periode: string; skor_rata_rata: number };

/**
 * Perbandingan tren evaluasi antar PJP pada satu bidang.
 *
 * Palet di sini KATEGORIKAL — ia menandai perusahaan mana, bukan sebaik
 * apa skornya — dan sengaja berbeda dari empat warna pita capaian
 * (hijau/kuning/oranye/merah) yang dipakai di seluruh modul. Memakai
 * warna yang sama untuk dua arti berbeda pada halaman yang sama membuat
 * garis merah terbaca "kritis" padahal ia hanya PJP kedelapan.
 */
const props = defineProps<{
  judul: string;
  seri: Array<{ id: number; label: string; titik: Titik[] }>;
}>();

const KATEGORIKAL = [
  '#2a78d6', '#eb6834', '#1baf7a', '#eda100',
  '#e87ba4', '#008300', '#4a3aa7', '#e34948',
];

const L = 640, T = 220, KIRI = 32, KANAN = 12, ATAS = 14, BAWAH = 26;
const lebarPlot = L - KIRI - KANAN;
const tinggiPlot = T - ATAS - BAWAH;

const berisi = computed(() => props.seri.filter((s) => s.titik.length > 0));

/** Sumbu waktu gabungan: setiap periode yang muncul pada seri mana pun. */
const periode = computed(() => {
  const peta = new Map<string, { tahun: number; semester: number }>();

  berisi.value.forEach((s) => s.titik.forEach((t) => {
    peta.set(`${t.tahun}-${t.semester}`, { tahun: t.tahun, semester: t.semester });
  }));

  return [...peta.values()].sort((a, b) => a.tahun - b.tahun || a.semester - b.semester);
});

const x = (tahun: number, semester: number) => {
  const i = periode.value.findIndex((p) => p.tahun === tahun && p.semester === semester);
  return periode.value.length <= 1 ? KIRI + lebarPlot / 2 : KIRI + (i / (periode.value.length - 1)) * lebarPlot;
};

const y = (skor: number) => ATAS + (1 - skor / 100) * tinggiPlot;

/** Seri yang tergambar dibatasi sebanyak warna yang tersedia; sisanya disebut jumlahnya. */
const tergambar = computed(() => berisi.value.slice(0, KATEGORIKAL.length));
const sisa = computed(() => berisi.value.length - tergambar.value.length);

const jalur = (titik: Titik[]) => titik.map((t) => `${x(t.tahun, t.semester)},${y(t.skor_rata_rata)}`).join(' ');
</script>

<template>
  <div v-if="berisi.length && periode.length" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
    <h3 class="mb-3 font-bold text-[14px] text-stone-800">{{ props.judul }}</h3>

    <div class="overflow-x-auto">
      <svg :viewBox="`0 0 ${L} ${T}`" class="w-full min-w-[520px]" style="max-height:260px">
        <rect :x="KIRI" :y="ATAS" :width="lebarPlot" :height="tinggiPlot" rx="12" fill="rgba(168,162,158,0.08)" />

        <g v-for="garis in [0, 50, 100]" :key="garis">
          <line :x1="KIRI" :x2="L - KANAN" :y1="y(garis)" :y2="y(garis)" stroke="#e7e5e4" stroke-width="1" />
          <text :x="KIRI - 6" :y="y(garis)" text-anchor="end" dominant-baseline="middle"
                font-size="10" fill="#a8a29e">{{ garis }}</text>
        </g>

        <text v-for="p in periode" :key="`${p.tahun}-${p.semester}`"
              :x="x(p.tahun, p.semester)" :y="T - 8" text-anchor="middle" font-size="10" fill="#a8a29e">
          S{{ p.semester }}'{{ String(p.tahun).slice(2) }}
        </text>

        <g v-for="(s, i) in tergambar" :key="s.id">
          <polyline v-if="s.titik.length > 1" :points="jalur(s.titik)" fill="none"
                    :stroke="KATEGORIKAL[i]" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
          <circle v-for="t in s.titik" :key="`${s.id}-${t.tahun}-${t.semester}`"
                  :cx="x(t.tahun, t.semester)" :cy="y(t.skor_rata_rata)" r="4.5"
                  :fill="KATEGORIKAL[i]" stroke="#ffffff" stroke-width="2">
            <title>{{ s.label }} — {{ t.periode }}: {{ t.skor_rata_rata }}</title>
          </circle>
        </g>
      </svg>
    </div>

    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 border-t border-stone-100 pt-3">
      <span v-for="(s, i) in tergambar" :key="s.id" class="flex items-center gap-1.5 text-[11px] text-stone-600">
        <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: KATEGORIKAL[i] }"></span>
        {{ s.label }}
      </span>
      <span v-if="sisa > 0" class="text-[11px] text-stone-400">+{{ sisa }} PJP lainnya</span>
    </div>
  </div>
</template>
