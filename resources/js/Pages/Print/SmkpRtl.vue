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
  /** Penanda tangan formulir: Nama Auditor dan Nama Auditi. */
  ttd?: Array<{ peran: string; nama: string }>;
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

      <!-- `table-fixed`: lebar kolom ditetapkan persen, bukan ditawar dari
           isi. Dengan tata letak otomatis, delapan kolom berlebar tetap
           menjumlah satu piksel lebih lebar daripada lembarnya — dan satu
           piksel itu cukup untuk memotong judul kolom terakhir di tepi
           kertas. Terukur: tabel 817px di dalam lembar 816px. -->
      <table class="w-full table-fixed text-[10.5px] border border-stone-300">
        <thead>
          <!-- Kolomnya mengikuti Formulir Rencana Tindak Lanjut acuan:
               Nomor Ketidaksesuaian dan AKAR PERMASALAHAN ikut tercetak.
               Tanpa akar permasalahan, lembar ini hanya memuat apa yang
               akan dikerjakan tanpa mengapa — dan tindakan koreksi yang
               tidak menjawab akar masalahnya adalah tindakan yang akan
               diulang pada audit berikutnya. -->
          <!-- `break-words` pada baris judul, bukan hanya pada isinya:
               "Ketidaksesuaian" satu kata sepanjang 15 aksara, dan pada
               kolom selebar 90px ia meluber menimpa judul kolom
               sebelahnya — terbaca "Nomor KetidakseKriteria". -->
          <tr class="bg-stone-100 text-left align-bottom break-words">
            <th class="p-1.5 border-b border-stone-300 w-[4%]">No</th>
            <th class="p-1.5 border-b border-stone-300 w-[13%]">Nomor Ketidaksesuaian</th>
            <th class="p-1.5 border-b border-stone-300 w-[7%]">Kriteria</th>
            <th class="p-1.5 border-b border-stone-300 w-[17%]">Deskripsi Ketidaksesuaian</th>
            <th class="p-1.5 border-b border-stone-300 w-[17%]">Akar Permasalahan</th>
            <th class="p-1.5 border-b border-stone-300 w-[17%]">Tindakan Koreksi</th>
            <th class="p-1.5 border-b border-stone-300 w-[11%]">Penanggung Jawab</th>
            <!-- Kolom terakhir Formulir acuan adalah Batas Waktu
                 Perbaikan; tidak ada kolom "Keadaan". Kolom kesembilan
                 membuat tabelnya lebih lebar daripada lembarnya, dan dua
                 kolom terakhir terpotong di tepi kertas. Keterlambatan
                 tetap terlihat: tenggat yang lewat ditebalkan. -->
            <th class="p-1.5 border-b border-stone-300 w-[13%]">Batas Waktu Perbaikan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in props.temuan" :key="t.id" class="border-b border-stone-100 align-top break-words">
            <td class="p-1.5">{{ t.urut }}</td>
            <td class="p-1.5 font-semibold">{{ t.kode_nc }}</td>
            <td class="p-1.5 font-semibold">{{ t.kode_kriteria }}</td>
            <td class="p-1.5">{{ t.uraian }}</td>
            <td class="p-1.5">
              <template v-if="t.akar_masalah">{{ t.akar_masalah }}</template>
              <span v-else class="block h-4 border-b border-dashed border-stone-300"></span>
            </td>
            <td class="p-1.5">
              <template v-if="t.tindakan">{{ t.tindakan }}</template>
              <span v-else class="block h-4 border-b border-dashed border-stone-300"></span>
            </td>
            <td class="p-1.5">{{ t.penanggung_jawab || '—' }}</td>
            <!-- Yang lewat tenggat ditebalkan dan diberi keterangannya;
                 warnanya sengaja tidak dipakai sebagai satu-satunya
                 penanda, sebab lembar ini sering dicetak hitam putih. -->
            <td class="p-1.5" :class="t.lewat ? 'font-bold' : ''">
              {{ tanggal(t.target_selesai) }}
              <span v-if="t.lewat" class="block">({{ sisa(t) }})</span>
            </td>
          </tr>
          <tr v-if="!props.temuan?.length">
            <td colspan="8" class="p-8 text-center text-stone-400">
              Tidak ada tindak lanjut tercatat pada periode ini.
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Dua baris penanda tangan, sesuai Formulir Rencana Tindak
           Lanjut acuan: Nama Auditor dan Nama Auditi, masing-masing
           dengan kolom tanda tangan dan tanggal.

           KTT TIDAK menandatangani formulir ini. Ia mengesahkan Rencana
           Audit dan mengetahui Laporan Audit; rencana tindak lanjut
           adalah kesepakatan antara auditor dan auditi atas perbaikan
           yang menjadi tanggung jawab auditi. Kolom "Disetujui oleh —
           Kepala Teknik Tambang" menuntut tanda tangan yang formulir ini
           memang tidak minta, dan formulir yang kolomnya kosong terbaca
           sebagai formulir yang belum lengkap. -->
      <table class="w-full text-[11px] mt-8">
        <tbody>
          <tr v-for="b in props.ttd || []" :key="b.peran">
            <td class="p-2 w-32 align-bottom">{{ b.peran }}</td>
            <td class="p-2 w-52 align-bottom font-semibold border-b border-stone-400">
              {{ b.nama || '&nbsp;' }}
            </td>
            <td class="p-2 w-28 align-bottom">Tanda Tangan</td>
            <td class="p-2 w-44 align-bottom border-b border-stone-400">&nbsp;</td>
            <td class="p-2 w-20 align-bottom">Tanggal</td>
            <td class="p-2 align-bottom border-b border-stone-400">&nbsp;</td>
          </tr>
        </tbody>
      </table>
    </section>
  </PrintShell>
</template>
