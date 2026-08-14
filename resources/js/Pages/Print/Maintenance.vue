<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok: any; dari: string; sampai: string;
  keandalan: Record<string, any>;
  pm: Record<string, any>;
  biaya: Record<string, any>;
  tunggakan: Record<string, any>;
  dasar: Record<string, number>;
  perAlat: Array<Record<string, any>>;
  orders: Array<Record<string, any>>;
  tindak: Array<Record<string, any>>;
  kembali?: string;
}>();

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const persen = (v: unknown) => `${angka(v, 1)}%`;
const rupiah = (v: unknown) => `Rp ${angka(v, 0)}`;
const jam = (v: unknown) => v === null || v === undefined ? '—' : `${angka(v, 1)} jam`;
const tanggal = (v: unknown) => v ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(String(v))) : '—';
const label = (v: string) => String(v || '').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
</script>

<template>
  <Head title="Laporan Keandalan dan Pemeliharaan Armada" />

  <PrintShell title="Laporan Keandalan dan Pemeliharaan Armada" :kembali="props.kembali">
    <div class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">

      <table class="w-full border-collapse text-[10px] mb-5">
        <tbody>
          <tr>
            <td class="border border-stone-300 p-2 w-[18%] text-center font-bold">{{ props.dok?.perusahaan || 'EQOHSEE' }}</td>
            <td class="border border-stone-300 p-2 text-center">
              <div class="text-[9px] tracking-wide text-stone-500">{{ props.dok?.jenis }}</div>
              <div class="font-bold">{{ props.dok?.judul }}</div>
            </td>
            <td class="border border-stone-300 p-0 w-[30%]">
              <table class="w-full border-collapse">
                <tbody>
                  <tr><td class="border-b border-r border-stone-300 p-1">No. Dokumen</td><td class="border-b border-stone-300 p-1 font-semibold">{{ props.dok?.nomor }}</td></tr>
                  <tr><td class="border-b border-r border-stone-300 p-1">Tgl Penerbitan</td><td class="border-b border-stone-300 p-1">{{ tanggal(props.dok?.terbit) }}</td></tr>
                  <tr><td class="border-b border-r border-stone-300 p-1">Tgl Persetujuan</td><td class="border-b border-stone-300 p-1">{{ tanggal(props.dok?.setuju) }}</td></tr>
                  <tr><td class="border-r border-stone-300 p-1">No. Revisi</td><td class="p-1">{{ props.dok?.revisi }}</td></tr>
                </tbody>
              </table>
            </td>
          </tr>
        </tbody>
      </table>

      <header class="text-center border-b border-stone-200 pb-4 mb-5">
        <h1 class="font-bold text-[16px] uppercase">Laporan Keandalan dan Pemeliharaan Armada</h1>
        <p class="text-[11px] text-stone-500 mt-1">Periode {{ tanggal(props.dari) }} — {{ tanggal(props.sampai) }}</p>
        <p class="text-[10px] text-stone-400 mt-1">{{ props.dok?.divisi }} · {{ props.dok?.departemen }}</p>
      </header>

      <!--
        Berbeda dari laporan Operasi dan Konservasi, laporan ini memuat
        perintah kerja yang belum diverifikasi. Mengeluarkannya justru
        membuat ketersediaan terlihat lebih baik daripada kenyataannya —
        alat yang rusak tetap rusak walau laporannya belum ditandatangani.
        Yang dilakukan adalah menyebutkan berapa banyak.
      -->
      <section class="mb-5 rounded border border-stone-300 p-3 text-[10px] print:rounded-none"
               :class="Number(props.dasar?.belumDiverifikasi) ? 'bg-amber-50' : ''">
        <b class="block mb-1">Dasar laporan</b>
        Disusun dari <b>{{ props.dasar?.order ?? 0 }}</b> perintah kerja atas
        <b>{{ props.dasar?.unit ?? 0 }}</b> alat terdaftar.
        <b>{{ props.dasar?.diverifikasi ?? 0 }}</b> penutupan telah diverifikasi.
        <template v-if="Number(props.dasar?.belumDiverifikasi)">
          <b>{{ props.dasar.belumDiverifikasi }}</b> selesai tetapi belum diverifikasi —
          jam dan biayanya tetap ikut dihitung di sini, sebab mengeluarkannya akan membuat
          ketersediaan terlihat lebih baik daripada kenyataannya.
        </template>
        <template v-if="Number(props.dasar?.masihTerbuka)">
          <b>{{ props.dasar.masihTerbuka }}</b> masih terbuka; waktu hentinya dihitung sampai tanggal cetak.
        </template>
      </section>

      <h3 class="font-bold text-[13px] mb-2">A. Ringkasan Keandalan</h3>
      <table class="w-full border-collapse text-[11px] mb-6">
        <tbody>
          <tr v-for="b in [
            { k: 'Ketersediaan armada', v: persen(props.keandalan.ketersediaan), s: `${angka(props.keandalan.jamJalan)} dari ${angka(props.keandalan.jamTersedia)} jam` },
            { k: 'MTBF', v: jam(props.keandalan.mtbf), s: 'Rata-rata jam jalan antara dua kegagalan' },
            { k: 'MTTR', v: jam(props.keandalan.mttr), s: 'Rata-rata lama pengerjaan perbaikan' },
            { k: 'Rata-rata waktu henti', v: jam(props.keandalan.waktuHentiRata), s: 'Termasuk menunggu suku cadang dan montir' },
            { k: 'Jam menunggu', v: jam(props.keandalan.jamMenunggu), s: `${persen(props.keandalan.porsiMenunggu)} dari seluruh waktu henti` },
            { k: 'Jumlah kegagalan', v: String(props.keandalan.kegagalan), s: 'Korektif dan darurat; perawatan berkala tidak dihitung' },
            { k: 'Kepatuhan PM', v: persen(props.pm.persen), s: `${props.pm.terlambat} dari ${props.pm.berjadwal} alat terlewat jadwal` },
          ]" :key="b.k" class="border-b border-stone-200">
            <td class="p-2 w-[32%]">{{ b.k }}</td>
            <td class="p-2 font-semibold w-[18%]">{{ b.v }}</td>
            <td class="p-2 text-stone-500">{{ b.s }}</td>
          </tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">B. Biaya Pemeliharaan</h3>
      <table class="w-full border-collapse text-[11px] mb-6">
        <tbody>
          <tr v-for="b in [
            { k: 'Total biaya', v: rupiah(props.biaya.total), s: `Termasuk suku cadang ${rupiah(props.biaya.sukuCadang)}` },
            { k: 'Per jam jalan', v: rupiah(props.biaya.perJam), s: '' },
            { k: 'Per ton produksi', v: rupiah(props.biaya.perTon), s: `${angka(props.biaya.tonDasar)} ton produksi disetujui` },
          ]" :key="b.k" class="border-b border-stone-200">
            <td class="p-2 w-[32%]">{{ b.k }}</td>
            <td class="p-2 font-semibold w-[18%]">{{ b.v }}</td>
            <td class="p-2 text-stone-500">{{ b.s }}</td>
          </tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">C. Keandalan per Alat</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead>
          <tr class="border-b border-stone-300 text-left text-stone-500">
            <th class="p-1.5">Alat</th><th class="p-1.5">Kritikalitas</th>
            <th class="p-1.5 text-right">WO</th><th class="p-1.5 text-right">Kegagalan</th>
            <th class="p-1.5 text-right">Jam henti</th><th class="p-1.5 text-right">Menunggu</th>
            <th class="p-1.5 text-right">Biaya</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in props.perAlat" :key="a.kode" class="border-b border-stone-100">
            <td class="p-1.5"><b>{{ a.kode }}</b><span class="block text-stone-500">{{ a.nama }}</span></td>
            <td class="p-1.5">{{ a.kritikalitas || '—' }}</td>
            <td class="p-1.5 text-right">{{ a.order }}</td>
            <td class="p-1.5 text-right">{{ a.kegagalan }}</td>
            <td class="p-1.5 text-right font-semibold">{{ angka(a.jamHenti, 1) }}</td>
            <td class="p-1.5 text-right">{{ angka(a.jamMenunggu, 1) }}</td>
            <td class="p-1.5 text-right">{{ rupiah(a.biaya) }}</td>
          </tr>
          <tr v-if="!props.perAlat.length"><td colspan="7" class="p-4 text-center text-stone-400">Tidak ada perintah kerja yang tertaut ke alat.</td></tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">D. Rincian Perintah Kerja</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead>
          <tr class="border-b border-stone-300 text-left text-stone-500">
            <th class="p-1.5">Alat / Gejala</th><th class="p-1.5">Jenis</th>
            <th class="p-1.5">Dilaporkan</th><th class="p-1.5 text-right">Henti</th>
            <th class="p-1.5 text-right">Kerja</th><th class="p-1.5">Status</th><th class="p-1.5">Diverifikasi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="w in props.orders" :key="w.id" class="border-b border-stone-100 align-top">
            <td class="p-1.5"><b>{{ w.objek || '—' }}</b><span class="block text-stone-500">{{ w.gejala }}</span></td>
            <td class="p-1.5">{{ label(w.jenis) }}</td>
            <td class="p-1.5">{{ w.dilaporkanPada }}</td>
            <td class="p-1.5 text-right">{{ angka(w.jamHenti, 1) }}</td>
            <td class="p-1.5 text-right">{{ angka(w.jamPerbaikan, 1) }}</td>
            <td class="p-1.5">{{ label(w.status) }}</td>
            <td class="p-1.5 text-stone-500">{{ w.pemverifikasi || '—' }}</td>
          </tr>
          <tr v-if="!props.orders.length"><td colspan="7" class="p-4 text-center text-stone-400">Tidak ada perintah kerja pada periode ini.</td></tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">E. Tindak Lanjut Terbuka</h3>
      <table class="w-full border-collapse text-[10px] mb-8">
        <thead>
          <tr class="border-b border-stone-300 text-left text-stone-500">
            <th class="p-1.5">Tindakan</th><th class="p-1.5">Penanggung Jawab</th>
            <th class="p-1.5">Target Selesai</th><th class="p-1.5">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in props.tindak" :key="t.id" class="border-b border-stone-100 align-top">
            <td class="p-1.5"><b>{{ t.judul }}</b><span v-if="t.uraian" class="block text-stone-500">{{ t.uraian }}</span></td>
            <td class="p-1.5">{{ t.penanggung_jawab || '—' }}</td>
            <td class="p-1.5">{{ t.target_selesai || '—' }}</td>
            <td class="p-1.5" :class="t.terlambat ? 'font-bold' : ''">{{ t.statusLabel }}</td>
          </tr>
          <tr v-if="!props.tindak.length"><td colspan="4" class="p-4 text-center text-stone-400">Tidak ada tindak lanjut terbuka.</td></tr>
        </tbody>
      </table>

      <section class="pt-6 grid gap-8 sm:grid-cols-2 text-center text-[11px]">
        <div>Disusun oleh<div class="h-16"></div><b class="block border-t border-stone-400 pt-1">&nbsp;</b><span class="text-[10px] text-stone-500">Pengawas Pemeliharaan</span></div>
        <div>Disetujui oleh<div class="h-16"></div><b class="block border-t border-stone-400 pt-1">&nbsp;</b><span class="text-[10px] text-stone-500">Kepala Teknik Tambang</span></div>
      </section>
    </div>
  </PrintShell>
</template>
