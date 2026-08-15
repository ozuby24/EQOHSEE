<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import IkonStat from '../Components/IkonStat.vue';

interface Enrollment {
  judul: string; deskripsi: string | null; sampul: string | null; kategori: string | null;
  nada: string | null; status: string; progress: number; modul: number; menit: number;
  belajar: string; detail: string;
}
interface NewsItem { judul: string; cuplikan: string; tanggal: string | null; url: string }
interface ModuleItem { nama: string; ket: string; nilai: number; total: number; warna: string; url: string; ikon: string }

const props = defineProps<{
  judul: string; subjudul: string; sapa: string; nama: string; hero: string | null; lanjut: string;
  enrollments: Enrollment[]; certificates: number; ringkas: { total: number; selesai: number; kemajuan: number; belum: number };
  news: NewsItem[]; modul: ModuleItem[]; kategori: Array<{ nama: string; jumlah: number; nada?: string }>;
  pekan: Array<{ label: string; nilai: number }>; admin: { users: number; courses: number; procedures: number; certs: number } | null;
}>();

const kpi: Array<{ label: string; value: () => number | string; hint: string; tone: string; ikon: string }> = [
  { label: 'Total Kursus', value: () => props.ringkas.total, hint: 'Semua kursus tersedia', tone: 'hijau', ikon: 'pustaka' },
  { label: 'Kursus Selesai', value: () => props.ringkas.selesai, hint: 'Kursus telah selesai', tone: 'biru', ikon: 'tuntas' },
  { label: 'Progress Belajar', value: () => `${props.ringkas.kemajuan}%`, hint: 'Rata-rata progress', tone: 'toska', ikon: 'laju' },
  { label: 'Sertifikat', value: () => props.certificates, hint: 'Sertifikat diperoleh', tone: 'kuning', ikon: 'medali' },
  { label: 'Belum Diikuti', value: () => props.ringkas.belum, hint: 'Menunggu untuk dimulai', tone: 'ungu', ikon: 'menunggu' },
];
</script>

<template>
  <Head title="Dashboard" />
  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="eq-hero">
      <img v-if="hero" class="eq-hero-foto" :src="hero" alt="" loading="lazy">
      <div class="eq-hero-isi"><h2>{{ sapa }}, {{ nama }} <span aria-hidden="true">👋</span></h2><p>Tingkatkan kompetensi dan budaya keselamatan Anda setiap hari.</p><Link :href="lanjut" class="eq-hero-btn">{{ enrollments.length ? 'Lanjutkan Pembelajaran' : 'Jelajahi Kursus' }} <span aria-hidden="true">→</span></Link></div>
    </section>

    <div class="eq-kpi-baris">
      <article v-for="item in kpi" :key="item.label" class="eq-kpi"><span class="eq-kpi-ikon" :class="`t-${item.tone}`"><IkonStat :nama="item.ikon" :ukuran="18" /></span><span class="eq-kpi-isi"><span class="eq-kpi-label">{{ item.label }}</span><span class="eq-kpi-nilai">{{ item.value() }}</span><span class="eq-kpi-ket">{{ item.hint }}</span></span></article>
    </div>

    <div class="eq-kisi-utama">
      <section class="eq-panel"><div class="eq-panel-kepala"><h3>Kursus Saya</h3><Link href="/courses" class="eq-tautan">Lihat Semua →</Link></div>
        <div v-if="enrollments.length" class="eq-kursus-kisi"><article v-for="kursus in enrollments" :key="kursus.judul" class="eq-kursus"><div class="eq-kursus-gambar"><img v-if="kursus.sampul" :src="kursus.sampul" alt="" loading="lazy"><span class="eq-kursus-lencana"><i v-if="kursus.kategori" class="l-utama" :class="`k-${kursus.nada}`">{{ kursus.kategori.toUpperCase() }}</i><i v-if="kursus.status === 'ongoing'" class="l-ikut">DIIKUTI</i><i v-else-if="kursus.status === 'finished'" class="l-selesai">SELESAI</i></span></div><div class="eq-kursus-isi"><h4>{{ kursus.judul }}</h4><p>{{ kursus.deskripsi }}</p><div class="eq-kursus-meta"><span>{{ kursus.modul }} Modul</span><span>{{ kursus.menit }} Menit</span></div><div class="eq-kursus-maju"><span>Progress Anda</span><b>{{ kursus.progress }}%</b></div><div class="eq-bilah"><i :style="{ width: `${kursus.progress}%` }"></i></div><div class="eq-kursus-aksi"><Link :href="kursus.belajar" class="eq-btn-utama">▶ {{ kursus.progress > 0 ? 'Lanjut Belajar' : 'Mulai Belajar' }}</Link><Link :href="kursus.detail" class="eq-btn-lain">Detail</Link></div></div></article></div>
        <div v-else class="eq-kosong"><p><strong>Belum ada kursus yang diikuti.</strong></p><p>Mulai dari katalog kursus dan pilih yang paling dibutuhkan pekerjaan Anda.</p><Link href="/courses" class="eq-btn-utama">Jelajahi Kursus</Link></div>
      </section>

      <div class="eq-kolom-sisi"><section class="eq-panel"><div class="eq-panel-kepala"><h3>Progress Mingguan</h3><span class="eq-chip">7 Hari</span></div><div v-if="pekan.some((h) => h.nilai > 0)" class="flex items-end gap-2 h-36 px-2"><div v-for="h in pekan" :key="h.label" class="flex-1 flex flex-col items-center gap-1"><span class="text-[10px] text-stone-400">{{ h.nilai }}</span><i class="w-full rounded-t bg-cam-lime" :style="{ height: `${Math.max(4, h.nilai * 18)}px` }"></i><span class="text-[10px] text-stone-400">{{ h.label }}</span></div></div><div v-else class="eq-kosong eq-kosong-kecil"><p>Belum ada modul yang diselesaikan pekan ini.</p><p class="halus">Grafik muncul setelah ada modul yang dituntaskan.</p></div></section>
        <section class="eq-panel"><div class="eq-panel-kepala"><h3>Pengumuman</h3><Link href="/news" class="eq-tautan">Lihat Semua →</Link></div><ul v-if="news.length" class="eq-warta"><li v-for="item in news" :key="item.url"><Link :href="item.url"><span class="eq-warta-teks"><strong>{{ item.judul }}</strong><small>{{ item.cuplikan }}</small></span><time>{{ item.tanggal }}</time></Link></li></ul><div v-else class="eq-kosong eq-kosong-kecil"><p>Belum ada pengumuman.</p></div></section></div>
    </div>

    <section v-if="kategori.length" class="eq-panel"><div class="eq-panel-kepala"><h3>Kategori Kursus</h3><Link href="/courses" class="eq-tautan">Lihat Semua →</Link></div><div class="eq-kategori"><Link v-for="item in kategori" :key="item.nama" :href="`/courses?kategori=${encodeURIComponent(item.nama)}`"><span class="eq-kategori-ikon" :class="`t-${item.nada ?? 'biru'}`"><IkonStat nama="kursus" :ukuran="18" /></span><span><strong>{{ item.nama }}</strong><small>{{ item.jumlah }} Kursus</small></span></Link></div></section>
    <section class="eq-panel"><div class="eq-panel-kepala"><h3>Modul Lainnya</h3><span class="eq-panel-ket">Angka yang ditampilkan adalah yang butuh perhatian.</span></div><div class="eq-modul"><Link v-for="item in modul" :key="item.nama" :href="item.url"><span class="eq-modul-atas"><span class="eq-modul-nilai" :style="{ color: item.warna }">{{ item.nilai }}</span><span class="eq-modul-ikon" :style="{ background: `${item.warna}18`, color: item.warna }"><IkonStat :nama="item.ikon" :ukuran="18" /></span></span><strong>{{ item.nama }}</strong><small>{{ item.ket }}<template v-if="item.total > 0"> · dari {{ item.total }}</template></small></Link></div></section>
    <section v-if="admin" class="eq-panel"><div class="eq-panel-kepala"><h3>Ringkasan Sistem</h3></div><div class="eq-kategori"><div v-for="item in [['Pengguna', admin.users], ['Kursus', admin.courses], ['Prosedur', admin.procedures], ['Sertifikat terbit', admin.certs]]" :key="item[0]" class="eq-admin-angka"><span class="eq-kpi-nilai">{{ item[1] }}</span><small>{{ item[0] }}</small></div></div></section>
  </div>
</template>
