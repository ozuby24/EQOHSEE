<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PjpStatusStackedBar from '../../Components/PjpStatusStackedBar.vue';
import PjpAchievementBarChart from '../../Components/PjpAchievementBarChart.vue';
import PjpTahapanSection from '../../Components/PjpTahapanSection.vue';

interface MiniPjp { id: number; nama_perusahaan: string; status: string; achievement: number | null }
interface TrendPoint { tahun: number; semester: number; rata_rata: number; jumlah_pjp: number }

const SEMESTER_OPTIONS: Record<number, string> = { 1: 'Semester 1', 2: 'Semester 2' };

const props = defineProps<{
  pjps: MiniPjp[];
  filters: { search: string; status: string };
  statusCounts: Record<string, number>;
  evaluasiTrendGabungan: TrendPoint[];
}>();

const tren = computed(() => {
  const points = props.evaluasiTrendGabungan;
  if (points.length < 2) return null;

  const width = 600, height = 140, padX = 20, padY = 16;
  const step = (width - padX * 2) / (points.length - 1);
  const coords = points.map((p, i) => ({
    x: padX + i * step,
    y: padY + (1 - p.rata_rata / 100) * (height - padY * 2),
    point: p,
  }));
  const path = coords.map((c) => `${c.x},${c.y}`).join(' ');
  const area = `${padX},${height} ${path} ${width - padX},${height}`;

  return { width, height, coords, path, area, first: points[0], last: points[points.length - 1] };
});
</script>

<template>
  <Head title="Evaluasi" />

  <div class="max-w-5xl mx-auto">
    <h2 class="font-serif text-xl font-bold text-cam-ink">Evaluasi</h2>
    <p class="text-[12.5px] text-stone-500 mt-1 mb-6">
      Evaluasi kinerja PJP setiap semester (Teknis, Keselamatan &amp; Kesehatan, Lingkungan) sebagai dasar tindak lanjut.
    </p>

    <div class="mb-4"><PjpStatusStackedBar title="Capaian Status Seluruh PJP" :counts="statusCounts" /></div>

    <div class="mb-8">
      <PjpAchievementBarChart title="Skor Evaluasi Kinerja per Perusahaan" empty-message="Belum ada data PJP."
        no-data-label="Belum dievaluasi"
        :items="pjps.map((p) => ({ id: p.id, label: p.nama_perusahaan, value: p.achievement }))" />
    </div>

    <div v-if="tren" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 mb-8">
      <h3 class="text-[13px] font-bold text-cam-ink">Tren Rata-Rata Skor Evaluasi Seluruh PJP</h3>
      <p class="text-[11.5px] text-stone-500 mt-0.5 mb-3">Rata-rata skor evaluasi semua PJP per semester — arah portofolio secara keseluruhan.</p>
      <svg :viewBox="`0 0 ${tren.width} ${tren.height}`" class="w-full">
        <defs>
          <linearGradient id="trenGabunganGradient" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#F57C00" stop-opacity="0.35"/>
            <stop offset="100%" stop-color="#F57C00" stop-opacity="0"/>
          </linearGradient>
        </defs>
        <rect :x="20" :y="16" :width="tren.width - 40" :height="tren.height - 32" fill="rgba(120,113,108,.06)" rx="10"/>
        <polygon :points="tren.area" fill="url(#trenGabunganGradient)"/>
        <polyline :points="tren.path" fill="none" stroke="#F57C00" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        <circle v-for="(c, i) in tren.coords" :key="i" :cx="c.x" :cy="c.y" :r="i === tren.coords.length - 1 ? 5 : 4" fill="#F57C00" stroke="#fff" stroke-width="2">
          <title>{{ `${SEMESTER_OPTIONS[c.point.semester]} ${c.point.tahun}: ${c.point.rata_rata} (rata-rata dari ${c.point.jumlah_pjp} PJP)` }}</title>
        </circle>
      </svg>
      <div class="flex items-center justify-between text-[11px] text-stone-400 mt-1">
        <span>{{ SEMESTER_OPTIONS[tren.first.semester] }} {{ tren.first.tahun }}</span>
        <span class="font-bold text-stone-600">Terakhir: {{ tren.last.rata_rata }}</span>
        <span>{{ SEMESTER_OPTIONS[tren.last.semester] }} {{ tren.last.tahun }}</span>
      </div>
    </div>

    <div class="rounded-2xl border border-orange-200 bg-orange-50 p-5 text-[12.5px] text-orange-900 mb-8">
      <p class="font-bold">Evaluasi Kinerja Semester</p>
      <p class="mt-1">
        Klik salah satu PJP pada daftar di bawah untuk mengisi skor evaluasi semesteran pada bagian
        "Evaluasi Kinerja" di halaman detailnya. Skor rata-rata evaluasi terakhir tiap PJP juga
        ditampilkan langsung pada daftar di bawah ini.
      </p>
    </div>

    <PjpTahapanSection action="/pjp-tahapan/evaluasi" :pjps="pjps" :filters="filters" />
  </div>
</template>
