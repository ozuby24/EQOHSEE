<script setup lang="ts">
/**
 * Keluaran 4 — Rencana Tindak Lanjut.
 *
 * DIURUTKAN MENURUT TENGGATNYA, bukan menurut beratnya. Yang dipakai
 * memantau bukan mana yang paling berat melainkan mana yang paling
 * dekat jatuh tempo — sebuah observasi yang tenggatnya lusa lebih
 * mendesak daripada mayor yang tenggatnya tiga bulan lagi.
 *
 * Yang sudah lewat tenggat ditandai, dan yang belum punya tenggat sama
 * sekali ditaruh paling bawah dengan tandanya sendiri. Baris tanpa
 * tenggat bukan baris yang santai — ia baris yang tidak pernah muncul
 * di daftar mana pun, dan karena itu tidak pernah ditagih.
 */
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  audit: any;
  temuan: any[];
  ringkas: Record<string, number>;
  meta?: any;
  dok?: any;
  kembali?: string;
}>();

const tanggal = (v: unknown) =>
  v ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
        .format(new Date(String(v)))
    : '—';

/** Kalimat, bukan angka bertanda: "-14" menuntut pembacanya menafsirkan sendiri. */
function sisa(t: Record<string, any>): string {
  if (t.status === 'closed')     return 'selesai';
  if (t.sisaHari === null)       return 'tanpa tenggat';
  if (t.sisaHari < 0)            return `lewat ${Math.abs(t.sisaHari)} hari`;
  if (t.sisaHari === 0)          return 'jatuh tempo hari ini';

  return `${t.sisaHari} hari lagi`;
}
</script>

<template>
  <Head title="Rencana Tindak Lanjut" />

  <PrintShell title="Rencana Tindak Lanjut" :kembali="props.kembali">
    <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">

      <KopCetak :dok="props.dok" :halaman="1" :dari="1" />

      <h1 class="text-center font-bold text-[16px] uppercase border-b border-stone-200 pb-4 mb-4">
        Rencana Tindak Lanjut Audit SMKP
      </h1>

      <table class="w-full text-[11px] border border-stone-300 mb-4">
        <tbody>
          <tr>
            <td class="p-2 border-b border-r border-stone-200 w-36 text-stone-500">Perusahaan</td>
            <td class="p-2 border-b border-r border-stone-200 font-semibold">
              {{ props.audit?.company?.name || '—' }}
            </td>
            <td class="p-2 border-b border-r border-stone-200 w-28 text-stone-500">Tahun audit</td>
            <td class="p-2 border-b border-stone-200 font-semibold">{{ props.audit?.tahun }}</td>
          </tr>
        </tbody>
      </table>

      <div class="grid grid-cols-4 gap-2 mb-5 text-center">
        <div v-for="r in [
               ['Total tindakan', props.ringkas?.total],
               ['Selesai', props.ringkas?.tertutup],
               ['Lewat tenggat', props.ringkas?.lewat],
               ['Tanpa tenggat', props.ringkas?.tanpaTarget],
             ]" :key="r[0] as string"
             class="border border-stone-300 rounded p-2">
          <p class="text-[18px] font-bold leading-none">{{ r[1] ?? 0 }}</p>
          <p class="text-[9.5px] text-stone-500 mt-1">{{ r[0] }}</p>
        </div>
      </div>

      <table class="w-full text-[10.5px] border border-stone-300">
        <thead>
          <tr class="bg-stone-100 text-left">
            <th class="p-1.5 border-b border-stone-300 w-8">No</th>
            <th class="p-1.5 border-b border-stone-300 w-14">Kriteria</th>
            <th class="p-1.5 border-b border-stone-300">Ketidaksesuaian</th>
            <th class="p-1.5 border-b border-stone-300">Tindakan perbaikan</th>
            <th class="p-1.5 border-b border-stone-300 w-24">Penanggung jawab</th>
            <th class="p-1.5 border-b border-stone-300 w-20">Tenggat</th>
            <th class="p-1.5 border-b border-stone-300 w-24">Keadaan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in props.temuan" :key="t.id" class="border-b border-stone-100 align-top">
            <td class="p-1.5">{{ t.urut }}</td>
            <td class="p-1.5 font-semibold">{{ t.kode_kriteria }}</td>
            <td class="p-1.5">{{ t.uraian }}</td>
            <td class="p-1.5">
              <template v-if="t.tindakan">{{ t.tindakan }}</template>
              <span v-else class="block h-4 border-b border-dashed border-stone-300"></span>
            </td>
            <td class="p-1.5">{{ t.penanggung_jawab || '—' }}</td>
            <td class="p-1.5">{{ tanggal(t.target_selesai) }}</td>
            <!-- Yang lewat tenggat ditebalkan; warnanya sengaja tidak
                 dipakai sebagai satu-satunya penanda, sebab lembar ini
                 sering dicetak hitam putih. -->
            <td class="p-1.5" :class="t.lewat ? 'font-bold' : ''">
              {{ sisa(t) }}
            </td>
          </tr>
          <tr v-if="!props.temuan?.length">
            <td colspan="7" class="p-8 text-center text-stone-400">
              Tidak ada tindak lanjut tercatat pada periode ini.
            </td>
          </tr>
        </tbody>
      </table>

      <section class="grid grid-cols-2 gap-8 text-[11px] mt-8">
        <div class="text-center">
          <p class="mb-14">Disusun oleh — Ketua tim auditor,</p>
          <p class="font-bold border-t border-stone-400 pt-1">
            {{ props.audit?.ketua_auditor || '………………………………' }}
          </p>
        </div>
        <div class="text-center">
          <p class="mb-14">Disetujui oleh — Kepala Teknik Tambang,</p>
          <p class="border-t border-stone-400 pt-1">………………………………</p>
        </div>
      </section>
    </section>
  </PrintShell>
</template>
