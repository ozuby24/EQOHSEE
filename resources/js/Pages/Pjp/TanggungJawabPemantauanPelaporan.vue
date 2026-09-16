<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PjpStatusStackedBar from '../../Components/PjpStatusStackedBar.vue';
import PjpAchievementBarChart from '../../Components/PjpAchievementBarChart.vue';
import PjpTahapanSection from '../../Components/PjpTahapanSection.vue';

interface MiniPjp { id: number; nama_perusahaan: string; status: string; achievement: number | null }

defineProps<{
  pjps: MiniPjp[];
  filters: { search: string; status: string };
  statusCounts: Record<string, number>;
}>();

const subTahapan = [
  { title: 'Tanggung Jawab', description: 'Pembagian tanggung jawab PJP dalam menjalankan operasinya.' },
  { title: 'Pemantauan', description: 'Pemantauan berkala terhadap kinerja dan kepatuhan PJP.' },
  { title: 'Pelaporan', description: 'Pelaporan hasil pemantauan dan kondisi terkini PJP.' },
];
</script>

<template>
  <Head title="Tanggung Jawab, Pemantauan, dan Pelaporan" />

  <div class="max-w-5xl mx-auto">
    <h2 class="font-serif text-xl font-bold text-cam-ink">Tanggung Jawab, Pemantauan, dan Pelaporan</h2>
    <p class="text-[12.5px] text-stone-500 mt-1 mb-6">
      Tahapan pengelolaan berkelanjutan PJP: tanggung jawab operasional, pemantauan rutin, dan pelaporan hasil pemantauan.
    </p>

    <div class="mb-4"><PjpStatusStackedBar title="Capaian Status Seluruh PJP" :counts="statusCounts" /></div>

    <div class="mb-8">
      <PjpAchievementBarChart title="Kepatuhan Pelaporan per Perusahaan" empty-message="Belum ada data PJP."
        no-data-label="Belum ada laporan"
        :items="pjps.map((p) => ({ id: p.id, label: p.nama_perusahaan, value: p.achievement }))" />
    </div>

    <div class="grid sm:grid-cols-3 gap-4 mb-8">
      <div v-for="s in subTahapan" :key="s.title" class="bg-white rounded-2xl border border-dashed border-stone-200 p-5">
        <h4 class="font-bold text-[13px] text-cam-ink">{{ s.title }}</h4>
        <p class="text-[12px] text-stone-500 mt-1">{{ s.description }}</p>
      </div>
    </div>

    <PjpTahapanSection action="/pjp-tahapan/pelaporan" :pjps="pjps" :filters="filters" />
  </div>
</template>
