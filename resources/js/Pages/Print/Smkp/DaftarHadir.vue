<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import CetakLayout from '../../../Components/Smkp/CetakLayout.vue';
import Lembar from '../../../Components/Smkp/Lembar.vue';
import DataAuditi from '../../../Components/Smkp/DataAuditi.vue';
import type { KopDok, RingkasAudit } from '../../../smkp/types';
import BlankLayout from '../../../Layouts/BlankLayout.vue';

/* Lembar cetak di luar kerangka aplikasi (konvensi EQOHSEE: Pages/Print + BlankLayout). */
defineOptions({ layout: BlankLayout });


const props = defineProps<{
  audit: RingkasAudit;
  judul: string;
  hadir: Array<{ id: number; nama: string; jabatan: string | null; perusahaan: string | null }>;
  dok: KopDok;
  kembali: string;
}>();

const PER = 16;
const lembar = computed(() => {
  const out: Array<typeof props.hadir> = [];
  for (let i = 0; i < props.hadir.length; i += PER) out.push(props.hadir.slice(i, i + PER));
  return out.length ? out : [[]];
});
</script>

<template>
  <Head :title="`Daftar Hadir ${judul}`" />
  <CetakLayout :judul="`Daftar Hadir ${judul}`" :kembali="kembali">
    <Lembar v-for="(bagian, h) in lembar" :key="h" :dok="dok" :halaman="h + 1" :dari="lembar.length">
      <template v-if="h === 0">
        <header class="text-center border-b border-slate-200 pb-4 mb-5">
          <h1 class="text-[15px] font-bold uppercase text-navy-900">Daftar Hadir {{ judul }}</h1>
          <p class="text-[12px] font-bold uppercase text-navy-900 mt-0.5">Audit Internal Sistem Manajemen Keselamatan Pertambangan</p>
        </header>
        <div class="mb-5"><DataAuditi :audit="audit" /></div>
      </template>
      <table class="w-full">
        <thead><tr class="border-b-2 border-slate-300 text-left text-slate-500"><th class="py-2 w-10">No</th><th class="py-2">Nama</th><th class="py-2 w-40">Jabatan</th><th class="py-2 w-44">Perusahaan</th><th class="py-2 w-32">Tanda Tangan</th></tr></thead>
        <tbody>
          <tr v-for="(p, i) in bagian" :key="p.id" class="border-b border-slate-200">
            <td class="py-2.5 text-slate-400 tabular-nums">{{ h * PER + i + 1 }}</td><td class="py-2.5 font-semibold text-navy-900">{{ p.nama }}</td><td class="py-2.5">{{ p.jabatan || '—' }}</td><td class="py-2.5">{{ p.perusahaan || '—' }}</td>
            <td class="py-2.5"><div class="h-7 border-b border-dashed border-slate-300" /></td>
          </tr>
          <tr v-if="!bagian.length"><td colspan="5" class="py-10 text-center text-slate-400">Belum ada peserta tercatat.</td></tr>
        </tbody>
      </table>
    </Lembar>
  </CetakLayout>
</template>
