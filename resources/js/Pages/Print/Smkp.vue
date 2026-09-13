<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  mode: string;
  audit: any;
  totalLembar?: number;
  dok?: any;
  elemen?: any[];
  kelayakan?: Record<string, string>;
  faktor?: Record<string, string>;
  pengurang?: Record<string, string>;
  /* Jawaban tujuh faktor penyesuaian yang BERLAKU — hitungan mengalahkan
     centang — beserta alasan bagi yang terhitung. */
  berlaku?: Record<string, boolean>;
  terhitung?: Record<string, { nilai: boolean | null; alasan: string; terkunci: boolean }>;
  nasional?: Record<string, { label: string }>;
  /** Matriks Metode & Sampel, hanya kriteria yang sudah direncanakan. */
  matriks?: Array<{
    kode: string; nama: string; elemen: string; elemenNama: string;
    na: boolean; ket: string; metode: Array<{ label: string; sampel: string }>;
  }>;
  rekapSampel?: { total: number; terisi: number; na: number; kurang: number; persen: number; lengkap: boolean };

  /* Bagian naratif Laporan Audit. */
  narasi?: Record<string, any>;
  dasarHukum?: string[];
  kategori?: Record<string, { label: string; singkat: string; baris: any[] }>;
  barisTemuan?: Array<{ nomor: string; kode: string; uraian: string; kategori: string; status: string }>;
  praktik?: Record<string, Array<{ kode: string; nama: string; ket: string; elemenNama: string }>>;
  pekerja?: { karyawan: number; subkontrak: number; total: number; risiko: string | null };

  /** Blok tanda tangan sesuai jenis auditi, dibangun di satu tempat. */
  ttd?: {
    jenis: string;
    baris: Array<{
      kunci: string; peran: string; peranRingkas: string;
      organisasi: string; nama: string; tanggal: string | null;
    }>;
    catatan: string[];
  };
  selaras?: Array<{ kunci: string; judul: string; selaras: boolean; ket: string }>;
  tim?: Array<Record<string, any>>;
  kinerja?: Record<string, any>;
  komponen?: Record<string, any>;
  pengesah?: Record<string, any>;
  judul?: string;
  hadir?: any[];
  temuan?: any[];
  rekap?: any;
  mandays?: any;
  meta?: any;
  kembali?: string;
}>();

const rencana = computed(() => props.audit?.rencana || {});
const permulaan = computed(() => props.audit?.permulaan || {});
const title = computed(() => ({
  berita: 'Berita Acara Tahap I',
  rencana: 'Rencana Audit SMKP',
  hadir: `Daftar Hadir ${props.judul || ''}`,
  laporan: 'Laporan Audit SMKP',
}[props.mode] || 'Dokumen SMKP'));
const pages = computed(() => {
  const rows = props.mode === 'hadir' ? props.hadir || [] : props.temuan || [];
  const size = props.mode === 'hadir' ? 16 : 6;
  const result: any[][] = [];
  for (let i = 0; i < rows.length; i += size) result.push(rows.slice(i, i + size));
  return result.length ? result : [[]];
});
const tanggal = (value: unknown) => value
  ? new Date(String(value)).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
  : '-';
const nomor = (value: unknown) => Number(value) + 1;

/* ── Angka untuk dibaca manusia ──
 *
 * `rekap()` menyimpan capaian sebagai pecahan penuh — 0.8421052631578947 —
 * supaya penjumlahan tujuh elemen tidak kehilangan ketelitiannya. Angka
 * itu benar, dan tercetak apa adanya ia salah tempat: tabel pencapaian
 * pada laporan yang dibaca inspektur tambang memuat "0.8421052631578947"
 * di kolom Capaian dan "8.421052631578947" di kolom Nilai.
 *
 * Pembulatannya di sini, di lapisan tampilan, bukan pada hitungannya:
 * membulatkan lebih awal akan membuat jumlah tujuh elemen menyimpang
 * dari nilai akhir yang tercetak di atasnya. */
const persen = (v: unknown) =>
  Number.isFinite(Number(v))
    ? `${(Number(v) * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`
    : '—';

/* Pemisah desimal Indonesia. Lembar yang sama memuat "238,84" pada
   tabel kinerja dan "6.5" pada tabel hari kerja; dua gaya angka dalam
   satu berkas resmi terbaca sebagai salah cetak. */
const hari = (v: unknown) =>
  Number.isFinite(Number(v))
    ? Number(v).toLocaleString('id-ID', { maximumFractionDigits: 2 })
    : '-';

const angka2 = (v: unknown) =>
  Number.isFinite(Number(v))
    ? Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : '—';
/* Daftar ketidaksesuaian dipenggal delapan baris tiap lembar. Deretnya
   sudah rata dan bernomor dari server, jadi pemenggalannya tidak perlu
   memikirkan batas kategori — kategorinya ikut pada tiap baris. */
const lembarTemuan = computed<any[][]>(() => {
  const baris = props.barisTemuan || [];
  const out: any[][] = [];
  for (let i = 0; i < baris.length; i += 8) out.push(baris.slice(i, i + 8));
  return out.length ? out : [[]];
});

const total = computed(() => props.totalLembar || (
  props.mode === 'hadir' || props.mode === 'laporan' ? pages.value.length : 1
));
</script>

<template>
  <Head :title="title" />
  <PrintShell :title="title" :kembali="props.kembali">
    <template v-if="props.mode === 'berita'">
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" :title="title" :halaman="1" :dari="4" />
        <h1 class="text-center font-bold text-[16px] uppercase border-b border-stone-200 pb-4 mb-5">Berita Acara Hasil Pelaksanaan</h1>
        <h2 class="text-center font-bold text-[13px] uppercase mb-5">Tahapan Awal Audit Internal SMKP</h2>
        <InfoAudit :audit="props.audit" />
        <h3 class="font-bold text-[13px] mb-2">Data Kinerja Keselamatan Pertambangan</h3>
        <table class="w-full text-[12px]"><tbody>
          <tr v-for="(item, key) in props.kinerja || {}" :key="key" class="border-b border-stone-100">
            <td class="p-2">{{ item.label || key }}</td>
            <td class="p-2 text-right font-semibold">{{ props.audit?.kinerja?.[key] || '-' }} {{ item.satuan || '' }}</td>
          </tr>
        </tbody></table>
      </section>
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" :title="title" :halaman="2" :dari="4" />
        <h3 class="font-bold text-[13px] mb-2">B. Pelaksanaan Kontak Awal dan Kelayakan Audit</h3>
        <p class="text-[12px] text-stone-600 mb-3">Kontak awal: {{ tanggal(permulaan.tanggal_kontak) }} - {{ permulaan.media_kontak || 'Media belum dicatat' }} - {{ permulaan.wakil_auditi || 'Auditi belum dicatat' }}<span v-if="permulaan.jabatan_wakil"> ({{ permulaan.jabatan_wakil }})</span></p>

        <!-- Tim auditor ikut pada Berita Acara Tahap I, sebab langkah ini
             memang "Kontak Awal & Penugasan Tim": berkas inilah yang
             menugaskan mereka, dan jumlah namanya pula yang membagi durasi
             audit pada tabel hari kerja di lembar berikutnya. -->
        <h3 class="font-bold text-[13px] mt-5 mb-2">Susunan Tim Auditor</h3>
        <table class="w-full text-[11.5px] mb-3"><thead><tr class="bg-stone-100 text-left"><th class="p-2 w-10">No</th><th class="p-2">Nama</th><th class="p-2 w-32">Peran</th><th class="p-2 w-28">No. Registrasi</th></tr></thead><tbody>
          <tr v-for="(row, index) in props.tim || []" :key="index" class="border-b border-stone-100">
            <td class="p-2">{{ nomor(index) }}</td>
            <td class="p-2 font-semibold">{{ row.nama }}</td>
            <td class="p-2">{{ row.peran }}</td>
            <td class="p-2">{{ row.registrasi || '-' }}</td>
          </tr>
          <tr v-if="!(props.tim || []).length">
            <td colspan="4" class="p-6 text-center text-stone-400">Susunan tim auditor belum diisi.</td>
          </tr>
        </tbody></table>
        <table class="w-full text-[11.5px]"><thead><tr class="bg-stone-100 text-left"><th class="p-2">No</th><th class="p-2">Indikator Kelayakan Audit</th><th class="p-2">Hasil Evaluasi</th></tr></thead><tbody>
          <!-- Nomor dari POSISI barisnya, bukan dari kuncinya. Kunci di sini
               teks — `profil_organisasi` — dan Number() atasnya menghasilkan
               NaN, yang tercetak apa adanya pada berkas terkendali yang
               ditandatangani KTT. -->
          <tr v-for="(label, key, index) in props.kelayakan || {}" :key="key" class="border-b border-stone-100"><td class="p-2">{{ nomor(index) }}</td><td class="p-2">{{ label }}</td><td class="p-2">{{ permulaan.kelayakan?.[key] || '-' }}</td></tr>
        </tbody></table>
        <p v-if="permulaan.kesimpulan" class="text-[12px] text-stone-600 mt-4 whitespace-pre-line">{{ permulaan.kesimpulan }}</p>
      </section>
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" :title="title" :halaman="3" :dari="4" />
        <h3 class="font-bold text-[13px] mb-2">C. Perhitungan Hari Kerja Audit</h3>
        <!-- Mandays dasar dibaca dari tabel menurut jumlah pekerja dan kelas
             risiko; baris ini yang membuat angkanya dapat ditelusuri kembali
             ke dasarnya, bukan sekadar dipercaya. -->
        <table class="w-full text-[11.5px] mb-5"><tbody>
          <tr v-for="baris in [
                ['Jumlah pekerja auditi', `${hari(props.mandays?.pekerja)} orang`],
                ['Kelas risiko', props.mandays?.kelas ?? '-'],
                ['Rentang tabel', props.mandays?.rentang ?? '-'],
                ['Mandays dasar (tabel)', `${hari(props.mandays?.dasar)} hari`],
                ['Faktor penyesuaian', `+${props.mandays?.penambah ?? 0} / −${props.mandays?.pengurang ?? 0} hari`],
                ['Total mandays', `${hari(props.mandays?.total)} orang-hari`],
                ['Jumlah auditor', `${hari(props.mandays?.auditor)} orang`],
                ['Durasi audit di lapangan', `${hari(props.mandays?.durasi)} hari`],
                ['Alokasi Tahap I (maks 10%)', `${hari(props.mandays?.tahap1)} hari`],
                ['Alokasi Tahap II', `${hari(props.mandays?.tahap2)} hari`],
              ]" :key="baris[0]" class="border-b border-stone-100">
            <td class="p-2 text-stone-500 w-64">{{ baris[0] }}</td>
            <td class="p-2 font-semibold">{{ baris[1] }}</td>
          </tr>
        </tbody></table>

        <h3 class="font-bold text-[13px] mb-2">D. Faktor Penyesuaian dan Kecukupan Dokumentasi</h3>
        <!-- Uraian faktornya, bukan kunci ruasnya. Daftar ini datang sebagai
             peta kunci → kalimat, jadi yang digambar nilainya. -->
        <!-- YANG TERCETAK ADALAH JAWABAN YANG BERLAKU, bukan centang mentah.
             Empat dari tujuh butir dihitung dari angka kinerja, dan hitungan
             mengalahkan centang. Mencetak centangnya membuat lembar ini
             menyangkal mandays yang tercetak tepat di atasnya — misalnya
             "Tidak" pada faktor kecelakaan, di bawah tabel yang sudah
             menambahnya satu hari. -->
        <table class="w-full text-[11.5px] mb-5"><thead><tr class="bg-stone-100 text-left">
          <th class="p-2 w-8">No</th><th class="p-2">Kondisi</th>
          <th class="p-2 w-12 text-center">Ya</th><th class="p-2 w-12 text-center">Tidak</th>
        </tr></thead><tbody>
          <tr v-for="(teks, key, i) in props.faktor || {}" :key="key" class="border-b border-stone-100">
            <td class="p-2 align-top">{{ Number(i) + 1 }}</td>
            <td class="p-2">{{ teks }}<span v-if="props.terhitung?.[key]?.terkunci" class="block text-[10px] text-stone-500 mt-0.5">{{ props.terhitung[key].alasan }}</span></td>
            <td class="p-2 text-center align-top">{{ props.berlaku?.[key] ? '√' : '' }}</td>
            <td class="p-2 text-center align-top">{{ props.berlaku?.[key] ? '' : '√' }}</td>
          </tr>
        </tbody></table>
        <table class="w-full text-[11.5px] mb-5"><thead><tr class="bg-stone-100 text-left"><th class="p-2">Faktor pengurang hari</th><th class="p-2 w-24">Hasil</th></tr></thead><tbody>
          <tr v-for="(teks, key) in props.pengurang || {}" :key="key" class="border-b border-stone-100"><td class="p-2">{{ teks }}</td><td class="p-2">{{ permulaan.pengurang?.[key] ? 'Ya' : 'Tidak' }}</td></tr>
        </tbody></table>
        <table class="w-full text-[11.5px]"><thead><tr class="bg-stone-100 text-left"><th class="p-2">Elemen</th><th class="p-2">Status</th><th class="p-2">Catatan</th></tr></thead><tbody>
          <tr v-for="element in props.elemen || []" :key="element.kode" class="border-b border-stone-100"><td class="p-2 font-semibold">{{ element.kode }} - {{ element.nama }}</td><td class="p-2">{{ props.audit?.kecukupan?.[element.kode]?.status || 'Belum ditinjau' }}</td><td class="p-2">{{ props.audit?.kecukupan?.[element.kode]?.ket || '-' }}</td></tr>
        </tbody></table>
      </section>
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">
        <DocHeader :dok="props.dok" :title="title" :halaman="4" :dari="4" />
        <h3 class="font-bold text-[13px] mb-2">D. Kesimpulan Tahap I</h3>
        <div class="rounded-xl border border-stone-200 p-4 text-[12px] text-stone-600 whitespace-pre-line">{{ permulaan.kesimpulan || 'Hasil dan kesimpulan Tahap I belum diisi.' }}</div>
        <!-- Blok tanda tangan mengikuti berkas acuan: tiap baris memuat
             jabatan, nama, tanggal, dan ruang tanda tangan — bukan dua
             kolom nama berjajar. Yang tercetak hanya jabatan yang berlaku
             bagi jenis auditi; berkas yang menyediakan kolom kosong bagi
             PJO pada perusahaan pertambangan terbaca sebagai berkas yang
             belum lengkap ditandatangani. -->
        <table class="w-full text-[11px] mt-10">
          <thead><tr class="bg-stone-100 text-left">
            <th class="p-2">Jabatan</th><th class="p-2 w-52">Nama</th>
            <th class="p-2 w-36">Tanggal</th><th class="p-2 w-44">Tanda Tangan</th>
          </tr></thead>
          <tbody>
            <tr v-for="b in props.ttd?.baris || []" :key="b.kunci" class="border-b border-stone-200">
              <td class="p-2 align-bottom">{{ b.peran }}</td>
              <td class="p-2 align-bottom font-semibold">{{ b.nama || '—' }}</td>
              <td class="p-2 align-bottom">{{ b.tanggal ? tanggal(b.tanggal) : '' }}</td>
              <td class="p-2"><div class="h-12"></div></td>
            </tr>
          </tbody>
        </table>
        <p class="text-[10px] text-stone-500 mt-3 font-semibold">Catatan:</p>
        <ul class="text-[10px] text-stone-500 list-disc pl-4 space-y-0.5">
          <li v-for="(c, i) in props.ttd?.catatan || []" :key="i">{{ c }}</li>
        </ul>
      </section>
    </template>

    <template v-if="props.mode === 'rencana'">
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" :title="title" :halaman="1" :dari="props.totalLembar ?? 3" /><h1 class="text-center font-bold text-[16px] uppercase border-b border-stone-200 pb-4 mb-5">Rencana Audit SMKP</h1><InfoAudit :audit="props.audit" />
        <div v-for="(key, index) in ['tujuan', 'kriteria', 'ruang_lingkup']" :key="key" class="mb-4"><h3 class="font-bold text-[13px]">{{ nomor(index) }}. {{ props.komponen?.[key]?.judul || key }}</h3><p class="text-[12px] text-stone-600 whitespace-pre-line">{{ rencana[key] || 'Belum diisi.' }}</p></div>
        <h3 class="font-bold text-[13px]">4. {{ props.komponen?.tanggal?.judul || 'Jadwal Audit' }}</h3><p class="text-[12px] text-stone-600">{{ tanggal(rencana.tanggal_mulai) }} - {{ tanggal(rencana.tanggal_selesai) }}</p><!-- Dasar hari kerja audit ikut dicetak di sini, bukan hanya di Berita
             Acara Tahap I. Rencana Audit dibagikan kepada auditi, dan tanggal
             pelaksanaannya baru dapat disepakati bila dasar perhitungannya
             terbaca — jumlah pekerja, kelas risiko, dan pembaginya. Alokasi
             diukur dalam HARI DI LAPANGAN, bukan mandays: menyebut "3 hari
             dari 16 hari" ketika 16 itu orang-hari membuat pembacanya
             mengira audit molor lima kali lipat dari jadwalnya. -->
        <table class="w-full text-[11px] mt-3 border border-stone-200"><tbody>
          <tr v-for="baris in [
                ['Jumlah tenaga kerja auditi', `${hari(props.mandays?.pekerja)} orang`],
                ['Kelas risiko', props.mandays?.kelas ?? '-'],
                ['Total mandays', `${hari(props.mandays?.total)} orang-hari`],
                ['Jumlah auditor', `${hari(props.mandays?.auditor)} orang`],
                ['Durasi audit di lapangan', `${hari(props.mandays?.durasi)} hari`],
                ['Alokasi Tahap II', `${hari(props.mandays?.tahap2)} hari`],
              ]" :key="baris[0]" class="border-b border-stone-100">
            <td class="p-1.5 text-stone-500 w-56">{{ baris[0] }}</td>
            <td class="p-1.5 font-semibold">{{ baris[1] }}</td>
          </tr>
        </tbody></table>
        <!-- Ketidakselarasan dicetak, tidak disembunyikan. Rencana yang
             menjadwalkan lebih pendek dari alokasinya adalah kesepakatan
             yang perlu dibaca auditi sebelum ditandatangani, bukan cacat
             yang dirapikan diam-diam sebelum dibagikan. -->
        <p v-for="c in (props.selaras || []).filter((x) => !x.selaras)" :key="c.kunci"
           class="text-[10.5px] mt-2 font-semibold">
          Catatan — {{ c.judul }}: {{ c.ket }}
        </p>
      </section>
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" :title="title" :halaman="2" :dari="props.totalLembar ?? 3" /><h3 class="font-bold text-[13px] mb-2">5. {{ props.komponen?.susunan?.judul || 'Susunan Kegiatan' }}</h3><table class="w-full text-[11px]"><thead><tr class="bg-stone-100 text-left"><th class="p-2">No</th><th class="p-2">Tanggal</th><th class="p-2">Waktu</th><th class="p-2">Kegiatan</th><th class="p-2">Auditi</th><th class="p-2">Auditor</th></tr></thead><tbody><tr v-for="(row, index) in rencana.susunan || []" :key="index" class="border-b border-stone-100"><td class="p-2">{{ nomor(index) }}</td><td class="p-2">{{ tanggal(row.tanggal) }}</td><td class="p-2">{{ row.waktu || '-' }}</td><td class="p-2">{{ row.kegiatan || '-' }}</td><td class="p-2">{{ row.auditi || '-' }}</td><td class="p-2">{{ row.auditor || '-' }}</td></tr></tbody></table><h3 class="font-bold text-[13px] mt-6 mb-2">6. {{ props.komponen?.tugas?.judul || 'Tim Auditor' }}</h3><table class="w-full text-[11px]"><thead><tr class="bg-stone-100 text-left"><th class="p-2">No</th><th class="p-2">Nama</th><th class="p-2">Peran</th><th class="p-2">Registrasi</th><th class="p-2">Lingkup</th></tr></thead><tbody><tr v-for="(row, index) in rencana.tugas || []" :key="index" class="border-b border-stone-100"><td class="p-2">{{ nomor(index) }}</td><td class="p-2">{{ row.nama }}</td><td class="p-2">{{ row.peran }}</td><td class="p-2">{{ row.registrasi || '-' }}</td><td class="p-2">{{ row.lingkup || '-' }}</td></tr></tbody></table>
      </section>
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">
        <DocHeader :dok="props.dok" :title="title" :halaman="3" :dari="props.totalLembar ?? 3" /><!-- SEMBILAN KOMPONEN, bukan sepuluh. `sampel` bukan komponen
             tersendiri: ia bagian dari komponen ke-8, "Penetapan Metode dan
             Sampel Audit". Digambar sebagai butir tersendiri, ia memperoleh
             nomor 9 — nomor yang sudah dipakai Pengesahan di bawahnya —
             dan judulnya jatuh ke nama ruasnya, tercetak "9. sampel". -->
        <div v-for="(key, index) in ['sumberdaya', 'metode']" :key="key" class="mb-5">
          <h3 class="font-bold text-[13px]">{{ nomor(index + 6) }}. {{ props.komponen?.[key]?.judul || key }}</h3>
          <p class="text-[12px] text-stone-600 whitespace-pre-line">{{ rencana[key] || 'Belum diisi.' }}</p>
          <p v-if="key === 'metode' && rencana.sampel" class="text-[12px] text-stone-600 whitespace-pre-line mt-2">
            {{ rencana.sampel }}
          </p>
          <p v-if="key === 'metode'" class="text-[11px] text-stone-500 mt-2">
            Rincian metode dan sampel tiap kriteria tercantum pada lembar tersendiri:
            {{ props.rekapSampel?.terisi ?? 0 }} dari {{ props.rekapSampel?.total ?? 0 }} kriteria terencana<span
              v-if="props.rekapSampel?.na">, {{ props.rekapSampel.na }} dinyatakan tidak berlaku bagi auditi</span>.
          </p>
        </div><template v-for="slot in ['present', 'future']" :key="slot"><div v-if="rencana.risiko?.[slot]?.length" class="mb-4"><h3 class="font-bold text-[12px]">Top Risks - {{ slot }}</h3><table class="w-full text-[11px]"><tbody><tr v-for="(risk, index) in rencana.risiko[slot]" :key="index" class="border-b border-stone-100"><td class="p-2">{{ risk.kegiatan }}</td><td class="p-2">{{ risk.risiko }}</td><td class="p-2">{{ risk.nilai }}</td></tr></tbody></table></div></template><h3 class="font-bold text-[13px]">9. {{ props.komponen?.pengesahan?.judul || 'Pengesahan Rencana Audit' }}</h3>
        <table class="w-full text-[11px] mt-3">
          <thead><tr class="bg-stone-100 text-left">
            <th class="p-2">Jabatan</th><th class="p-2 w-52">Nama</th>
            <th class="p-2 w-36">Tanggal</th><th class="p-2 w-44">Tanda Tangan</th>
          </tr></thead>
          <tbody>
            <tr v-for="b in props.ttd?.baris || []" :key="b.kunci" class="border-b border-stone-200">
              <td class="p-2 align-bottom">{{ b.peran }}</td>
              <td class="p-2 align-bottom font-semibold">{{ b.nama || '—' }}</td>
              <td class="p-2 align-bottom">{{ b.tanggal ? tanggal(b.tanggal) : '' }}</td>
              <td class="p-2"><div class="h-12"></div></td>
            </tr>
          </tbody>
        </table>
        <p class="text-[10px] text-stone-500 mt-3 font-semibold">Catatan:</p>
        <ul class="text-[10px] text-stone-500 list-disc pl-4 space-y-0.5">
          <li v-for="(c, i) in props.ttd?.catatan || []" :key="i">{{ c }}</li>
        </ul>
      </section>

      <!-- ── Lembar 4: Matriks Metode dan Sampel ──
           Pada Rencana Audit acuan, tabel ini bertajuk "METODE DAN SAMPEL
           AUDIT" dan menempati lembar tersendiri sesudah susunan kegiatan.
           Ia yang menjawab pertanyaan yang paling sering diajukan inspektur
           atas sebuah temuan: sub-elemen ini dibuktikan dengan apa, dan
           sampelnya yang mana. -->
      <section v-if="(props.matriks || []).length"
               class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">
        <DocHeader :dok="props.dok" :title="title" :halaman="4" :dari="props.totalLembar ?? 4" />
        <h3 class="font-bold text-[13px] mb-1">Metode dan Sampel Audit</h3>
        <p class="text-[10.5px] text-stone-500 mb-3">
          {{ props.rekapSampel?.terisi ?? 0 }} dari {{ props.rekapSampel?.total ?? 0 }} kriteria terencana<span
            v-if="props.rekapSampel?.na"> · {{ props.rekapSampel.na }} dinyatakan tidak berlaku bagi auditi</span>.
        </p>
        <table class="w-full text-[10.5px]"><thead><tr class="bg-stone-100 text-left">
          <th class="p-2 w-16">No</th><th class="p-2 w-56">Kriteria</th>
          <th class="p-2 w-40">Metode Audit</th><th class="p-2">Sampel</th>
        </tr></thead><tbody>
          <template v-for="baris in props.matriks || []" :key="baris.kode">
            <tr v-if="baris.na" class="border-b border-stone-100">
              <td class="p-2 align-top font-semibold">{{ baris.kode }}</td>
              <td class="p-2 align-top">{{ baris.nama }}</td>
              <td class="p-2 align-top">N/A</td>
              <td class="p-2 align-top text-stone-500">{{ baris.ket || 'Tidak berlaku bagi auditi.' }}</td>
            </tr>
            <tr v-for="(m, i) in baris.metode" v-else :key="baris.kode + m.label"
                class="border-b border-stone-100">
              <td class="p-2 align-top font-semibold">{{ i === 0 ? baris.kode : '' }}</td>
              <td class="p-2 align-top">{{ i === 0 ? baris.nama : '' }}</td>
              <td class="p-2 align-top">{{ m.label }}</td>
              <td class="p-2 align-top whitespace-pre-line">{{ m.sampel }}</td>
            </tr>
          </template>
        </tbody></table>
      </section>
    </template>

    <template v-if="props.mode === 'hadir'"><section v-for="(bagian, page) in pages" :key="page" class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0" :class="Number(page) < pages.length - 1 ? 'lembar-putus' : ''"><DocHeader :dok="props.dok" :title="title" :halaman="nomor(page)" :dari="pages.length" /><h1 v-if="Number(page) === 0" class="text-center font-bold text-[16px] uppercase border-b border-stone-200 pb-4 mb-5">Daftar Hadir {{ props.judul }}</h1><table class="w-full text-[11.5px]"><thead><tr class="bg-stone-100 text-left"><th class="p-2">No</th><th class="p-2">Nama</th><th class="p-2">Jabatan</th><th class="p-2">Perusahaan</th><th class="p-2">Tanda Tangan</th></tr></thead><tbody><tr v-for="(person, index) in bagian" :key="person.id" class="border-b border-stone-100"><td class="p-2">{{ Number(page) * 16 + Number(index) + 1 }}</td><td class="p-2 font-semibold">{{ person.nama }}</td><td class="p-2">{{ person.jabatan || '-' }}</td><td class="p-2">{{ person.perusahaan || '-' }}</td><td class="p-2"><div class="h-7 border-b border-dashed border-stone-300"></div></td></tr><tr v-if="!bagian.length"><td colspan="5" class="p-8 text-center text-stone-400">Belum ada peserta tercatat.</td></tr></tbody></table></section></template>

    <template v-if="props.mode === 'laporan'">
      <!-- ── Lembar 1: sampul dan latar belakang ──
           Dasar hukumnya dicetak sebagai daftar bernomor, bukan satu
           paragraf: nomor peraturan yang tenggelam di tengah kalimat
           panjang adalah nomor yang tidak dapat diperiksa pembacanya. -->
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" :title="title" :halaman="1" :dari="total" />
        <!-- Sampul mengikuti berkas acuan: judul, nama auditi beserta
             site-nya, alamat kantor, lalu tanggal pelaksanaan audit. -->
        <h1 class="text-center font-bold text-[16px] uppercase leading-snug">
          Laporan Internal Audit<br>
          Penerapan Sistem Manajemen Keselamatan Pertambangan Mineral dan Batubara
        </h1>
        <p class="text-center font-bold text-[13px] uppercase mt-3">
          {{ props.audit?.company?.name || 'Perusahaan belum ditetapkan'
          }}<span v-if="props.audit?.company?.parent"> – Site {{ props.audit.company.parent }}</span>
        </p>

        <!-- Alamat kantor TIDAK diulang di sini: tabel keterangan audit
             tepat di bawahnya sudah memuatnya pada baris "Alamat
             perusahaan". -->
        <div class="text-center text-[10.5px] text-stone-600 mt-4 space-y-1">
          <p v-if="props.audit?.company?.location">
            <span class="font-bold uppercase">Site:</span> {{ props.audit.company.location }}
          </p>
          <p>
            <span class="font-bold uppercase">Pelaksanaan Audit:</span>
            {{ tanggal(rencana.tanggal_mulai || props.audit?.tanggal_mulai) }} s.d.
            {{ tanggal(rencana.tanggal_selesai || props.audit?.tanggal_selesai) }}
          </p>
        </div>

        <div class="border-t border-stone-200 mt-5 pt-5">
          <InfoAudit :audit="props.audit" />
        </div>

        <h3 class="font-bold text-[13px] mt-5 mb-2 uppercase">Latar Belakang</h3>
        <p class="text-[12px] text-stone-600 leading-relaxed mb-2">
          Audit Internal Sistem Manajemen Keselamatan Pertambangan ini merupakan bagian dari
          penerapan elemen SMKP Minerba, dan bertujuan memperoleh gambaran tingkat penerapan
          SMKP Minerba di {{ props.audit?.company?.name || 'auditi' }} pada periode audit.
          Dasar hukum pelaksanaan, penilaian, dan pelaporannya:
        </p>
        <ol class="text-[11.5px] text-stone-600 list-decimal pl-5 space-y-1">
          <li v-for="(d, i) in props.dasarHukum || []" :key="i" class="leading-relaxed">{{ d }}</li>
        </ol>
      </section>

      <!-- ── Lembar 2: gambaran umum auditi ── -->
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" title="Gambaran Umum" :halaman="2" :dari="total" />
        <h3 class="font-bold text-[13px] mb-2 uppercase">Gambaran Umum</h3>

        <h4 class="font-bold text-[12px] mt-3">Domisili dan Legalitas</h4>
        <p class="text-[11.5px] text-stone-600 whitespace-pre-line leading-relaxed">
          {{ props.narasi?.domisili || 'Belum diisi.' }}
        </p>

        <h4 class="font-bold text-[12px] mt-4">Kegiatan Perusahaan</h4>
        <p class="text-[11.5px] text-stone-600 whitespace-pre-line leading-relaxed">
          {{ props.narasi?.kegiatan || 'Belum diisi.' }}
        </p>

        <h4 v-if="(props.narasi?.peralatan || []).length" class="font-bold text-[12px] mt-4">
          Daftar Unit dan Peralatan
        </h4>
        <table v-if="(props.narasi?.peralatan || []).length" class="w-full text-[11.5px] mt-2">
          <thead><tr class="bg-stone-100 text-left"><th class="p-2">Peralatan</th><th class="p-2 w-28">Jumlah</th></tr></thead>
          <tbody>
            <tr v-for="(b, i) in props.narasi?.peralatan || []" :key="i" class="border-b border-stone-100">
              <td class="p-2">{{ b.jenis }}</td><td class="p-2">{{ b.jumlah || '-' }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Jumlah tenaga kerja dibaca dari profil perusahaan, tempat
             mandays audit juga dihitung darinya — bukan diketik ulang,
             sehingga laporan tidak dapat menyebut angka yang berbeda dari
             yang dipakai menghitung hari kerja auditor. -->
        <h4 class="font-bold text-[12px] mt-4">Tenaga Kerja</h4>
        <p class="text-[11.5px] text-stone-600 leading-relaxed">
          {{ props.pekerja?.total ?? 0 }} orang — {{ props.pekerja?.karyawan ?? 0 }} pekerja perusahaan
          dan {{ props.pekerja?.subkontrak ?? 0 }} pekerja jasa pertambangan<span
            v-if="props.pekerja?.risiko">, dengan kelas risiko {{ props.pekerja.risiko }}</span>.
        </p>

        <h4 class="font-bold text-[12px] mt-4">Penerapan SMKP</h4>
        <p class="text-[11.5px] text-stone-600 whitespace-pre-line leading-relaxed">
          {{ props.narasi?.penerapan || 'Belum diisi.' }}
        </p>
      </section>

      <!-- ── Lembar 3: ringkasan penerapan tiap elemen ──
           Paragraf naratifnya berdampingan dengan capaian angkanya. Yang
           dibaca inspektur adalah keduanya sekaligus: paragraf yang
           berbunyi "sudah berjalan baik" di atas capaian 12% adalah
           laporan yang menyangkal tabelnya sendiri. -->
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" title="Penerapan Tiap Elemen" :halaman="3" :dari="total" />
        <h3 class="font-bold text-[13px] mb-3">Penerapan SMKP — Ringkasan Tiap Elemen</h3>
        <div v-for="element in props.elemen || []" :key="element.kode"
             class="mb-3 pb-3 border-b border-stone-100 last:border-b-0">
          <div class="flex items-baseline justify-between gap-3">
            <h4 class="font-bold text-[12px]">Elemen {{ element.kode }} — {{ element.nama }}</h4>
            <span class="text-[10.5px] text-stone-500 shrink-0">
              Capaian {{ persen(props.rekap?.elemen?.[element.kode]?.capaian) }}
            </span>
          </div>
          <p class="text-[11.5px] text-stone-600 whitespace-pre-line leading-relaxed mt-1">
            {{ props.narasi?.elemen?.[element.kode] || 'Belum diisi.' }}
          </p>
        </div>
      </section>

      <!-- ── Lembar 4: lingkup audit dan penilaian ── -->
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" title="Lingkup dan Penilaian" :halaman="4" :dari="total" />
        <h3 class="font-bold text-[13px] mb-2 uppercase">Lingkup Audit</h3>
        <p class="text-[11.5px] text-stone-600 whitespace-pre-line leading-relaxed mb-5">
          {{ props.narasi?.lingkup || rencana.ruang_lingkup || 'Belum diisi.' }}
        </p>

        <h3 class="font-bold text-[13px] mb-2 uppercase">Ringkasan Laporan &amp; Penilaian Audit</h3>
        <h4 class="font-bold text-[12px] mb-2">Tingkat Pencapaian Penerapan SMKP Minerba</h4>
        <div class="rounded-xl border border-stone-200 p-4 flex justify-between mb-5">
          <span class="text-[11.5px]">Nilai akhir<br><b class="text-2xl">{{ props.rekap?.skor ?? 0 }}</b></span>
          <span class="text-[11.5px] text-right">Tingkat penerapan<br><b>{{ props.rekap?.tingkat?.label || '-' }}</b></span>
        </div>
        <table class="w-full text-[11px]">
          <thead><tr class="bg-stone-100 text-left">
            <th class="p-2">Elemen</th><th class="p-2 w-16">Bobot</th><th class="p-2 w-20">Dinilai</th>
            <th class="p-2 w-20">Capaian</th><th class="p-2 w-16">Nilai</th>
          </tr></thead>
          <tbody>
            <tr v-for="element in props.elemen || []" :key="element.kode" class="border-b border-stone-100">
              <td class="p-2">{{ element.kode }}. {{ element.nama }}</td>
              <td class="p-2">{{ props.rekap?.elemen?.[element.kode]?.bobot }}</td>
              <td class="p-2">{{ props.rekap?.elemen?.[element.kode]?.dinilai }}/{{ props.rekap?.elemen?.[element.kode]?.berlaku }}</td>
              <td class="p-2">{{ persen(props.rekap?.elemen?.[element.kode]?.capaian) }}</td>
              <td class="p-2 font-bold">{{ angka2(props.rekap?.elemen?.[element.kode]?.skor) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Lembar tersendiri: pelaksanaan audit dan tim auditornya.
           Pertanyaan pertama atas sebuah nilai audit adalah berapa lama
           audit itu berjalan dan oleh berapa orang. Tanpa keterangan itu,
           skor 82% dari kunjungan setengah hari terbaca sama saja dengan
           82% dari audit dua minggu — dan laporan inilah yang dibaca
           inspektur tambang. -->
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" title="Pelaksanaan Audit" :halaman="5" :dari="total" />
        <h3 class="font-bold text-[13px] mb-2 uppercase">Pelaksanaan Audit &amp; Tim Auditor</h3>
        <p class="text-[12px] text-stone-600 mb-3">
          Audit dilaksanakan {{ tanggal(rencana.tanggal_mulai || props.audit?.tanggal_mulai) }} —
          {{ tanggal(rencana.tanggal_selesai || props.audit?.tanggal_selesai) }}.
        </p>
        <table class="w-full text-[11.5px] mb-5"><tbody>
          <tr v-for="baris in [
                ['Jumlah tenaga kerja auditi', `${hari(props.mandays?.pekerja)} orang`],
                ['Kelas risiko', props.mandays?.kelas ?? '-'],
                ['Mandays dasar (tabel)', `${hari(props.mandays?.dasar)} hari`],
                ['Faktor penyesuaian', `+${props.mandays?.penambah ?? 0} / −${props.mandays?.pengurang ?? 0} hari`],
                ['Total mandays', `${hari(props.mandays?.total)} orang-hari`],
                ['Jumlah auditor', `${hari(props.mandays?.auditor)} orang`],
                ['Durasi audit di lapangan', `${hari(props.mandays?.durasi)} hari`],
                ['Alokasi Tahap I', `${hari(props.mandays?.tahap1)} hari`],
                ['Alokasi Tahap II', `${hari(props.mandays?.tahap2)} hari`],
              ]" :key="baris[0]" class="border-b border-stone-100">
            <td class="p-2 text-stone-500 w-64">{{ baris[0] }}</td>
            <td class="p-2 font-semibold">{{ baris[1] }}</td>
          </tr>
        </tbody></table>

        <!-- Selisih antara yang dialokasikan dan yang dijadwalkan ikut
             tercatat pada laporan, bukan hanya pada rencana. Ia menyangkut
             kecukupan sampel, dan pembaca laporan berhak menimbangnya
             sendiri terhadap nilai yang tertera di lembar sebelumnya. -->
        <div v-if="(props.selaras || []).some((c) => !c.selaras)" class="mb-5">
          <h4 class="font-bold text-[12px] mb-1">Catatan pelaksanaan</h4>
          <p v-for="c in (props.selaras || []).filter((c) => !c.selaras)" :key="c.kunci"
             class="text-[11px] text-stone-600 leading-relaxed">
            {{ c.judul }}: {{ c.ket }}
          </p>
        </div>

        <!-- Nama dan nomor registrasi auditor adalah bagian dari bukti
             audit, bukan pelengkap: laporan tanpa keduanya tidak dapat
             ditelusuri siapa yang menilai elemen mana. -->
        <h3 class="font-bold text-[13px] mb-2">Tim Auditor</h3>
        <table class="w-full text-[11.5px]"><thead><tr class="bg-stone-100 text-left"><th class="p-2 w-10">No</th><th class="p-2">Nama</th><th class="p-2">Peran</th><th class="p-2">No. Registrasi</th><th class="p-2">Lingkup Elemen</th></tr></thead><tbody>
          <tr v-for="(row, index) in props.tim || []" :key="index" class="border-b border-stone-100">
            <td class="p-2">{{ nomor(index) }}</td>
            <td class="p-2 font-semibold">{{ row.nama }}</td>
            <td class="p-2">{{ row.peran || '-' }}</td>
            <td class="p-2">{{ row.registrasi || '-' }}</td>
            <td class="p-2">{{ row.lingkup || '-' }}</td>
          </tr>
          <tr v-if="!(props.tim || []).length">
            <td colspan="5" class="p-6 text-center text-stone-400">
              Pembagian tugas tim auditor belum diisi pada Rencana Audit.
            </td>
          </tr>
        </tbody></table>
      </section>

      <!-- ── Lembar 6: informasi praktik terbaik ──
           Laporan yang hanya memuat kekurangan dibaca auditi sebagai
           daftar tuduhan, dan butir yang sudah berjalan baik kehilangan
           satu-satunya tempat ia tercatat. Laporan acuan mencantumkannya
           per elemen, sebelum daftar ketidaksesuaian. -->
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" title="Praktik Terbaik" :halaman="6" :dari="total" />
        <h3 class="font-bold text-[13px] mb-1">Informasi Praktek Terbaik</h3>
        <p class="text-[11px] text-stone-500 mb-3">
          Sub-elemen yang capaiannya penuh terhadap nilai maksimumnya.
        </p>

        <!-- Tiga kolom seperti berkas acuan: Elemen | Sub Elemen |
             Keterangan. Keterangannya adalah catatan yang ditulis auditor
             saat menilai butirnya — bukan nama butirnya diulang. -->
        <table v-if="Object.keys(props.praktik || {}).length" class="w-full text-[11px]">
          <thead><tr class="bg-stone-100 text-left">
            <th class="p-2 w-40">Elemen</th><th class="p-2 w-56">Sub Elemen</th><th class="p-2">Keterangan</th>
          </tr></thead>
          <tbody>
            <template v-for="element in props.elemen || []" :key="`pt-${element.kode}`">
              <tr v-for="(b, i) in props.praktik?.[element.kode] || []" :key="b.kode"
                  class="border-b border-stone-100">
                <td class="p-2 align-top">{{ i === 0 ? `${element.kode}. ${element.nama}` : '' }}</td>
                <td class="p-2 align-top font-semibold">{{ b.kode }} {{ b.nama }}</td>
                <td class="p-2 align-top">
                  {{ b.ket || 'Capaian penuh terhadap nilai maksimum butir.' }}
                </td>
              </tr>
            </template>
          </tbody>
        </table>

        <p v-if="!Object.keys(props.praktik || {}).length" class="text-[11.5px] text-stone-500">
          Belum ada sub-elemen yang mencapai nilai penuh pada periode audit ini.
        </p>

        <!-- Ringkasan jumlah tiap kategori, seperti pada Formulir
             Rekapitulasi Ketidaksesuaian. Bagian Kritikal tetap dicetak
             walau nihil: bagian yang hilang tidak dapat dibedakan dari
             bagian yang kosong. -->
        <h3 class="font-bold text-[13px] mt-5 mb-2">Rekapitulasi Ketidaksesuaian</h3>
        <table class="w-full text-[11.5px]">
          <thead><tr class="bg-stone-100 text-left"><th class="p-2">Kategori</th><th class="p-2 w-28">Jumlah</th></tr></thead>
          <tbody>
            <tr v-for="(g, k) in props.kategori || {}" :key="k" class="border-b border-stone-100">
              <td class="p-2">Ketidaksesuaian {{ g.label }}</td>
              <td class="p-2 font-bold">{{ g.baris.length }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- ── Lembar 7..n: daftar ketidaksesuaian ──
           Empat kolom seperti pada laporan acuan: nomor urut, nomor
           ketidaksesuaian, deskripsi, kategori. Nomornya menyebut
           kategorinya sendiri (ISM-MYR-01), sehingga "ketidaksesuaian
           nomor 30" tidak perlu ditelusuri ke tabel lain untuk diketahui
           mayor atau minor. -->
      <section v-for="(bagian, page) in lembarTemuan" :key="`nc-${page}`"
               class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0 lembar-putus">
        <DocHeader :dok="props.dok" title="Daftar Ketidaksesuaian"
                   :halaman="Number(page) + 7" :dari="total" />
        <h3 class="font-bold text-[13px] mb-3">
          Daftar Ketidaksesuaian ({{ (props.barisTemuan || []).length }})
        </h3>
        <table class="w-full text-[11px]">
          <thead><tr class="bg-stone-100 text-left">
            <th class="p-2 w-10">No</th><th class="p-2 w-28">Nomor</th>
            <th class="p-2 w-16">Kriteria</th><th class="p-2">Deskripsi Ketidaksesuaian</th>
            <th class="p-2 w-20">Kategori</th>
          </tr></thead>
          <tbody>
            <tr v-for="(b, i) in bagian" :key="b.nomor" class="border-b border-stone-100">
              <td class="p-2 align-top">{{ Number(page) * 8 + Number(i) + 1 }}</td>
              <td class="p-2 align-top font-semibold">{{ b.nomor }}</td>
              <td class="p-2 align-top">{{ b.kode }}</td>
              <td class="p-2 align-top">{{ b.uraian }}</td>
              <td class="p-2 align-top">{{ b.kategori }}</td>
            </tr>
            <tr v-if="!bagian.length">
              <td colspan="5" class="p-8 text-center text-stone-400">
                Tidak ada ketidaksesuaian tercatat pada periode audit ini.
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- ── Lembar penutup: lampiran, distribusi, tanda tangan ── -->
      <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">
        <DocHeader :dok="props.dok" title="Lampiran dan Distribusi"
                   :halaman="total" :dari="total" />

        <h3 class="font-bold text-[13px] mb-2 uppercase">Kesimpulan Audit</h3>
        <p class="text-[11.5px] text-stone-600 whitespace-pre-line leading-relaxed mb-5">
          {{ props.narasi?.kesimpulan
             || `Tingkat pencapaian penerapan SMKP Minerba adalah ${props.rekap?.skor ?? 0} (${props.rekap?.tingkat?.label || '-'}).` }}
        </p>

        <!-- Lampiran mendahului blok tanda tangan, seperti pada berkas
             acuan; distribusi laporan menyusul SESUDAHNYA. Urutannya bukan
             selera: yang menandatangani mengesahkan isi laporan beserta
             lampirannya, lalu barulah ditetapkan ke siapa berkas itu
             dibagikan. -->
        <h3 class="font-bold text-[13px] mb-2">Lampiran – Lampiran</h3>
        <ol class="text-[11px] text-stone-600 list-decimal pl-5 space-y-1">
          <li v-for="(l, i) in props.narasi?.lampiran || []" :key="i" class="leading-relaxed">{{ l }}</li>
        </ol>
        <p v-if="!(props.narasi?.lampiran || []).length" class="text-[11px] text-stone-400">
          Belum ada lampiran didaftar.
        </p>

        <!-- ── Penutup laporan ──
             Bentuknya mengikuti berkas acuan, bukan blok tanda tangan
             berkolom seperti Berita Acara dan Rencana Audit: laporan
             ditutup dengan baris tempat dan tanggal, lalu "Dibuat Oleh"
             berisi tim auditor, lalu "Diketahui Oleh" berisi jabatan
             auditi yang mengesahkan. Barulah distribusi laporan. -->
        <p class="text-[11.5px] mt-10 text-right pr-4">
          <span v-if="props.audit?.company?.location">{{ props.audit.company.location }}, </span>
          {{ tanggal(props.audit?.tanggal_selesai || rencana.tanggal_selesai) }}
        </p>

        <p class="text-[11.5px] mt-4">Dibuat Oleh,</p>
        <p class="text-[11.5px] font-bold uppercase">
          Tim Internal Auditor {{ props.audit?.company?.name || 'auditi' }}
        </p>
        <table class="w-full text-[11.5px] mt-3">
          <tbody>
            <tr v-for="(row, index) in props.tim || []" :key="index">
              <td class="py-3 w-10 align-bottom">{{ nomor(index) }}.</td>
              <td class="py-3 w-48 align-bottom">{{ row.peran || 'Auditor' }}</td>
              <td class="py-3 align-bottom">: {{ row.nama }}</td>
              <td class="py-3 w-56 align-bottom text-right text-stone-400">(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</td>
            </tr>
            <tr v-if="!(props.tim || []).length">
              <td class="py-3 w-10 align-bottom">1.</td>
              <td class="py-3 w-48 align-bottom">Ketua Tim Audit</td>
              <td class="py-3 align-bottom">: {{ props.audit?.ketua_auditor || '—' }}</td>
              <td class="py-3 w-56 align-bottom text-right text-stone-400">(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</td>
            </tr>
          </tbody>
        </table>

        <!-- "Diketahui Oleh" memuat penanda tangan pihak auditi saja —
             Ketua Tim Audit sudah tercantum di atas sebagai pembuat. -->
        <template v-if="(props.ttd?.baris || []).filter((b) => b.kunci !== 'ketua').length">
          <p class="text-[11.5px] mt-6">Diketahui Oleh,</p>
          <table class="w-full text-[11.5px] mt-2">
            <tbody>
              <tr v-for="b in (props.ttd?.baris || []).filter((x) => x.kunci !== 'ketua')" :key="b.kunci">
                <!-- Organisasi datang dari server, tidak ditebak di sini.
                     Pada audit perusahaan jasa pertambangan, KTT adalah
                     KTT pemegang IUP/IUPK — bukan KTT auditi, yang memang
                     tidak punya. -->
                <td class="py-3 align-bottom">{{ b.peranRingkas }} {{ b.organisasi }}</td>
                <td class="py-3 w-48 align-bottom">: {{ b.nama || '—' }}</td>
                <td class="py-3 w-56 align-bottom text-right text-stone-400">(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</td>
              </tr>
            </tbody>
          </table>
        </template>

        <p class="text-[11.5px] font-bold mt-6">Distribusi laporan:</p>
        <ol class="text-[11px] text-stone-600 list-decimal pl-5 space-y-1 mt-1">
          <li v-for="(d, i) in props.narasi?.distribusi || []" :key="i" class="leading-relaxed">{{ d }}</li>
        </ol>
        <p v-if="!(props.narasi?.distribusi || []).length" class="text-[11px] text-stone-400 mt-1">
          Belum ada distribusi didaftar.
        </p>
      </section>
    </template>
  </PrintShell>
</template>

<script lang="ts">
import { h } from 'vue';

/* Kop ringkas diganti kop penuh: yang lama hanya menyebut nama
   perusahaan, judul, dan nomor halaman — tanpa nomor dokumen,
   tanggal penerbitan, tanggal persetujuan, maupun revisi.
   Keempatnya justru yang membuat sebuah lembar disebut
   dokumen terkendali. */
const DocHeader = (props: any) => h(KopCetak, {
  dok: { ...(props.dok ?? {}), judul: props.dok?.judul || props.title },
  halaman: props.halaman, dari: props.dari,
});
/* Tabel keterangan auditi, urut dan berbunyi seperti "Data Perusahaan
   Auditi" pada Berita Acara acuan.

   PERIODE AUDIT ADALAH RENTANG TANGGAL, bukan tahun. Berkas acuan
   menuliskannya "01 Januari 2023 – 30 November 2023": ia menyebut
   periode yang DIAUDIT, dan itu yang menentukan rekaman mana yang
   diperiksa. Mencetak "2023" saja membuat pembacanya tidak dapat
   mengetahui apakah bulan Desember termasuk — dan pertanyaan itu
   menentukan apakah sebuah temuan sah. Tahunnya dipakai hanya bila
   rentangnya memang belum ditetapkan. */
/* Pemformat tanggalnya sendiri: blok skrip ini terpisah dari
   <script setup>, jadi pembantu di sana tidak terlihat dari sini. */
const tgl = (v: unknown) => v
  ? new Date(String(v)).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
  : '-';

const InfoAudit = (props: any) => {
  const a = props.audit ?? {};
  const r = a.rencana ?? {};

  const mulai = r.tanggal_mulai || a.tanggal_mulai;
  const akhir = r.tanggal_selesai || a.tanggal_selesai;

  const periode = mulai && akhir
    ? `${tgl(mulai)} – ${tgl(akhir)}`
    : String(a.tahun ?? '-');

  return h('table', { class: 'w-full text-[12px] mb-5' }, [
    h('tbody', {}, [
      ['Nama perusahaan auditi', a.company?.name || 'Seluruh Perusahaan'],
      ['Jenis perizinan', a.company?.izin_type || '-'],
      ['Jenis komoditas', a.company?.commodity || '-'],
      ['Alamat perusahaan', a.company?.address || a.company?.location || '-'],
      ['Periode audit', periode],
    ].map(([key, value]) => h('tr', { class: 'border-b border-stone-100' }, [
      h('td', { class: 'p-2 text-stone-500 w-56' }, key),
      h('td', { class: 'p-2' }, value),
    ]))),
  ]);
};
export default { components: { DocHeader, InfoAudit } };
</script>
