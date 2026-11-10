<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import CetakLayout from '../../../Components/Smkp/CetakLayout.vue';
import LembarBaris from '../../../Components/Smkp/LembarBaris.vue';
import FormIdentitas from '../../../Components/Smkp/FormIdentitas.vue';
import FormTtd from '../../../Components/Smkp/FormTtd.vue';
import { potongTeks } from '../../../smkp/lib/halaman';
import type { KopDok, RingkasAudit, TtdForm } from '../../../smkp/types';
import BlankLayout from '../../../Layouts/BlankLayout.vue';

/* Lembar cetak di luar kerangka aplikasi (konvensi EQOHSEE: Pages/Print + BlankLayout). */
defineOptions({ layout: BlankLayout });

interface Temuan { id: number; nomor: string; pernyataan: string; kategori: string; labelKategori: string; batas: string | null }
const props = defineProps<{ audit: RingkasAudit; dok: KopDok; ttd: TtdForm; kembali: string; temuan: Temuan[] }>();

/*
 * Baris dibagi per lembar A4 dari tinggi yang diukur di peramban (LembarBaris):
 * tabel terisi sampai batas bawah lembar, jumlah temuan & tanda tangan di
 * lembar terakhir. Deskripsi yang lebih tinggi daripada satu lembar (±50
 * karakter per baris) dipotong dan berlanjut ke lembar berikutnya. Minimal
 * sepuluh baris seperti formulir asli supaya formulir kosong tetap layak cetak.
 */
type Baris = { no: number; t: Temuan | null; teks: string; lanjut: boolean };
const baris = computed<Baris[]>(() => {
  // Deskripsi sudah ditutup "Hal ini tidak sesuai dengan Sub Elemen …, Sub-sub Elemen …" (server).
  const isi: Baris[] = props.temuan.flatMap((t, i) => potongTeks(t.pernyataan, 50, 30)
    .map((teks, j) => ({ no: i + 1, t, teks, lanjut: j > 0 })));
  while (isi.length < 10) isi.push({ no: 0, t: null, teks: '', lanjut: false });
  return isi;
});
const jumlah = (k: string) => props.temuan.filter((t) => t.kategori === k).length;
</script>

<template>
  <Head title="Rekapitulasi Ketidaksesuaian" />
  <CetakLayout judul="Formulir Rekapitulasi Ketidaksesuaian" :kembali="kembali">
    <LembarBaris v-slot="{ baris: h, pertama, akhir }" :dok="dok" :baris="baris">
      <div v-if="pertama" data-hanya-awal class="flow-root">
        <p class="form-judul">Formulir Rekapitulasi Ketidaksesuaian</p>
        <FormIdentitas :baris="[['Nama Perusahaan', audit.perusahaan.nama], ['Tanggal Audit', audit.periode]]" />
      </div>
      <table class="tabel-form">
        <thead><tr><th class="w-8">No</th><th class="w-[18%]">Nomor Ketidaksesuaian</th><th>Deskripsi Ketidaksesuaian</th><th class="w-[14%]">Kategori</th><th class="w-[17%]">Batas Waktu Perbaikan</th></tr></thead>
        <tbody data-baris>
          <tr v-for="({ no, t, teks, lanjut }, j) in h" :key="j" class="h-7">
            <td class="text-center tabular-nums">{{ t && !lanjut ? no : '' }}</td>
            <td class="tabular-nums whitespace-nowrap">{{ t?.nomor }}<span v-if="t && lanjut" class="block italic text-slate-500">(lanjutan)</span></td>
            <td class="whitespace-pre-line text-justify">{{ teks }}</td>
            <td class="text-center">{{ lanjut ? '' : t?.labelKategori }}</td>
            <td class="text-center">{{ lanjut || !t ? '' : t.batas || '—' }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="akhir" data-penutup class="flow-root">
        <table class="mt-5 border-separate border-spacing-y-2">
          <tbody>
            <tr v-for="k in [['kritikal', 'Jumlah Temuan Kritikal'], ['mayor', 'Jumlah Temuan Mayor'], ['minor', 'Jumlah Temuan Minor']]" :key="k[0]">
              <td class="w-56 font-bold text-slate-900">{{ k[1] }}</td>
              <td><div class="kotak-isi w-16 text-center font-bold tabular-nums">{{ jumlah(k[0]) }}</div></td>
            </tr>
          </tbody>
        </table>
        <FormTtd :pihak="[{ peran: 'Nama Lead Auditor', kunci: 'ketua', nama: ttd.ketua }]" :ttd="ttd" bagian="rekapitulasi" />
      </div>
    </LembarBaris>
  </CetakLayout>
</template>
