<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';

const STATUS_OPTIONS: Record<string, string> = {
  aktif: 'Aktif Dipantau',
  perlu_tindak_lanjut: 'Perlu Tindak Lanjut',
  tidak_aktif: 'Tidak Aktif',
};
const STATUS_BADGE: Record<string, string> = {
  aktif: 'bg-cam-lime-soft text-cam-lime-deep',
  perlu_tindak_lanjut: 'bg-amber-100 text-amber-700',
  tidak_aktif: 'bg-stone-100 text-stone-500',
};
const SEMESTER_OPTIONS: Record<number, string> = { 1: 'Semester 1', 2: 'Semester 2' };

interface MiniPjp {
  id: number; nama_perusahaan: string; status: string;
  smkpScore?: { persentase: number };
  latestEvaluasi?: { semester: number; tahun: number; skor_rata_rata: number } | null;
}

const props = defineProps<{
  action: string;
  pjps: MiniPjp[];
  filters: { search: string; status: string };
}>();

const saring = useForm({ search: props.filters.search, status: props.filters.status });

function terapkan() {
  saring.transform((d) => Object.fromEntries(Object.entries(d).filter(([, v]) => v)))
    .get(props.action, { preserveState: true, preserveScroll: true, replace: true });
}
function resetFilter() {
  saring.search = '';
  saring.status = '';
  router.get(props.action, {}, { preserveState: true, preserveScroll: true });
}
</script>

<template>
  <section id="daftar-pjp" class="mt-10 scroll-mt-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
      <h3 class="text-[16px] font-bold text-cam-ink">Daftar Seluruh PJP</h3>
      <Link href="/pjp/create" class="text-[12.5px] font-semibold text-cam-lime-deep hover:underline">+ Tambah PJP</Link>
    </div>

    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-4 mb-4 flex flex-wrap items-end gap-3" @submit.prevent="terapkan">
      <div class="flex-1 min-w-[200px]">
        <label class="text-[11px] font-bold uppercase tracking-wide text-stone-400">Cari</label>
        <input v-model="saring.search" placeholder="Nama, NIB, penanggung jawab, atau alamat…" class="ring-focus mt-1 w-full rounded-xl border border-stone-200 px-3.5 py-2 text-[13px]">
      </div>
      <div class="min-w-[180px]">
        <label class="text-[11px] font-bold uppercase tracking-wide text-stone-400">Status</label>
        <select v-model="saring.status" class="ring-focus mt-1 w-full rounded-xl border border-stone-200 px-3.5 py-2 text-[13px]">
          <option value="">Semua Status</option>
          <option v-for="(label, key) in STATUS_OPTIONS" :key="key" :value="key">{{ label }}</option>
        </select>
      </div>
      <div class="flex gap-2">
        <button type="submit" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2 text-[12.5px] font-bold">Terapkan</button>
        <button v-if="filters.search || filters.status" type="button" class="rounded-xl border border-stone-200 px-4 py-2 text-[12.5px] font-semibold text-stone-600 hover:bg-stone-50" @click="resetFilter">Reset</button>
      </div>
    </form>

    <div v-if="!pjps.length" class="bg-white rounded-2xl border border-dashed border-stone-200 p-10 text-center text-[13px] text-stone-400">
      {{ filters.search || filters.status ? 'Tidak ada data PJP yang cocok dengan filter.' : 'Belum ada data PJP.' }}
    </div>
    <div v-else class="bg-white rounded-2xl shadow-card border border-stone-100 divide-y divide-stone-100 overflow-hidden">
      <Link v-for="pjp in pjps" :key="pjp.id" :href="`/pjp/${pjp.id}`"
            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-4 py-3 text-[13px] hover:bg-stone-50 transition">
        <span class="font-semibold text-cam-ink">{{ pjp.nama_perusahaan }}</span>
        <div class="flex flex-wrap items-center gap-2">
          <span v-if="pjp.smkpScore" class="rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-600">
            Persyaratan PJP: {{ pjp.smkpScore.persentase }}%
          </span>
          <span v-if="pjp.latestEvaluasi" class="rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-600">
            Evaluasi {{ SEMESTER_OPTIONS[pjp.latestEvaluasi.semester] }} {{ pjp.latestEvaluasi.tahun }}: {{ pjp.latestEvaluasi.skor_rata_rata }}
          </span>
          <span v-else-if="pjp.latestEvaluasi === null" class="rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-400">Belum dievaluasi</span>
          <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="STATUS_BADGE[pjp.status]">
            {{ STATUS_OPTIONS[pjp.status] ?? pjp.status }}
          </span>
        </div>
      </Link>
    </div>
  </section>
</template>
