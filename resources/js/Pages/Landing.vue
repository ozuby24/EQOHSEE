<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BlankLayout from '../Layouts/BlankLayout.vue';
import Wordmark from '../Components/Wordmark.vue';
import IkonPilar from '../Components/IkonPilar.vue';

defineOptions({ layout: BlankLayout });

type Pillar = {
  nama: string; ket: string; deep: string; warna: string; light: string;
  ringkas: string; cakupan: [string, string][]; modul: string[];
  // Nama ikon ('bolt', 'droplet', …) sudah lama dikirim App\Support\Pillars
  // tetapi belum pernah ikut di tipe ini, sehingga tidak ada yang menyadari
  // bahwa datanya tersedia.
  ikon: string;
};
type Module = {
  nama: string; status: string; ket: string; ikon: string; pilar: string;
  pilarNama: string; pilarWarna: string; pilarDeep: string; url: string | null;
};

const props = defineProps<{
  hero: { video: string | null; poster: string | null };
  galeri: { judul: string; ket: string; aspek: string; gambarUrl: string | null; videoUrl: string | null }[];
  klien: { nama: string; url: string }[];
  standar: { kode: string; ket: string }[];
  elemenSmkp: { nama: string; bobot: number }[];
  jumlahItem: number;
  modul: Module[];
  pilar: Record<string, Pillar>;
  fitur: { judul: string; ket: string }[];
  alur: { judul: string; ket: string }[];
  jual: {
    paket: { nama: string; harga: number; masa: string } | null;
    termurah: number | null;
    jumlah: number;
  };
  tahun: number;
}>();

const pilarTerpilih = ref<string | null>(null);
const videoTerbuka = ref(false);

/**
 * Pengguna yang meminta gerakan dikurangi mendapat poster diam.
 *
 * Bukan sekadar sopan santun: latar bergerak memicu mual dan pusing pada
 * sebagian orang, dan halaman depan adalah tempat mereka tidak punya
 * pilihan untuk menghindarinya. Dibaca sekali saat pemasangan — bukan
 * lewat kelas CSS — supaya videonya memang tidak diunduh sama sekali.
 */
const kurangiGerak = ref(
  typeof window !== 'undefined'
  && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true,
);
const pilarList = computed(() => Object.entries(props.pilar));

/**
 * Empat jaminan pada bagian harga, dengan vektornya masing-masing.
 *
 * Digambar sebagai path inline, bukan pustaka ikon: repo ini sudah
 * sekali melepas pustaka dari CDN, dan ikon yang gagal dimuat pada
 * jaringan site tambang meninggalkan kotak kosong persis di tempat yang
 * paling menjelaskan.
 */
const jaminan = [
  { teks: 'Bayar QRIS' },
  { teks: 'Tanpa membuat akun' },
  { teks: 'Lisensi terbit setelah bukti diperiksa' },
  { teks: 'Data terpisah per perusahaan' },
];

function togglePilar(slug: string) {
  pilarTerpilih.value = pilarTerpilih.value === slug ? null : slug;
}
</script>

<template>
  <Head title="Platform Terpadu Keselamatan Pertambangan" />

  <div class="bg-cam-bg text-cam-ink scroll-smooth">
    <header class="fixed inset-x-0 top-0 z-40 border-b border-white/10 bg-cam-ink/80 backdrop-blur-md text-white">
      <div class="max-w-6xl mx-auto px-5 h-[66px] flex items-center gap-3">
        <Link href="/" class="shrink-0"><Wordmark :tinggi="30" /></Link>
        <nav class="ml-auto hidden md:flex items-center gap-1 text-[12.5px] font-semibold">
          <a v-for="item in [['#beranda','Beranda'],['#pilar','Pilar'],['#modul','Modul'],['#harga','Harga'],['#fitur','Fitur'],['#alur','Cara Kerja']]" :key="item[0]" :href="item[0]" class="px-3 py-2 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">{{ item[1] }}</a>
        </nav>
        <Link href="/katalog" class="ml-2 lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Beli Sekarang</Link>
        <Link href="/login" class="glass rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:bg-white/15 transition">Masuk</Link>
      </div>
    </header>

    <section id="beranda" class="relative min-h-[min(100svh,820px)] brand-gradient text-white overflow-hidden flex items-center pt-20">
      <!--
        Rekamannya diputar sebagai latar, bukan hanya disimpan di balik
        tombol. Sebelumnya video hero sudah ada tetapi yang tergambar di
        sini hanya posternya, sehingga halaman depan tampak diam padahal
        berkasnya sudah terunduh.

        `muted` bukan pilihan gaya melainkan syarat: peramban menolak
        memutar video bersuara tanpa pengguna mengklik lebih dulu, dan
        penolakan itu tidak memunculkan galat — videonya hanya diam di
        bingkai pertama. `playsinline` menahan iOS membuka pemutar layar
        penuh di atas halaman.

        Posternya tetap dipasang: ia yang tampil selama video belum siap,
        dan yang menggantikannya sepenuhnya ketika pengguna meminta
        gerakan dikurangi.
      -->
      <video
        v-if="hero.video && !kurangiGerak"
        :src="hero.video" :poster="hero.poster ?? undefined"
        autoplay muted loop playsinline preload="metadata"
        aria-hidden="true"
        class="absolute inset-0 w-full h-full object-cover opacity-40"
      ></video>
      <img v-else-if="hero.poster" :src="hero.poster" alt="" class="absolute inset-0 w-full h-full object-cover opacity-35">

      <div class="absolute inset-0 bg-gradient-to-r from-cam-black/95 via-cam-black/65 to-cam-black/20"></div>

      <!-- Rautan halus di tepi bawah supaya potongan videonya tidak terbaca sebagai garis. -->
      <div class="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-cam-black to-transparent"></div>
      <div class="relative w-full max-w-6xl mx-auto px-5 py-16">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_300px] gap-8 lg:gap-10 items-center">
          <div>
            <span class="inline-flex items-center gap-2 glass rounded-full px-3.5 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.18em] text-cam-lime-light"><span class="w-1.5 h-1.5 rounded-full bg-cam-lime animate-pulse"></span>Delapan Aspek · Satu Platform</span>
            <h1 class="font-display text-[38px] sm:text-[46px] xl:text-[58px] font-black mt-5 leading-[1.06]">Keselamatan tambang,<span class="sheen block">terukur dan terbukti.</span></h1>
            <p class="text-[14px] md:text-[15.5px] mt-5 leading-relaxed max-w-xl text-white/75">Platform keselamatan pertambangan terpadu untuk pembelajaran, penilaian kinerja, inspeksi, kinerja energi, hingga sertifikasi — mengikuti regulasi keselamatan pertambangan Indonesia.</p>
            <div class="flex flex-wrap gap-2.5 mt-8">
              <Link href="/katalog" class="lime-gradient shadow-glow rounded-xl text-white px-6 py-3.5 text-[13.5px] font-bold">Beli Platform</Link>
              <Link href="/login" class="glass rounded-xl px-6 py-3.5 text-[13.5px] font-bold">Masuk ke Platform</Link>
              <a href="#modul" class="glass rounded-xl px-6 py-3.5 text-[13.5px] font-bold">Lihat Modul</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mt-10 max-w-2xl">
              <div v-for="stat in [[jumlahItem, 'Item penilaian'], [elemenSmkp.length, 'Elemen SMKP'], [modul.length, 'Modul terpadu'], ['24/7', 'Akses platform']]" :key="stat[1]" class="glass rounded-xl px-4 py-3.5"><div class="stat stat-sm text-cam-lime-light">{{ stat[0] }}</div><div class="text-[10.5px] text-white/55 mt-1.5">{{ stat[1] }}</div></div>
            </div>
          </div>
          <aside class="glass rounded-2xl p-5">
            <button v-if="hero.video" type="button" class="w-full flex items-center gap-3.5 text-left mb-5" @click="videoTerbuka = true"><span class="w-11 h-11 rounded-full lime-gradient grid place-items-center text-white text-xl">▶</span><span><span class="block text-[12.5px] font-bold">Tonton Video</span><span class="block text-[10.5px] text-white/50 mt-0.5">Operasional tambang · rekaman singkat</span></span></button>
            <h2 class="text-[13.5px] font-bold text-white">Mengapa EQOHSEE?</h2>
            <div class="space-y-4 mt-5"><div v-for="reason in [['Sesuai regulasi','Mengacu pada Kepdirjen 185.K/2019, SMKP Minerba, dan standar ISO.'],['Terpadu','Delapan aspek keselamatan terhubung dalam satu basis data.'],['Data langsung','Kinerja dan temuan terbaca saat itu juga, bukan menunggu rekap bulanan.'],['Mudah dipakai','Antarmuka sederhana, tetap terbaca pada jaringan site tambang.']]" :key="reason[0]" class="flex gap-3"><span class="shrink-0 w-8 h-8 rounded-lg lime-gradient grid place-items-center text-white">✓</span><div><div class="text-[12.5px] font-bold">{{ reason[0] }}</div><p class="text-[11px] text-white/45 mt-0.5 leading-relaxed">{{ reason[1] }}</p></div></div></div>
          </aside>
        </div>
      </div>
    </section>

    <section id="beranda-lanjut" class="relative bg-cam-ink text-white overflow-hidden scroll-mt-[66px]"><div class="max-w-6xl mx-auto px-5 py-14 md:py-16"><div class="grid lg:grid-cols-[minmax(0,400px)_minmax(0,1fr)] gap-8 lg:gap-10"><div><h3 class="font-display text-[22px] font-black">Kerangka SMKP Minerba</h3><p class="text-[12.5px] text-white/45 mt-1.5 leading-relaxed">Tujuh elemen wajib menurut Kepdirjen 185.K/37.04/DJB/2019, beserta bobot penilaiannya.</p><div class="grid grid-cols-2 gap-2.5 mt-5"><div v-for="(element, index) in elemenSmkp" :key="element.nama" class="glass rounded-xl p-3.5"><div class="flex justify-between"><span class="num text-[10.5px] text-cam-lime-light">{{ String(index + 1).padStart(2, '0') }}</span><span class="num text-[10px] text-white/40">{{ element.bobot }}%</span></div><div class="text-[11.5px] font-bold mt-2">{{ element.nama }}</div><div class="mt-2.5 h-1 rounded-full bg-white/10"><div class="h-full rounded-full lime-gradient" :style="{ width: `${Math.min(100, element.bobot * 2.9)}%` }"></div></div></div></div></div><div><h3 class="font-display text-[22px] font-black">Potret kegiatan lapangan</h3><div class="grid sm:grid-cols-2 gap-3 mt-5"><article v-for="item in galeri" :key="item.judul" class="rounded-2xl overflow-hidden bg-white/5 border border-white/10"><img v-if="item.gambarUrl" :src="item.gambarUrl" :alt="item.judul" class="w-full h-32 object-cover"><div v-else class="h-32 bg-gradient-to-br from-cam-panel to-cam-ink"></div><div class="p-3.5"><div class="text-[12.5px] font-bold">{{ item.judul }}</div><p class="text-[11px] text-white/45 mt-1 leading-relaxed">{{ item.ket }}</p><button v-if="item.videoUrl" type="button" class="text-[11px] text-cam-lime-light font-bold mt-2" @click="videoTerbuka = true">Putar video</button></div></article></div></div></div></div></section>

    <section class="relative overflow-hidden text-white bg-cam-lime-deep"><div class="max-w-6xl mx-auto px-5 py-12 md:py-14"><div class="grid lg:grid-cols-[260px_minmax(0,1fr)] gap-8 items-center"><div><h3 class="font-display text-[22px] font-black">{{ klien.length ? 'Terpercaya di Industri' : 'Mengacu pada Standar' }}</h3><p class="text-[12.5px] text-white/65 mt-1.5 leading-relaxed">{{ klien.length ? 'Dipakai perusahaan pertambangan di seluruh Indonesia.' : 'Setiap penilaian bersandar pada regulasi dan standar yang berlaku.' }}</p></div><div v-if="klien.length" class="flex flex-wrap items-center justify-start lg:justify-end gap-6"><img v-for="client in klien" :key="client.url" :src="client.url" :alt="client.nama" class="h-9 w-auto object-contain"></div><div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-2.5"><div v-for="item in standar" :key="item.kode" class="glass rounded-xl px-3.5 py-3"><div class="text-[11.5px] font-bold">{{ item.kode }}</div><div class="text-[10px] text-white/55 mt-1">{{ item.ket }}</div></div></div></div></div></section>

    <section id="tentang" class="bg-cam-ink text-white"><div class="max-w-6xl mx-auto px-5 py-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4"><div v-for="item in [['Sesuai regulasi','Mengacu pada standar dan regulasi resmi Indonesia.'],['Akses fleksibel','Berbasis web, terbuka dari kantor maupun dari site.'],['Dukungan penuh','Tim HSE siap membantu penerapan di lapangan.'],['Keamanan data','Data terenkripsi dalam pengiriman dan terpisah per perusahaan.']]" :key="item[0]" class="flex gap-3.5"><span class="shrink-0 w-9 h-9 rounded-xl grid place-items-center glass text-cam-lime-light">✓</span><div><div class="text-[12.5px] font-bold">{{ item[0] }}</div><p class="text-[11px] text-white/45 mt-1 leading-relaxed">{{ item[1] }}</p></div></div></div></section>

    <section id="pilar" class="relative bg-cam-ink text-white overflow-hidden"><div class="relative max-w-6xl mx-auto px-5 py-20 md:py-28"><div class="text-center max-w-2xl mx-auto"><span class="text-[10.5px] font-bold uppercase tracking-[0.28em] text-cam-lime-light">Kerangka Kerja</span><h2 class="font-display text-[32px] md:text-[46px] font-black mt-4">Delapan aspek, satu sistem</h2><p class="text-[13.5px] text-white/50 mt-4 leading-relaxed">Tujuh huruf pada <strong class="text-white/80">EQOHSEE</strong> mewakili satu aspek masing-masing, ditambah Konservasi Minerba di luar akronim.</p></div><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mt-12"><button v-for="[slug, item] in pilarList" :key="slug" type="button" class="text-left glass rounded-2xl p-5 hover:bg-white/15 transition-colors" :class="pilarTerpilih === slug ? 'ring-2 ring-cam-orange' : ''" @click="togglePilar(slug)"><div class="flex items-start gap-3.5"><span class="shrink-0 w-11 h-11 rounded-xl grid place-items-center text-white" :style="{ background: `linear-gradient(135deg, ${item.deep}, ${item.light})` }"><IkonPilar :nama="item.ikon" :ukuran="22" /></span><div><h3 class="text-[14.5px] font-bold">{{ item.nama }}</h3><p class="text-[12px] text-white/45 mt-1 leading-relaxed">{{ item.ket }}</p></div></div><div class="mt-4 text-[11px] font-bold text-cam-orange">{{ pilarTerpilih === slug ? 'Tutup rincian' : 'Lihat rincian' }}</div></button></div><div v-if="pilarTerpilih && pilar[pilarTerpilih]" class="mt-6 rounded-2xl glass p-6 md:p-8"><template v-for="[slug, item] in pilarList" :key="slug"><div v-if="slug === pilarTerpilih"><h3 class="font-display text-[24px] font-black">{{ item.nama }}</h3><p class="text-[13px] text-white/60 mt-4 max-w-2xl leading-relaxed">{{ item.ringkas }}</p><div class="grid md:grid-cols-3 gap-4 mt-6"><div v-for="coverage in item.cakupan" :key="coverage[0]" class="border-l-2 pl-3" :style="{ borderColor: item.light }"><div class="text-[12.5px] font-bold">{{ coverage[0] }}</div><div class="text-[12px] text-white/45 mt-1 leading-relaxed">{{ coverage[1] }}</div></div></div><div class="flex flex-wrap gap-1.5 mt-6"><span v-for="module in item.modul" :key="module" class="text-[10.5px] font-semibold rounded-full px-2.5 py-1" :style="{ backgroundColor: `${item.warna}33`, color: item.light }">{{ module }}</span></div></div></template></div></div></section>

    <section id="modul" class="max-w-6xl mx-auto px-5 py-16 md:py-20"><div class="text-center max-w-xl mx-auto"><span class="text-[10.5px] font-bold uppercase tracking-[0.22em] text-cam-lime-dark">Aplikasi di Dalamnya</span><h2 class="font-display text-[30px] md:text-[38px] font-black text-cam-ink mt-3">{{ modul.length }} modul, satu akun</h2><p class="text-[13.5px] text-stone-500 mt-3 leading-relaxed">{{ modul.filter(item => item.status === 'aktif').length }} modul sudah aktif dan siap dipakai. Semua modul berbagi data perusahaan, pengguna, dan peran yang sama.</p></div><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mt-10"><component :is="item.url ? 'a' : 'div'" v-for="item in modul" :key="item.nama" :href="item.url ?? undefined" class="group relative bg-white rounded-2xl shadow-card border border-stone-100 p-6 hover:shadow-lg transition"><span class="absolute inset-x-0 top-0 h-[3px]" :style="{ background: item.url ? `linear-gradient(90deg, ${item.pilarDeep}, ${item.pilarWarna})` : '#E7E5E4' }"></span><div class="w-11 h-11 rounded-xl grid place-items-center" :class="item.url ? 'text-white' : 'text-stone-400'" :style="{ background: item.url ? `linear-gradient(135deg, ${item.pilarDeep}, ${item.pilarWarna})` : '#E7E5E4' }"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="item.ikon" /></svg></div><div class="flex flex-wrap items-center gap-2 mt-4"><h3 class="text-[14.5px] font-bold text-cam-ink">{{ item.nama }}</h3><span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wide" :class="item.status === 'aktif' ? 'bg-cam-lime-soft text-cam-lime-deep' : 'bg-stone-100 text-stone-400'">{{ item.status === 'aktif' ? 'Aktif' : 'Segera' }}</span></div><p class="text-[12.5px] text-stone-500 mt-2 leading-relaxed">{{ item.ket }}</p><div class="text-[11px] font-bold mt-4" :style="{ color: item.pilarDeep }">Pilar {{ item.pilarNama }}</div></component></div></section>

    <!-- ══════════ HARGA ══════════

         Bentuknya sengaja sama dengan /katalog: label monospace, judul
         tebal, kartu putih bergaris rambut. Dua halaman yang menjual
         barang yang sama tidak boleh terlihat berasal dari dua
         perusahaan berbeda — dan yang membacanya berpindah di antara
         keduanya dalam satu klik.

         Halaman depan menjawab "berapa kira-kira", bukan "berapa
         tepatnya untuk tiap butir": daftar harga lengkapnya di /katalog.
         Disalin ke sini, dua tempat harus sama-sama diperbarui — dan yang
         terjadi cepat atau lambat adalah dua harga berbeda untuk satu
         barang yang sama, keduanya tercetak di situs yang sama. -->
    <section id="harga" class="jual-lugas scroll-mt-[66px]">
      <div class="jual-lebar py-16 md:py-24">
        <div class="max-w-xl">
          <p class="jual-mata jual-mata-aksen">Pembelian</p>
          <h2 class="jual-h2">Miliki platformnya</h2>
          <p class="jual-tubuh mt-5">
            Ambil paket menyeluruh, atau beli aplikasi satuan yang benar-benar Anda pakai.
            Pemesanannya tidak menuntut akun, dan pembayarannya lewat QRIS.
          </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 mt-10 max-w-4xl">
          <!-- paket menyeluruh -->
          <div class="jual-kartu flex flex-col">
            <span class="jual-pita">Paling lengkap</span>
            <h3 class="jual-h4 mt-4">Paket menyeluruh</h3>
            <p class="jual-tubuh-kecil mt-2">
              Seluruh {{ modul.length }} aplikasi, pembaruan, dan pendampingan pemasangan.
            </p>

            <p v-if="jual.paket" class="jual-angka mt-6">
              <span class="jual-angka-kecil">Rp</span>{{ jual.paket.harga.toLocaleString('id-ID') }}
            </p>
            <p v-if="jual.paket" class="jual-tubuh-kecil mt-2">{{ jual.paket.masa }}</p>
            <p v-else class="jual-h4 mt-6">Sesuai kebutuhan</p>

            <Link href="/katalog" class="jual-tombol w-full mt-7">
              {{ jual.paket ? 'Beli paket' : 'Minta penawaran' }}
            </Link>
          </div>

          <!-- satuan -->
          <div class="jual-kartu flex flex-col">
            <span class="jual-pita jual-pita-sunyi">Bertahap</span>
            <h3 class="jual-h4 mt-4">Aplikasi satuan</h3>
            <p class="jual-tubuh-kecil mt-2">
              Mulai dari satu aplikasi, tambahkan yang lain kapan saja — datanya menyatu sendiri.
            </p>

            <p v-if="jual.termurah !== null" class="jual-angka mt-6">
              <span class="jual-angka-kecil">Rp</span>{{ jual.termurah.toLocaleString('id-ID') }}
            </p>
            <p v-if="jual.termurah !== null" class="jual-tubuh-kecil mt-2">
              harga mulai · {{ jual.jumlah }} aplikasi siap dibeli
            </p>
            <p v-else class="jual-h4 mt-6">Sesuai kebutuhan</p>

            <Link href="/katalog" class="jual-tombol jual-tombol-lain w-full mt-7">Lihat katalog</Link>
          </div>
        </div>

        <div class="flex flex-wrap gap-x-8 gap-y-3 mt-9">
          <span v-for="t in jaminan" :key="t.teks" class="inline-flex items-center gap-2 jual-tubuh-kecil">
            <span class="jual-centang">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m5 12.5 4.5 4.5L19 7" />
              </svg>
            </span>
            {{ t.teks }}
          </span>
        </div>
      </div>
    </section>

    <section id="fitur" class="relative bg-gradient-to-b from-cam-bg via-white to-cam-bg"><div class="max-w-6xl mx-auto px-5 py-16 md:py-20"><div class="text-center max-w-xl mx-auto"><span class="text-[10.5px] font-bold uppercase tracking-[0.22em] text-cam-lime-dark">Fitur Unggulan</span><h2 class="font-display text-[30px] md:text-[38px] font-black text-cam-ink mt-3">Dibuat untuk lapangan, bukan sekadar laporan</h2></div><div class="grid gap-x-8 gap-y-9 sm:grid-cols-2 lg:grid-cols-3 mt-11"><div v-for="(item, index) in fitur" :key="item.judul" class="flex gap-4"><div class="w-10 h-10 rounded-xl grid place-items-center shrink-0 text-white shadow-sm lime-gradient">{{ index + 1 }}</div><div><h3 class="text-[13.5px] font-bold text-cam-ink">{{ item.judul }}</h3><p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed">{{ item.ket }}</p></div></div></div></div></section>

    <section id="alur" class="max-w-5xl mx-auto px-5 py-16 md:py-20"><div class="text-center max-w-xl mx-auto"><span class="text-[10.5px] font-bold uppercase tracking-[0.22em] text-cam-lime-dark">Cara Kerja</span><h2 class="font-display text-[30px] md:text-[38px] font-black text-cam-ink mt-3">Empat langkah, satu siklus</h2></div><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mt-10"><div v-for="(item, index) in alur" :key="item.judul" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5"><div class="stat stat-sm text-cam-lime/35">{{ String(index + 1).padStart(2, '0') }}</div><h3 class="text-[13.5px] font-bold text-cam-ink mt-2">{{ item.judul }}</h3><p class="text-[12px] text-stone-500 mt-1.5 leading-relaxed">{{ item.ket }}</p></div></div></section>

    <section class="max-w-6xl mx-auto px-5 pb-16 md:pb-20"><div class="brand-gradient rounded-3xl p-9 md:p-14 text-white text-center shadow-card"><h2 class="font-display text-[28px] md:text-[38px] font-black">Siap menaikkan level keselamatan?</h2><p class="text-[13.5px] text-white/55 mt-3 max-w-md mx-auto">Ambil paketnya, atau mulai dari satu aplikasi yang paling dibutuhkan lebih dulu.</p><div class="flex flex-wrap justify-center gap-2.5 mt-7"><Link href="/katalog" class="inline-block lime-gradient shadow-glow rounded-xl text-white px-7 py-3 text-[13.5px] font-bold">Lihat Katalog</Link><Link href="/login" class="inline-block glass rounded-xl text-white px-7 py-3 text-[13.5px] font-bold">Masuk ke Platform</Link></div></div></section>
    <footer class="border-t border-stone-100 bg-white"><div class="max-w-6xl mx-auto px-5 py-7 flex flex-wrap items-center justify-between gap-3"><div class="font-extrabold tracking-wide text-lg">E<span class="text-cam-orange">Q</span>OHSEE</div><p class="text-[11.5px] text-stone-400">Platform Terpadu Keselamatan Pertambangan · {{ tahun }}</p></div></footer>
  </div>

  <div v-if="videoTerbuka && hero.video" class="fixed inset-0 z-50 grid place-items-center bg-black/85 p-5" @click.self="videoTerbuka = false"><div class="w-full max-w-4xl"><div class="flex items-center justify-between mb-3"><span class="text-[13px] font-bold text-white">Operasional Tambang</span><button type="button" class="text-white text-xl" @click="videoTerbuka = false">×</button></div><video :src="hero.video" :poster="hero.poster ?? undefined" class="w-full rounded-2xl" controls autoplay playsinline></video></div></div>
</template>
