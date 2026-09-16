<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PjpStatusStackedBar from '../../Components/PjpStatusStackedBar.vue';
import PjpAchievementBarChart from '../../Components/PjpAchievementBarChart.vue';
import PjpTahapanSection from '../../Components/PjpTahapanSection.vue';

interface MiniPjp { id: number; nama_perusahaan: string; status: string; achievement: number | null }

const props = defineProps<{
  pjps: MiniPjp[];
  filters: { search: string; status: string };
  statusCounts: Record<string, number>;
}>();

const subTahapan = [
  { title: 'Persyaratan', description: 'Kelengkapan dokumen dan syarat administratif calon PJP.' },
  { title: 'Seleksi', description: 'Proses penilaian dan seleksi calon PJP.' },
  { title: 'Penetapan', description: 'Penetapan resmi PJP yang lolos proses seleksi.' },
];
</script>

<template>
  <Head title="Persyaratan, Seleksi, dan Penetapan" />

  <div class="max-w-5xl mx-auto">
    <h2 class="font-serif text-xl font-bold text-cam-ink">Persyaratan, Seleksi, dan Penetapan</h2>
    <p class="text-[12.5px] text-stone-500 mt-1 mb-6">
      Tahapan awal pengelolaan PJP: pemeriksaan persyaratan, proses seleksi, hingga penetapan resmi.
    </p>

    <div class="mb-4"><PjpStatusStackedBar title="Capaian Status Seluruh PJP" :counts="statusCounts" /></div>

    <div class="mb-8">
      <PjpAchievementBarChart title="Capaian Persyaratan PJP per Perusahaan" empty-message="Belum ada data PJP."
        no-data-label="Belum diisi"
        :items="pjps.map((p) => ({ id: p.id, label: p.nama_perusahaan, value: p.achievement }))"
        :href-for="(item) => `/pjp/${item.id}/checklist-smkp`" />
    </div>

    <div class="grid sm:grid-cols-3 gap-4 mb-8">
      <div v-for="s in subTahapan" :key="s.title" class="bg-white rounded-2xl border border-dashed border-stone-200 p-5">
        <h4 class="font-bold text-[13px] text-cam-ink">{{ s.title }}</h4>
        <p class="text-[12px] text-stone-500 mt-1">{{ s.description }}</p>
      </div>
    </div>

    <div class="rounded-2xl border border-orange-200 bg-orange-50 p-5 text-[12.5px] text-orange-900 mb-8">
      <p class="font-bold">Persyaratan PJP</p>
      <p class="mt-1">
        Setiap PJP wajib mengisi checklist prakualifikasi SMKP untuk menunjukkan tingkat kepatuhannya.
        Klik salah satu baris pada grafik di atas (atau salah satu PJP pada daftar di bawah) untuk langsung
        masuk ke halaman <strong>"Persyaratan PJP"</strong>-nya dan mengisi atau melihat skornya.
      </p>
    </div>

    <PjpTahapanSection action="/pjp-tahapan/persyaratan" :pjps="pjps" :filters="filters" />
  </div>
</template>
