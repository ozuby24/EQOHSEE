<script setup lang="ts">
import { computed } from 'vue';
import Ikon from '../Ikon.vue';

/*
 * Bantuan "Apa yang diisi?" per bagian Tahap I (port DBEST #23): bahasa
 * sederhana dan contoh, agar auditor baru tidak bingung dengan istilah
 * formulir resmi. Slot bawaan untuk alat tambahan (mis. kalkulator FR/SR).
 */
const props = defineProps<{ bagian: 'a' | 'b' | 'c' | 'd' | 'e' | 'f' | 'g'; judul?: string }>();

const ISI: Record<string, { pembuka: string; butir: string[]; kaki: string }> = {
  a: {
    pembuka: 'Catat pertemuan pertama dengan perusahaan yang diaudit dan surat tugas tim audit.',
    butir: [
      'Sifat & media: mis. Formal · rapat di kantor site / daring.',
      'Perwakilan auditi: orang yang ditemui, mis. KTT/PJO atau HSE Superintendent.',
      'Nomor & tanggal surat: surat pengangkatan tim audit dari KTT/PJO tahun ini.',
      'Periode audit: rentang data yang diperiksa, biasanya 1 Januari – 31 Desember tahun sebelumnya.',
    ],
    kaki: 'Alamat, perizinan, dan komoditas biasanya sama dengan tahun lalu — kosongkan untuk memakai data induk perusahaan.',
  },
  b: {
    pembuka: 'Angka statistik keselamatan selama periode audit, dari laporan statistik K3/KO bulanan atau laporan ke Inspektur Tambang.',
    butir: [
      'Jumlah kecelakaan dihitung per tingkat cidera: ringan, berat, mati.',
      'FR, SR, ASR, dan MFR dapat dihitung dengan kalkulator di bawah — cukup isi jam kerja dan jumlah kejadian.',
      'Kosongkan yang datanya memang tidak ada; jangan isi 0 bila belum tahu.',
    ],
    kaki: 'FR/SR dan ASR/MFR dibandingkan otomatis dengan rata-rata nasional Surat Edaran untuk saran penambahan hari audit (bagian D).',
  },
  c: {
    pembuka: 'Nilai apakah tujuh hal ini cukup untuk menjalankan audit, lalu tetapkan hasilnya.',
    butir: [
      'Tulis hasil evaluasi tiap indikator, mis. "Memadai" atau alasan bila belum memadai.',
      'Pilih Layak bila semua memadai; Tidak layak bila ada yang menghalangi audit.',
    ],
    kaki: 'Contoh alasan: "Belum memadai: data kinerja triwulan IV belum tersedia, dijanjikan 10 Oktober."',
  },
  d: {
    pembuka: 'Tentukan lama audit. Hari audit dihitung otomatis mengikuti Lampiran II SE Dirjen Minerba.',
    butir: [
      'Isi jumlah pekerja, kelas risiko, mandays dasar, dan jumlah auditor.',
      'Centang kondisi penambah hanya bila memang berlaku; kondisi 4, 5, dan 7 ditandai otomatis dari data kinerja (bagian B).',
      'Satu kondisi atau lebih menambah hari kerja paling banyak 10% — bukan satu hari per kondisi.',
    ],
    kaki: 'Hasil akhir (hari per auditor, total, alokasi Tahap I & II) tampil di kotak biru — tidak perlu dihitung manual.',
  },
  e: {
    pembuka: 'Periksa dokumen & rekaman tiap elemen SMKP sebelum audit lapangan.',
    butir: [
      'Pilih Lengkap bila dokumen utamanya tersedia; Tidak lengkap bila ada yang kurang.',
      'Tulis catatan singkat untuk yang tidak lengkap, mis. "Prosedur IBPR belum disahkan".',
    ],
    kaki: 'Kecukupan dokumentasi menentukan audit siap lanjut ke Tahap II (lihat panel kanan).',
  },
  f: {
    pembuka: 'Bahan untuk menentukan metode dan sampel di Rencana Audit (Berita Acara bagian III).',
    butir: [
      'Top risks: risiko tertinggi kegiatan tahun berjalan (Risk of Present) dan rencana tahun depan (Risk of Future).',
      'Data trend: 3–5 hal yang paling sering muncul pada laporan kejadian (penyebab, lokasi, kegiatan, jabatan).',
      'Unjuk kerja peralatan: PA/MA (%) alat utama. Key leading indicator: status pencapaiannya.',
      'Data audit tahun lalu: tombol Isi dari audit … mengambil temuan audit sebelumnya.',
    ],
    kaki: 'Masukan pemangku kepentingan dapat diambil dari Buku Tambang, notulen rapat, atau pengaduan.',
  },
  g: {
    pembuka: 'Tanggal Berita Acara ditandatangani.',
    butir: ['Tanda tangan Ketua Tim, PJO/KTT dibubuhkan di halaman cetak Berita Acara (tombol Berita Acara di atas).'],
    kaki: 'Setelah itu lanjut ke Rencana Audit.',
  },
};

const isi = computed(() => ISI[props.bagian]);
</script>

<template>
  <details class="group mt-3 rounded-xl border border-sky-100 bg-sky-50/50 open:bg-sky-50/70">
    <summary class="flex cursor-pointer list-none items-center gap-2 px-3.5 py-2 text-[12.5px] font-semibold text-sky-800 [&::-webkit-details-marker]:hidden">
      <Ikon nama="info" class="w-4 h-4 shrink-0" /> {{ judul ?? 'Petunjuk pengisian' }}
      <Ikon nama="chevron-down" class="ml-auto w-4 h-4 transition-transform group-open:rotate-180" />
    </summary>
    <div class="border-t border-sky-100 px-3.5 pb-3.5 pt-2.5 text-[12.5px] leading-relaxed text-slate-700">
      <p>{{ isi.pembuka }}</p>
      <ul class="mt-1.5 list-disc space-y-1 pl-5">
        <li v-for="b in isi.butir" :key="b">{{ b }}</li>
      </ul>
      <p class="mt-2 text-[12px] text-slate-500">{{ isi.kaki }}</p>
      <slot />
    </div>
  </details>
</template>
