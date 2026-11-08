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

interface Temuan { id: number; nomor: string; uraian: string; deskripsi: string; akar: string; tindakan: string; pj: string; batas: string | null }
const props = defineProps<{ audit: RingkasAudit; dok: KopDok; ttd: TtdForm; kembali: string; temuan: Temuan[] }>();

/*
 * Baris dibagi per lembar lanskap dari tinggi yang diukur di peramban
 * (LembarBaris), bukan jumlah tetap per lembar: akar masalah dan tindakan
 * koreksi bisa sangat panjang. Isian yang lebih tinggi daripada ±18 baris
 * (deskripsi ±30, akar ±33, tindakan ±40 karakter per baris) dipecah dan
 * berlanjut ke baris "(lanjutan)" — satu baris tabel tidak pernah melebihi
 * satu lembar; potongan yang ternyata berdampingan di satu lembar digabung
 * kembali. Minimal tujuh baris supaya formulir kosong tetap layak cetak.
 */
const MAKS = 18;
type Rentang = { awal: number; akhir: number } | null;
type Baris = { no: number; t: Temuan | null; desk: Rentang; akar: Rentang; tindakan: Rentang; lanjut: boolean };
const KOLOM = ['desk', 'akar', 'tindakan'] as const;
const sumber = (t: Temuan, k: typeof KOLOM[number]) => (k === 'desk' ? t.deskripsi || t.uraian : t[k]) ?? '';

/** Potongan teks beserta rentangnya di teks asli, agar potongan yang berdampingan dapat digabung kembali utuh. */
function potong(teks: string, lebar: number): Rentang[] {
  const bagian = potongTeks(teks, lebar, MAKS);
  const awal: number[] = [];
  let cari = 0;
  for (const b of bagian) {
    const kata = b.split(/\s/)[0] ?? '';
    const i = kata ? teks.indexOf(kata, cari) : -1;
    const mulai = i < 0 ? cari : i;
    awal.push(mulai);
    // Potongan dinormalkan (spasi dirapatkan), jadi awal potongan berikutnya tidak lebih dekat dari ini.
    cari = mulai + b.length;
  }
  return awal.map((a, j) => ({ awal: a, akhir: j + 1 < awal.length ? awal[j + 1] : teks.length }));
}

const baris = computed<Baris[]>(() => {
  const isi: Baris[] = props.temuan.flatMap((t, i) => {
    const desk = potong(sumber(t, 'desk'), 30);
    const akar = potong(sumber(t, 'akar'), 33);
    const tindakan = potong(sumber(t, 'tindakan'), 40);
    return Array.from({ length: Math.max(desk.length, akar.length, tindakan.length) }, (_, j) => (
      { no: i + 1, t, desk: desk[j] ?? null, akar: akar[j] ?? null, tindakan: tindakan[j] ?? null, lanjut: j > 0 }));
  });
  while (isi.length < 7) isi.push({ no: 0, t: null, desk: null, akar: null, tindakan: null, lanjut: false });
  return isi;
});

/** Satukan potongan lanjutan suatu temuan yang jatuh di lembar yang sama dengan potongan sebelumnya. */
function gabung(h: Baris[]): Baris[] {
  const hasil: Baris[] = [];
  for (const b of h) {
    const akhir = hasil[hasil.length - 1];
    if (b.lanjut && akhir?.t === b.t) {
      for (const k of KOLOM) {
        const a = akhir[k];
        const c = b[k];
        if (a && c) akhir[k] = { awal: a.awal, akhir: c.akhir };
        else if (c) akhir[k] = c;
      }
    } else {
      hasil.push({ ...b });
    }
  }
  return hasil;
}
const teks = (b: Baris, k: typeof KOLOM[number]) => (b.t && b[k] ? sumber(b.t, k).slice(b[k]!.awal, b[k]!.akhir).trim() : '');
</script>

<template>
  <Head title="Rencana Tindak Lanjut" />
  <CetakLayout judul="Formulir Rencana Tindak Lanjut Ketidaksesuaian" :kembali="kembali" lanskap>
    <LembarBaris v-slot="{ baris: h, pertama, akhir, ukur }" :dok="dok" :baris="baris" lanskap>
      <div v-if="pertama" data-hanya-awal class="flow-root">
        <p class="form-judul">Formulir Rencana Tindak Lanjut Ketidaksesuaian Audit<br>Penerapan Sistem Manajemen Keselamatan Pertambangan</p>
        <FormIdentitas :baris="[['Nama Perusahaan', audit.perusahaan.nama], ['Tanggal Audit', audit.periode]]" />
      </div>
      <table class="tabel-form text-[10.5px] leading-snug">
        <thead><tr><th class="w-8">No</th><th class="w-[11%]">Nomor Ketidaksesuaian</th><th class="w-[18%]">Deskripsi Ketidaksesuaian</th><th class="w-[20%]">Akar Permasalahan</th><th>Tindakan Koreksi</th><th class="w-[13%]">Penanggung Jawab</th><th class="w-[11%]">Batas Waktu Perbaikan</th></tr></thead>
        <tbody data-baris>
          <tr v-for="(b, j) in ukur ? h : gabung(h)" :key="j" class="h-12">
            <td class="text-center tabular-nums">{{ b.t && !b.lanjut ? b.no : '' }}</td>
            <td class="tabular-nums">{{ b.t?.nomor }}<span v-if="b.t && b.lanjut" class="block italic text-slate-500">(lanjutan)</span></td>
            <td class="whitespace-pre-line text-justify">{{ teks(b, 'desk') }}</td>
            <td class="whitespace-pre-line text-justify">{{ teks(b, 'akar') }}</td>
            <td class="whitespace-pre-line text-justify">{{ teks(b, 'tindakan') }}</td>
            <td class="text-justify">{{ b.lanjut ? '' : b.t?.pj }}</td>
            <td class="text-center">{{ b.lanjut ? '' : b.t?.batas }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="akhir" data-penutup class="flow-root">
        <FormTtd :pihak="[{ peran: 'Nama Auditor', kunci: 'ketua', nama: ttd.ketua }]" :ttd="ttd" bagian="tindak-lanjut" />
      </div>
    </LembarBaris>
  </CetakLayout>
</template>
