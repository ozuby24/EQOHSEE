<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok: any; dari: string; sampai: string;
  ringkas: Record<string, number>;
  kelengkapan: Record<string, any>;
  perPit: Array<Record<string, any>>;
  records: Array<Record<string, any>>;
  tindak: Array<Record<string, any>>;
  kembali?: string;
}>();

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const persen = (v: unknown) => `${angka(v, 1)}%`;
const tanggal = (v: unknown) => v ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(String(v))) : '—';
const label = (v: string) => String(v || '').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
</script>

<template>
  <Head title="Laporan Kinerja Operasi Penambangan" />

  <PrintShell title="Laporan Kinerja Operasi Penambangan" :kembali="props.kembali">
    <div class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">

      <!-- Kop dokumen terkendali, bentuknya sama dengan berkas audit lain. -->
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
        <h1 class="font-bold text-[16px] uppercase">Laporan Kinerja Operasi Penambangan</h1>
        <p class="text-[11px] text-stone-500 mt-1">Periode {{ tanggal(props.dari) }} — {{ tanggal(props.sampai) }}</p>
        <p class="text-[10px] text-stone-400 mt-1">{{ props.dok?.divisi }} · {{ props.dok?.departemen }}</p>
      </header>

      <!--
        Kelengkapan dicetak, bukan disembunyikan. Laporan yang memuat tujuh
        belas dari tiga puluh shift tetap sah dibaca asalkan pembacanya tahu;
        yang tidak menyebutkannya membuat pembacanya mengira sudah melihat
        seluruh periode.
      -->
      <section class="mb-5 rounded border border-stone-300 p-3 text-[10px] print:rounded-none"
               :class="Number(props.kelengkapan?.persen) < 90 ? 'bg-amber-50' : ''">
        <b class="block mb-1">Dasar laporan</b>
        Angka pada laporan ini disusun dari
        <b>{{ props.kelengkapan?.shiftDisetujui ?? 0 }}</b> laporan shift yang telah ditinjau dan disetujui,
        dari <b>{{ props.kelengkapan?.shiftWajib ?? 0 }}</b> shift pada periode ini
        (<b>{{ persen(props.kelengkapan?.persen) }}</b>).
        <template v-if="Number(props.kelengkapan?.belumDilaporkan)">
          {{ props.kelengkapan.belumDilaporkan }} shift belum dilaporkan.
        </template>
        <template v-if="Number(props.kelengkapan?.menungguTinjauan)">
          {{ props.kelengkapan.menungguTinjauan }} shift masih menunggu tinjauan dan tidak ikut dihitung.
        </template>
      </section>

      <!-- Ringkasan -->
      <h3 class="font-bold text-[13px] mb-2">A. Ringkasan Kinerja</h3>
      <table class="w-full border-collapse text-[11px] mb-6">
        <tbody>
          <tr v-for="baris in [
            { k: 'Produksi', v: `${angka(props.ringkas.produksi)} ton`, t: `${angka(props.ringkas.target_produksi)} ton`, c: persen(props.ringkas.capaian_produksi) },
            { k: 'Pemindahan overburden', v: `${angka(props.ringkas.ob)} BCM`, t: `${angka(props.ringkas.target_ob)} BCM`, c: persen(props.ringkas.capaian_ob) },
            { k: 'Strip ratio', v: angka(props.ringkas.strip_ratio, 2), t: '—', c: '—' },
            { k: 'Jam operasi', v: `${angka(props.ringkas.jam_operasi, 1)} jam`, t: '—', c: '—' },
            { k: 'Jam delay', v: `${angka(props.ringkas.jam_delay, 1)} jam`, t: '—', c: '—' },
            { k: 'Efisiensi waktu', v: persen(props.ringkas.efisiensi_waktu), t: '—', c: '—' },
          ]" :key="baris.k" class="border-b border-stone-200">
            <td class="p-2 w-[40%]">{{ baris.k }}</td>
            <td class="p-2 font-semibold">{{ baris.v }}</td>
            <td class="p-2 text-stone-500">Target {{ baris.t }}</td>
            <td class="p-2 text-right font-semibold">{{ baris.c }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Per pit -->
      <h3 class="font-bold text-[13px] mb-2">B. Kinerja per Pit / Area</h3>
      <table class="w-full border-collapse text-[11px] mb-6">
        <thead>
          <tr class="border-b border-stone-300 text-left text-stone-500">
            <th class="p-2">Pit / Area</th><th class="p-2 text-right">Produksi (ton)</th>
            <th class="p-2 text-right">OB (BCM)</th><th class="p-2 text-right">Strip Ratio</th><th class="p-2 text-right">Shift</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in props.perPit" :key="p.nama" class="border-b border-stone-100">
            <td class="p-2 font-semibold">{{ p.nama }}</td>
            <td class="p-2 text-right">{{ angka(p.produksi) }}</td>
            <td class="p-2 text-right">{{ angka(p.ob) }}</td>
            <td class="p-2 text-right">{{ angka(p.strip_ratio, 2) }}</td>
            <td class="p-2 text-right">{{ p.shift }}</td>
          </tr>
          <tr v-if="!props.perPit.length"><td colspan="5" class="p-4 text-center text-stone-400">Tidak ada data disetujui pada periode ini.</td></tr>
        </tbody>
      </table>

      <!-- Rincian shift -->
      <h3 class="font-bold text-[13px] mb-2">C. Rincian Laporan Shift</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead>
          <tr class="border-b border-stone-300 text-left text-stone-500">
            <th class="p-1.5">Tanggal</th><th class="p-1.5">Shift</th><th class="p-1.5">Pit</th><th class="p-1.5">Material</th>
            <th class="p-1.5 text-right">Ton</th><th class="p-1.5 text-right">BCM</th>
            <th class="p-1.5 text-right">Operasi</th><th class="p-1.5 text-right">Delay</th><th class="p-1.5">Ditinjau</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in props.records" :key="i" class="border-b border-stone-100">
            <td class="p-1.5">{{ r.tanggal }}</td>
            <td class="p-1.5">{{ label(r.shift) }}</td>
            <td class="p-1.5">{{ r.pit }}</td>
            <td class="p-1.5">{{ r.material }}</td>
            <td class="p-1.5 text-right">{{ angka(r.produksi_ton) }}</td>
            <td class="p-1.5 text-right">{{ angka(r.overburden_bcm) }}</td>
            <td class="p-1.5 text-right">{{ angka(r.jam_operasi, 1) }}</td>
            <td class="p-1.5 text-right">{{ angka(r.jam_delay, 1) }}</td>
            <td class="p-1.5 text-stone-500">{{ r.peninjau || '—' }}<span v-if="r.ditinjauPada" class="block text-[9px]">{{ r.ditinjauPada }}</span></td>
          </tr>
          <tr v-if="!props.records.length"><td colspan="9" class="p-4 text-center text-stone-400">Tidak ada laporan shift yang disetujui.</td></tr>
        </tbody>
      </table>

      <!-- Tindak lanjut -->
      <h3 class="font-bold text-[13px] mb-2">D. Tindak Lanjut Terbuka</h3>
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
        <div>Disusun oleh<div class="h-16"></div><b class="block border-t border-stone-400 pt-1">&nbsp;</b><span class="text-[10px] text-stone-500">Pengawas Operasional</span></div>
        <div>Disetujui oleh<div class="h-16"></div><b class="block border-t border-stone-400 pt-1">&nbsp;</b><span class="text-[10px] text-stone-500">Kepala Teknik Tambang</span></div>
      </section>
    </div>
  </PrintShell>
</template>
