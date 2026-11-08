<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import CetakLayout from '../../../Components/Smkp/CetakLayout.vue';
import Lembar from '../../../Components/Smkp/Lembar.vue';
import FormIdentitas from '../../../Components/Smkp/FormIdentitas.vue';
import FormTtd from '../../../Components/Smkp/FormTtd.vue';
import { angka } from '../../../smkp/lib/format';
import { bagiLembar, jumlahBaris } from '../../../smkp/lib/halaman';
import type { KopDok, RingkasAudit, TtdForm } from '../../../smkp/types';
import BlankLayout from '../../../Layouts/BlankLayout.vue';

/* Lembar cetak di luar kerangka aplikasi (konvensi EQOHSEE: Pages/Print + BlankLayout). */
defineOptions({ layout: BlankLayout });

interface Rinci { kode: string; nama: string; maks: number; nilai: number | string | null; na: boolean }
interface Sub { kode: string; nama: string; maks: number; nilai: number; dinilai: number; na: boolean; rinci: Rinci[] }
interface Elemen { kode: string; nama: string; bobot: number; maks: number; nilai: number; skor: number; na: boolean; sub: Sub[] }

const props = defineProps<{
  audit: RingkasAudit; dok: KopDok; ttd: TtdForm; kembali: string;
  elemen: Elemen[];
  total: { bobot: number; maks: number; nilai: number; skor: number };
  tingkat: { label: string; warna: string };
}>();

type Baris =
  | { jenis: 'elemen'; e: Elemen }
  | { jenis: 'lanjut'; e: Elemen }
  | { jenis: 'sub'; e: Elemen; s: Sub }
  | { jenis: 'rinci'; e: Elemen; r: Rinci };

/*
 * Matriks Formulir Kriteria Audit sesuai Kepdirjen 185.K/2019 hal. 605–608:
 * tiga kolom kode bertingkat (elemen, sub elemen, sub-sub elemen), Nilai
 * Maksimal Kriteria, dan Nilai Audit. Baris diratakan lalu dibagi per lembar
 * A4 menurut perkiraan tingginya (baris tunggal 23 px, tiap lipatan 14 px;
 * lebar nama ±50/56/48 karakter pada huruf 10 px untuk elemen/sub/sub-sub).
 * Elemen yang berlanjut ke lembar berikutnya mendapat baris "(lanjutan)".
 */
const BARIS = 23, LIPAT = 14;
const tinggi = (b: Baris) => {
  const [teks, lebar] = b.jenis === 'sub' ? [b.s.nama, 56] : b.jenis === 'rinci' ? [b.r.nama, 48] : [b.e.nama, 50];
  return BARIS + LIPAT * (jumlahBaris(teks, lebar) - 1);
};
// Ruang baris: A4 1123 − bantalan bawah 60 − kop & judul (270; lembar pertama 369 dengan identitas)
// − kepala tabel 176; lembar lanjutan menyisakan satu baris untuk judul elemen yang berlanjut.
const ruang = (i: number) => (i === 0 ? 515 : 615 - BARIS);
// Ekor lembar terakhir: baris TOTAL, keterangan perhitungan, dan tanda tangan.
const EKOR = BARIS + 200;

const lembar = computed(() => {
  const datar: Baris[] = [];
  for (const e of props.elemen) {
    datar.push({ jenis: 'elemen', e });
    for (const s of e.sub) {
      datar.push({ jenis: 'sub', e, s });
      for (const r of s.rinci) datar.push({ jenis: 'rinci', e, r });
    }
  }
  const hal = bagiLembar(datar, tinggi, ruang, EKOR);
  // Bila hanya baris TOTAL yang terdorong ke lembar baru, beberapa baris terakhir ikut pindah
  // agar lembar penutup tidak berisi TOTAL saja.
  if (hal.length > 1 && !hal[hal.length - 1].length) {
    const sebelum = hal[hal.length - 2];
    hal[hal.length - 1] = sebelum.splice(Math.max(1, sebelum.length - 3));
  }
  return hal.map((isi) =>
    isi.length && isi[0].jenis !== 'elemen' ? [{ jenis: 'lanjut', e: isi[0].e } as Baris, ...isi] : isi);
});

const n = (v: number | string | null | undefined) => (v === null || v === undefined || v === '' ? '' : v === 'N/A' ? 'N/A' : angka(Number(v), Number(v) % 1 ? 1 : 0));
// Nilai audit sub elemen berincian = jumlah sub-sub elemennya; kosong bila belum satu pun dinilai.
const nilaiSub = (s: Sub) => (s.na ? 'N/A' : s.dinilai ? n(s.nilai) : '');
const KEPALA_MAKS = ['Persentase', 'Nilai Sub Elemen', 'Nilai Sub-sub Elemen'];
const KEPALA_AUDIT = ['Nilai Sub Elemen', 'Nilai Sub-Sub Elemen', 'Total Nilai Elemen', 'Persentase Nilai Elemen'];
const RUMUS = [
  ['Total Nilai Elemen', 'Nilai Sub Elemen + Nilai Sub-Sub Elemen'],
  ['Persentase Nilai Elemen (%)', '(Total Nilai Elemen : Total Nilai Elemen Maksimal) × Persentase Nilai Maksimal'],
  ['Total Nilai Penerapan SMKP', 'Penjumlahan Nilai Elemen-Elemen'],
  ['Total Persentase', 'Penjumlahan Persentase Nilai Elemen-Elemen'],
];
</script>

<template>
  <Head title="Formulir Kriteria Audit SMKP" />
  <CetakLayout judul="Formulir Kriteria Audit SMKP Minerba" :kembali="kembali">
    <Lembar v-for="(h, i) in lembar" :key="i" :dok="dok" :halaman="i + 1" :dari="lembar.length">
      <p class="form-judul">Formulir Kriteria Audit SMKP Minerba</p>
      <FormIdentitas v-if="i === 0" :baris="[['Nama Perusahaan', audit.perusahaan.nama], ['Tanggal Audit', audit.periode]]" />
      <table class="tabel-form tabel-kriteria table-fixed text-[10px] leading-snug">
        <colgroup>
          <col class="w-[5%]"><col class="w-[6.5%]"><col class="w-[8.5%]"><col>
          <col class="w-[5.5%]"><col v-for="k in 4" :key="k" class="w-[5%]"><col class="w-[6.5%]"><col class="w-[7%]">
        </colgroup>
        <thead>
          <tr>
            <th rowspan="2" colspan="4">KRITERIA</th>
            <th colspan="3">Nilai Maksimal Kriteria</th>
            <th colspan="4">Nilai Audit</th>
          </tr>
          <tr class="h-[118px]">
            <th v-for="k in [...KEPALA_MAKS, ...KEPALA_AUDIT]" :key="k" class="!p-1"><span class="tegak">{{ k }}</span></th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(b, j) in h" :key="j">
            <tr v-if="b.jenis === 'elemen'" class="bg-slate-100 font-bold">
              <td class="text-center">{{ b.e.kode }}.</td><td colspan="3" class="uppercase">{{ b.e.nama }}</td>
              <td class="text-center tabular-nums">{{ b.e.bobot }}%</td><td class="text-center tabular-nums">{{ b.e.na ? 'N/A' : b.e.maks }}</td><td />
              <td /><td /><td class="text-center tabular-nums">{{ b.e.na ? 'N/A' : n(b.e.nilai) }}</td>
              <td class="text-center tabular-nums">{{ b.e.na ? 'N/A' : `${angka(b.e.skor, 2)}%` }}</td>
            </tr>
            <tr v-else-if="b.jenis === 'lanjut'" class="bg-slate-100 font-bold">
              <td class="text-center">{{ b.e.kode }}.</td>
              <td colspan="10"><span class="uppercase">{{ b.e.nama }}</span> <span class="font-normal italic text-slate-600">(lanjutan)</span></td>
            </tr>
            <tr v-else-if="b.jenis === 'sub'">
              <td class="sambung" /><td class="text-center tabular-nums">{{ b.s.kode }}</td><td colspan="2">{{ b.s.nama }}</td>
              <td /><td class="text-center tabular-nums">{{ b.s.na ? 'N/A' : b.s.maks }}</td><td />
              <td class="text-center tabular-nums">{{ nilaiSub(b.s) }}</td><td /><td /><td />
            </tr>
            <tr v-else>
              <td class="sambung" /><td class="sambung" /><td class="text-center tabular-nums">{{ b.r.kode }}</td><td>{{ b.r.nama }}</td>
              <td /><td /><td class="text-center tabular-nums">{{ b.r.na ? 'N/A' : b.r.maks }}</td>
              <td /><td class="text-center tabular-nums">{{ n(b.r.nilai) }}</td><td /><td />
            </tr>
          </template>
          <tr v-if="i === lembar.length - 1" class="bg-slate-100 font-bold">
            <td colspan="4" class="text-center">TOTAL</td>
            <td class="text-center tabular-nums">{{ total.bobot }}%</td><td class="text-center tabular-nums">{{ total.maks }}</td><td />
            <td /><td /><td class="text-center tabular-nums">{{ n(total.nilai) }}</td><td class="text-center tabular-nums">{{ angka(total.skor, 2) }}%</td>
          </tr>
        </tbody>
      </table>
      <template v-if="i === lembar.length - 1">
        <div class="mt-3 text-[10.5px] leading-snug text-slate-700">
          <p>Keterangan:</p>
          <p class="font-bold text-slate-900 mt-0.5">Perhitungan Nilai Audit</p>
          <ol class="mt-0.5">
            <li v-for="(r, k) in RUMUS" :key="k" class="grid grid-cols-[14px_190px_10px_1fr]"><span>{{ k + 1 }}.</span><span>{{ r[0] }}</span><span>=</span><span>{{ r[1] }}</span></li>
          </ol>
          <p class="mt-1 text-slate-600">N/A = kriteria tidak berlaku bagi perusahaan; tidak dinilai dan tidak dihitung dalam Total Nilai Elemen Maksimal. Nilai maksimal kriteria mengikuti Daftar Koreksi dan Klarifikasi Lampiran II Kepdirjen 185.K/37.04/DJB/2019.</p>
        </div>
        <FormTtd :pihak="[{ peran: 'Ketua Tim Audit', kunci: 'ketua', nama: ttd.ketua }]" :ttd="ttd" bagian="penilaian" />
      </template>
    </Lembar>
  </CetakLayout>
</template>

<style scoped>
/* Kepala kolom nilai ditulis tegak seperti matriks Kepdirjen. */
.tabel-kriteria td.num { padding-left: 2px; padding-right: 2px; white-space: nowrap; overflow-wrap: normal; }
.tegak { writing-mode: vertical-rl; transform: rotate(180deg); display: inline-block; white-space: nowrap; }
/* Sel kode induk pada baris anak tampak menyatu (seperti sel yang digabung pada matriks acuan). */
.tabel-kriteria td.sambung { border-top-color: transparent; border-bottom-color: transparent; }
</style>
