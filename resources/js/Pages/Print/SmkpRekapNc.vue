<script setup lang="ts">
/**
 * Keluaran 2 — Rekapitulasi Ketidaksesuaian.
 *
 * Dipakai rapat penutupan. Yang ditanyakan di sana bukan bunyi tiap
 * temuan melainkan SEBARANNYA — elemen mana yang paling banyak
 * bermasalah, dan berapa yang mayor. Karena itu tabel per elemen
 * mendahului daftar temuannya, bukan sebaliknya.
 */
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  audit: any;
  temuan: any[];
  perElemen: Array<Record<string, any>>;
  ringkas: Record<string, number>;
  meta?: any;
  dok?: any;
  kembali?: string;
}>();

const tanggal = (v: unknown) =>
  v ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
        .format(new Date(String(v)))
    : '—';
</script>

<template>
  <Head title="Rekapitulasi Ketidaksesuaian" />

  <PrintShell title="Rekapitulasi Ketidaksesuaian" :kembali="props.kembali">
    <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">

      <KopCetak :dok="props.dok" :halaman="1" :dari="1" />

      <h1 class="text-center font-bold text-[16px] uppercase border-b border-stone-200 pb-4 mb-4">
        Rekapitulasi Ketidaksesuaian
      </h1>

      <table class="w-full text-[11px] border border-stone-300 mb-5">
        <tbody>
          <tr>
            <td class="p-2 border-b border-r border-stone-200 w-36 text-stone-500">Perusahaan</td>
            <td class="p-2 border-b border-r border-stone-200 font-semibold">
              {{ props.audit?.company?.name || '—' }}
            </td>
            <td class="p-2 border-b border-r border-stone-200 w-28 text-stone-500">Tahun audit</td>
            <td class="p-2 border-b border-stone-200 font-semibold">{{ props.audit?.tahun }}</td>
          </tr>
          <tr>
            <td class="p-2 border-r border-stone-200 text-stone-500">Periode</td>
            <td class="p-2 border-r border-stone-200" colspan="3">
              {{ tanggal(props.audit?.tanggal_mulai) }} — {{ tanggal(props.audit?.tanggal_selesai) }}
            </td>
          </tr>
        </tbody>
      </table>

      <!-- ringkasan angka -->
      <div class="grid grid-cols-5 gap-2 mb-5 text-center">
        <div v-for="r in [
               ['Total', props.ringkas?.total],
               ['Mayor', props.ringkas?.mayor],
               ['Minor', props.ringkas?.minor],
               ['Observasi', props.ringkas?.obs],
               ['Masih terbuka', props.ringkas?.terbuka],
             ]" :key="r[0] as string"
             class="border border-stone-300 rounded p-2">
          <p class="text-[18px] font-bold leading-none">{{ r[1] ?? 0 }}</p>
          <p class="text-[9.5px] text-stone-500 mt-1">{{ r[0] }}</p>
        </div>
      </div>

      <!-- sebaran per elemen: yang dibaca lebih dulu di rapat penutupan -->
      <h3 class="font-bold text-[12px] mb-2">Sebaran per elemen</h3>
      <table class="w-full text-[10.5px] border border-stone-300 mb-5">
        <thead>
          <tr class="bg-stone-100 text-left">
            <th class="p-1.5 border-b border-stone-300">Elemen</th>
            <th class="p-1.5 border-b border-stone-300 w-16 text-right">Mayor</th>
            <th class="p-1.5 border-b border-stone-300 w-16 text-right">Minor</th>
            <th class="p-1.5 border-b border-stone-300 w-16 text-right">Obs</th>
            <th class="p-1.5 border-b border-stone-300 w-16 text-right">Total</th>
            <th class="p-1.5 border-b border-stone-300 w-20 text-right">Terbuka</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in props.perElemen" :key="e.kode" class="border-b border-stone-100">
            <td class="p-1.5">{{ e.kode }}. {{ e.nama }}</td>
            <td class="p-1.5 text-right font-semibold">{{ e.mayor || '—' }}</td>
            <td class="p-1.5 text-right">{{ e.minor || '—' }}</td>
            <td class="p-1.5 text-right">{{ e.obs || '—' }}</td>
            <td class="p-1.5 text-right font-bold">{{ e.total || '—' }}</td>
            <td class="p-1.5 text-right">{{ e.terbuka || '—' }}</td>
          </tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[12px] mb-2">Daftar ketidaksesuaian</h3>
      <table class="w-full text-[10.5px] border border-stone-300">
        <thead>
          <tr class="bg-stone-100 text-left">
            <th class="p-1.5 border-b border-stone-300 w-10">No</th>
            <th class="p-1.5 border-b border-stone-300 w-16">Kriteria</th>
            <th class="p-1.5 border-b border-stone-300 w-16">Jenis</th>
            <th class="p-1.5 border-b border-stone-300">Uraian</th>
            <th class="p-1.5 border-b border-stone-300 w-20">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in props.temuan" :key="t.id" class="border-b border-stone-100 align-top">
            <td class="p-1.5">{{ t.urut }}</td>
            <td class="p-1.5 font-semibold">{{ t.kode_kriteria }}</td>
            <td class="p-1.5 uppercase">{{ t.jenis }}</td>
            <td class="p-1.5">{{ t.uraian }}</td>
            <td class="p-1.5">{{ t.status }}</td>
          </tr>
          <tr v-if="!props.temuan?.length">
            <td colspan="5" class="p-8 text-center text-stone-400">
              Tidak ada ketidaksesuaian tercatat pada periode ini.
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </PrintShell>
</template>
