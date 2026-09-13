<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';

defineOptions({ layout: BlankLayout });

/**
 * Lembar siap cetak satu PJP.
 *
 * Menggantikan ekspor PDF aplikasi asal yang memakai dompdf. EQOHSEE
 * sudah mencetak seluruh laporan modulnya lewat halaman cetak peramban
 * dengan kop dokumen terkendali yang sama — menambah dompdf untuk satu
 * modul berarti dua cara mencetak yang harus dirawat bersama, dengan kop
 * dan penomoran dokumen yang pasti berbeda cepat atau lambat.
 */
const props = defineProps<{
  dok: any;
  pjp: Record<string, any>;
  smkpScore: { total_bobot: number; total_skor: number; persentase: number; kategori_risiko: string };
  rincianKategori: Array<Record<string, any>>;
  legalitasStatus: { total: number; lengkap: number };
  pelaporanScore: number | null;
  achievement: number | null;
  kelompokLaporan: Array<{
    jenis: string; label: string;
    berkas: Array<Record<string, any>>;
  }>;
  evaluasis: Array<Record<string, any>>;
  kembali?: string;
}>();

const angka = (v: unknown, d = 1) => new Intl.NumberFormat('id-ID', {
  maximumFractionDigits: d, minimumFractionDigits: 0,
}).format(Number(v ?? 0));

const tanggal = (v: unknown) => v
  ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(String(v)))
  : '—';

const dicetak = new Intl.DateTimeFormat('id-ID', {
  day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
}).format(new Date());
</script>

<template>
  <Head :title="`Laporan Pemantauan PJP — ${props.pjp.nama_perusahaan}`" />

  <PrintShell title="Laporan Pemantauan Perusahaan Jasa Pertambangan" :kembali="props.kembali">
    <div class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">

      <KopCetak :dok="props.dok" />

      <!-- Judul lembarnya sudah tergambar di kop. Yang disebut di sini
           hanya PERIHAL-nya: perusahaan jasa mana yang dipantau — sebab
           satu lembar berlaku untuk satu perusahaan, dan tanpa namanya
           lembar-lembar ini tidak dapat dibedakan setelah tercetak. -->
      <header class="text-center border-b border-stone-200 pb-4 mb-5">
        <p class="text-[10px] uppercase tracking-wide text-stone-400">Perusahaan jasa yang dipantau</p>
        <p class="font-bold text-[14px] mt-0.5">{{ props.pjp.nama_perusahaan }}</p>
        <p class="text-[10px] text-stone-400 mt-1">{{ props.dok?.divisi }} · {{ props.dok?.departemen }}</p>
      </header>

      <h3 class="font-bold text-[13px] mb-2">A. Identitas Perusahaan</h3>
      <table class="w-full border-collapse text-[11px] mb-6">
        <tbody>
          <tr v-for="baris in [
            { k: 'Nama perusahaan', v: props.pjp.nama_perusahaan },
            { k: 'NIB', v: props.pjp.nib || '—' },
            { k: 'Penanggung jawab', v: props.pjp.penanggung_jawab || '—' },
            { k: 'Alamat', v: props.pjp.alamat || '—' },
            { k: 'Status pemantauan', v: props.pjp.statusLabel },
            { k: 'Terdaftar sejak', v: props.pjp.terdaftar || '—' },
          ]" :key="baris.k" class="border-b border-stone-200">
            <td class="p-2 w-[30%] text-stone-500">{{ baris.k }}</td>
            <td class="p-2 font-semibold whitespace-pre-line">{{ baris.v }}</td>
          </tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">B. Ringkasan Capaian Tiga Aspek</h3>
      <table class="w-full border-collapse text-[11px] mb-3">
        <tbody>
          <tr class="border-b border-stone-200">
            <td class="p-2 w-[35%] text-stone-500">Skor persyaratan (checklist SMKP)</td>
            <td class="p-2 font-semibold w-[20%]">{{ angka(props.smkpScore.persentase) }}%</td>
            <td class="p-2 text-stone-500">
              {{ angka(props.smkpScore.total_skor) }} dari {{ props.smkpScore.total_bobot }} bobot dinilai
            </td>
          </tr>
          <tr class="border-b border-stone-200">
            <td class="p-2 text-stone-500">Kategori risiko pekerjaan</td>
            <td class="p-2 font-semibold">{{ props.smkpScore.kategori_risiko }}</td>
            <td class="p-2 text-stone-500">Tingkat risiko pekerjaan yang layak dipercayakan</td>
          </tr>
          <tr class="border-b border-stone-200">
            <td class="p-2 text-stone-500">Dokumen legalitas</td>
            <td class="p-2 font-semibold">{{ props.legalitasStatus.lengkap }} / {{ props.legalitasStatus.total }}</td>
            <td class="p-2 text-stone-500">Syarat wajib, di luar skor berbobot</td>
          </tr>
          <tr class="border-b border-stone-200">
            <td class="p-2 text-stone-500">Skor kepatuhan pelaporan</td>
            <td class="p-2 font-semibold">
              {{ props.pelaporanScore === null ? 'Belum ada laporan' : `${angka(props.pelaporanScore)}%` }}
            </td>
            <td class="p-2 text-stone-500">Rata-rata ketepatan waktu dan kesesuaian isi</td>
          </tr>
          <tr class="border-b border-stone-200">
            <td class="p-2 text-stone-500">Capaian gabungan</td>
            <td class="p-2 font-semibold">
              {{ props.achievement === null ? '—' : `${angka(props.achievement)}%` }}
            </td>
            <td class="p-2 text-stone-500">Skor terendah di antara aspek yang datanya tersedia</td>
          </tr>
        </tbody>
      </table>

      <p class="text-[10px] text-stone-500 mb-6">
        Capaian gabungan sengaja diambil dari nilai terendah, bukan rata-rata, supaya satu aspek yang lemah
        tidak tertutup oleh dua aspek lain yang baik.
      </p>

      <h3 class="font-bold text-[13px] mb-2">C. Rincian Skor Persyaratan per Kategori</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead>
          <tr class="border-b border-stone-300 text-left text-stone-500">
            <th class="p-1.5">Kode</th><th class="p-1.5">Kategori</th>
            <th class="p-1.5 text-right">Bobot</th><th class="p-1.5 text-right">Dinilai</th>
            <th class="p-1.5 text-right">Skor</th><th class="p-1.5 text-right">Capaian</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="k in props.rincianKategori" :key="k.kode" class="border-b border-stone-100">
            <td class="p-1.5 font-semibold">{{ k.kode }}</td>
            <td class="p-1.5">{{ k.nama }}</td>
            <td class="p-1.5 text-right">{{ k.bobot }}</td>
            <td class="p-1.5 text-right">{{ k.bobot_dinilai }}</td>
            <td class="p-1.5 text-right">{{ angka(k.skor) }}</td>
            <td class="p-1.5 text-right font-semibold">{{ angka(k.persentase) }}%</td>
          </tr>
          <tr v-if="!props.rincianKategori.length">
            <td colspan="6" class="p-4 text-center text-stone-400">Belum ada data kategori.</td>
          </tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">D. Dokumen Kepatuhan</h3>
      <div v-for="kelompok in props.kelompokLaporan" :key="kelompok.jenis" class="mb-4">
        <p class="text-[11px] font-semibold mb-1">{{ kelompok.label }}</p>
        <table class="w-full border-collapse text-[10px]">
          <thead>
            <tr class="border-b border-stone-300 text-left text-stone-500">
              <th class="p-1.5">Nama berkas</th><th class="p-1.5">Periode</th>
              <th class="p-1.5">Diunggah</th><th class="p-1.5">Ketepatan waktu</th>
              <th class="p-1.5">Kesesuaian isi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in kelompok.berkas" :key="l.id" class="border-b border-stone-100">
              <td class="p-1.5">{{ l.file_name }}</td>
              <td class="p-1.5">{{ l.periode || '—' }}</td>
              <td class="p-1.5">{{ l.diunggah }}</td>
              <td class="p-1.5">{{ l.tepat_waktu ? 'Tepat waktu' : 'Terlambat' }}</td>
              <td class="p-1.5">
                {{ l.kesesuaian_isi === 'sesuai' ? 'Sesuai'
                   : l.kesesuaian_isi === 'tidak_sesuai' ? 'Tidak sesuai' : 'Belum dinilai' }}
              </td>
            </tr>
            <tr v-if="!kelompok.berkas.length">
              <td colspan="5" class="p-3 text-center text-stone-400">Belum ada dokumen diunggah.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h3 class="font-bold text-[13px] mb-2 mt-6">E. Evaluasi Kinerja per Semester</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead>
          <tr class="border-b border-stone-300 text-left text-stone-500">
            <th class="p-1.5">Periode</th><th class="p-1.5 text-right">Teknis</th>
            <th class="p-1.5 text-right">K3</th><th class="p-1.5 text-right">Lingkungan</th>
            <th class="p-1.5 text-right">Rata-rata</th><th class="p-1.5">Catatan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in props.evaluasis" :key="e.periode" class="border-b border-stone-100">
            <td class="p-1.5 font-semibold">{{ e.semesterLabel }} {{ e.tahun }}</td>
            <td class="p-1.5 text-right">{{ e.skor_teknis }}</td>
            <td class="p-1.5 text-right">{{ e.skor_keselamatan_kesehatan }}</td>
            <td class="p-1.5 text-right">{{ e.skor_lingkungan }}</td>
            <td class="p-1.5 text-right font-semibold">{{ angka(e.skor_rata_rata) }}</td>
            <td class="p-1.5 text-stone-500">{{ e.catatan || '—' }}</td>
          </tr>
          <tr v-if="!props.evaluasis.length">
            <td colspan="6" class="p-4 text-center text-stone-400">Belum ada evaluasi kinerja.</td>
          </tr>
        </tbody>
      </table>

      <section v-if="props.pjp.catatan" class="mb-6">
        <h3 class="font-bold text-[13px] mb-2">F. Catatan</h3>
        <p class="text-[11px] whitespace-pre-line border border-stone-200 p-3">{{ props.pjp.catatan }}</p>
      </section>

      <p class="text-[10px] text-stone-400 border-t border-stone-200 pt-3">
        Dicetak {{ dicetak }} — Modul Perusahaan Jasa Pertambangan, EQOHSEE.
      </p>
    </div>
  </PrintShell>
</template>
