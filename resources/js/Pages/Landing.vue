<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
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
 * Kepala berubah setelah halaman digulir.
 *
 * Diambil dari scrollY, bukan dari IntersectionObserver pada unsur
 * pengintai: pengintai setinggi nol piksel tidak pernah memicu apa pun
 * pada sebagian peramban.
 */
const digulir = ref(false);
let onScroll: (() => void) | null = null;

onMounted(() => {
  onScroll = () => { digulir.value = window.scrollY > 24; };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
  if (onScroll) window.removeEventListener('scroll', onScroll);
});

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
/**
 * Warna dan ikon untuk keenam kartu fitur.
 *
 * Fitur tidak menempel pada satu pilar tertentu, jadi warnanya diputar
 * dari palet pilar yang sudah ada — bukan dikarang baru. Halaman yang
 * memakai dua kumpulan warna berbeda untuk hal yang setara terbaca
 * sebagai dua halaman yang ditempel.
 */
const warnaFitur = ['#F57C00', '#1E88E5', '#16883F', '#7E57C2', '#C2410C', '#0891B2'];

const ikonFitur: string[][] = [
  ['M12 2.8 4.6 5.7v5.6c0 4.5 3.1 8.6 7.4 9.9 4.3-1.3 7.4-5.4 7.4-9.9V5.7L12 2.8Z',
   'm8.9 11.9 2.2 2.2 4-4.4'],
  ['M4 5.5h16v13H4zM4 9.5h16', 'M8 13h8M8 16h5'],
  ['M12 3.5 3.5 8l8.5 4.5L20.5 8 12 3.5Z', 'M3.5 12 12 16.5 20.5 12', 'M3.5 16 12 20.5 20.5 16'],
  ['M16.5 20v-1.6a3.4 3.4 0 0 0-3.4-3.4H6.9a3.4 3.4 0 0 0-3.4 3.4V20',
   'M10 11.6a3.6 3.6 0 1 0 0-7.2 3.6 3.6 0 0 0 0 7.2Z', 'M17 8.5h4'],
  ['M7 11V8a5 5 0 0 1 10 0v3', 'M5.5 11h13v9.5h-13z', 'M12 15v2.5'],
  ['M3.5 3.5h6.5v6.5H3.5zM14 3.5h6.5v6.5H14zM3.5 14h6.5v6.5H3.5zM14 14h6.5v6.5H14z'],
];

/**
 * Angka contoh untuk tiruan dasbor di hero.
 *
 * Ketiganya memang yang dihitung EQOHSEE — laporan bahaya, temuan yang
 * belum tuntas, dan kepatuhan MCU. Bukan angka karangan tentang hal yang
 * tidak ada: yang dipajang sebagai layar produk harus berupa layar yang
 * benar-benar dapat dibuka.
 */
const angkaDasbor = [
  { nama: 'Hazard dilaporkan', nilai: '142', delta: '+18', baik: true },
  { nama: 'Temuan terbuka', nilai: '23', delta: '−7', baik: true },
  { nama: 'Kepatuhan MCU', nilai: '96%', delta: '', baik: true },
];

/** Tinggi batang bagan, persen. Bentuk, bukan data. */
const batangDasbor = [38, 52, 44, 61, 49, 72, 58, 83, 66, 91, 74, 88];

/**
 * Warna untuk ketujuh elemen SMKP.
 *
 * Diambil dari palet pilar yang sudah ada, bukan dikarang baru — halaman
 * yang memakai dua kumpulan warna berbeda untuk hal yang setara terbaca
 * sebagai dua halaman yang ditempel.
 */
/* Ketujuhnya harus dapat dibedakan SATU SAMA LAIN, bukan sekadar enak
   dipandang berdampingan. Susunan sebelumnya memakai jingga untuk
   Kebijakan dan jingga tua untuk Implementasi: pada bilah keduanya tidak
   bersebelahan, sehingga membaca daftarnya menuntut mencocokkan dua
   warna yang sekilas sama — persis pekerjaan yang seharusnya dihapus
   oleh warna. */
const warnaSmkp = ['#F57C00', '#2D8CF0', '#16A34A', '#8B5CF6', '#06B6D4', '#F43F5E', '#EAB308'];

const tentang: [string, string][] = [
  ['Sesuai regulasi', 'Mengacu pada standar dan regulasi resmi Indonesia.'],
  ['Akses fleksibel', 'Berbasis web, terbuka dari kantor maupun dari site.'],
  ['Dukungan penuh', 'Tim HSE siap membantu penerapan di lapangan.'],
  ['Keamanan data', 'Data terenkripsi dalam pengiriman dan terpisah per perusahaan.'],
];

const ikonTentang: string[][] = [
  ['M9 3.5h9.5v17H5.5v-14', 'M5.5 6.5 9 3.5v3H5.5Z', 'M9 11h6M9 14.5h6'],
  ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M3.2 12h17.6', 'M12 3.2a14 14 0 0 1 0 17.6a14 14 0 0 1 0-17.6'],
  ['M16.5 20v-1.6a3.4 3.4 0 0 0-3.4-3.4H6.9a3.4 3.4 0 0 0-3.4 3.4V20',
   'M10 11.6a3.6 3.6 0 1 0 0-7.2 3.6 3.6 0 0 0 0 7.2Z', 'M20.5 20v-1.6a3.4 3.4 0 0 0-2.5-3.3'],
  ['M7 11V8a5 5 0 0 1 10 0v3', 'M5.5 11h13v9.5h-13z', 'M12 15v2.5'],
];

const alasanHero: [string, string][] = [
  ['Sesuai regulasi', 'Mengacu pada Kepdirjen 185.K/2019, SMKP Minerba, dan standar ISO.'],
  ['Terpadu', 'Delapan aspek keselamatan terhubung dalam satu basis data.'],
  ['Data langsung', 'Kinerja dan temuan terbaca saat itu juga, bukan menunggu rekap bulanan.'],
  ['Mudah dipakai', 'Antarmuka sederhana, tetap terbaca pada jaringan site tambang.'],
];

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

  <div class="jual-lugas scroll-smooth">
    <!-- Kepala memakai kosakata yang sama dengan /katalog: mengambang
         tanpa garis di atas rekaman, lalu mendapat kertas dan garis bawah
         sesudah digulir. Yang berubah warnanya saja — kepala yang
         menyusut menggeser seluruh halaman tepat saat orang membaca. -->
    <!-- TANPA .jual-lugas di sini. Peubahnya sudah diwarisi dari
         pembungkus halaman; menambahkannya lagi ikut membawa latar
         terangnya, sehingga kepala yang seharusnya mengambang bening di
         atas rekaman tergambar putih dengan tulisan putih di atasnya —
         terbaca sebagai menu yang hilang, bukan sebagai warna yang
         keliru. -->
    <header class="jual-kepala" :class="digulir ? 'jual-kepala-turun' : ''">
      <div class="jual-lebar flex items-center gap-8 h-full">
        <Link href="/" class="shrink-0 opacity-90 hover:opacity-100 transition-opacity duration-300">
          <Wordmark :tinggi="26" />
        </Link>

        <nav class="ml-auto hidden md:flex items-center gap-7">
          <a v-for="item in [['#pilar','Pilar'],['#modul','Modul'],['#harga','Harga'],['#fitur','Fitur'],['#alur','Cara kerja']]"
             :key="item[0]" :href="item[0]" class="jual-nav">{{ item[1] }}</a>
        </nav>

        <Link href="/login" class="jual-nav ml-auto md:ml-0">Masuk</Link>
        <Link href="/katalog" class="jual-tombol jual-tombol-kecil"
              :class="digulir ? '' : 'jual-tombol-terang'">Beli sekarang</Link>
      </div>
    </header>

    <!-- ══════════ HERO ══════════ -->
    <section id="beranda" class="jual-lugas jual-lugas-gelap relative overflow-hidden">
      <!--
        `muted` bukan pilihan gaya melainkan syarat: peramban menolak
        memutar video bersuara tanpa pengguna mengklik lebih dulu, dan
        penolakan itu tidak memunculkan galat — videonya hanya diam di
        bingkai pertama. `playsinline` menahan iOS membuka pemutar layar
        penuh di atas halaman.
      -->
      <video v-if="hero.video && !kurangiGerak"
             :src="hero.video" :poster="hero.poster ?? undefined"
             autoplay muted loop playsinline preload="metadata" aria-hidden="true"
             class="absolute inset-0 w-full h-full object-cover opacity-[0.3]"></video>
      <img v-else-if="hero.poster" :src="hero.poster" alt=""
           class="absolute inset-0 w-full h-full object-cover opacity-[0.26]">

      <div class="absolute inset-0"
           style="background:linear-gradient(102deg,#12161AF7 0%,#12161AE0 46%,#12161A99 100%)"></div>

      <div class="jual-lebar relative">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_20rem] gap-x-14 gap-y-12 items-center
                    pt-32 pb-16 md:pt-40 md:pb-24">
          <div class="max-w-[40rem]">
            <p class="jual-mata jual-mata-terang">Delapan aspek · satu platform</p>

            <h1 class="jual-judul mt-6">
              Keselamatan tambang,<br>
              <span class="jual-judul-tipis">terukur dan terbukti.</span>
            </h1>

            <p class="jual-tubuh-besar jual-tubuh-terang mt-7 max-w-xl">
              Platform keselamatan pertambangan terpadu untuk pembelajaran, penilaian kinerja,
              inspeksi, kinerja energi, hingga sertifikasi — mengikuti regulasi keselamatan
              pertambangan Indonesia.
            </p>

            <div class="flex flex-wrap items-center gap-3 mt-9">
              <Link href="/katalog" class="jual-tombol jual-tombol-terang">Beli platform</Link>
              <Link href="/login" class="jual-tombol jual-tombol-garis">Masuk ke platform</Link>
            </div>

            <div class="jual-statistik mt-12 max-w-2xl">
              <div v-for="s in [[jumlahItem, 'Item penilaian'], [elemenSmkp.length, 'Elemen SMKP'],
                                [modul.length, 'Modul terpadu'], ['24/7', 'Akses platform']]"
                   :key="String(s[1])">
                <b>{{ s[0] }}</b><span>{{ s[1] }}</span>
              </div>
            </div>
          </div>

          <!-- Potongan produk yang sesungguhnya. Sebelum ini
               satu-satunya gambar produk di seluruh situs ada di
               /katalog, bukan di halaman yang dilihat lebih dulu.

               Angkanya contoh, dan terbaca sebagai contoh: tanpa nama
               perusahaan dan tanpa klaim, hanya bentuk layarnya. -->
          <aside class="jual-dasbor">
            <div class="jual-dasbor-kepala">
              <span class="jual-dasbor-judul">Dasbor HSE</span>
              <span class="jual-dasbor-masa">Sep 2026</span>
            </div>

            <div v-for="a in angkaDasbor" :key="a.nama" class="jual-dasbor-angka">
              <span>{{ a.nama }}</span>
              <span class="jual-dasbor-nilai">
                <b>{{ a.nilai }}</b>
                <span v-if="a.delta" class="jual-delta"
                      :class="a.baik ? 'jual-delta-naik' : 'jual-delta-turun'">{{ a.delta }}</span>
              </span>
            </div>

            <div class="jual-dasbor-bagan" aria-hidden="true">
              <i v-for="(t, k) in batangDasbor" :key="k" :style="{ height: t + '%' }"></i>
            </div>
            <div class="jual-dasbor-kaki">
              <span>Hazard dilaporkan · 12 bulan</span>
              <span class="num">2025–2026</span>
            </div>
          </aside>
        </div>
      </div>
    </section>

    <!-- ══════════ SMKP ══════════ -->
    <section id="beranda-lanjut" class="jual-lugas jual-lugas-gelap scroll-mt-[66px]"
             style="border-top:1px solid rgba(255,255,255,.08)">
      <div class="jual-lebar py-16 md:py-20">
        <div class="grid lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] gap-x-14 gap-y-10 items-start">
          <div>
            <p class="jual-mata jual-mata-terang">Kerangka</p>
            <h2 class="jual-h2 jual-h2-terang">SMKP Minerba</h2>
            <p class="jual-tubuh jual-tubuh-terang mt-4">
              Tujuh elemen wajib menurut Kepdirjen 185.K/37.04/DJB/2019. Bobotnya berjumlah
              tepat seratus — dan Implementasi sendirian menanggung sepertiganya.
            </p>
          </div>

          <div>
            <!-- Satu bilah utuh yang dibagi tujuh, bukan tujuh bilah yang
                 masing-masing punya seratus persennya sendiri. Yang
                 menarik dari angka ini justru perbandingannya. -->
            <div class="jual-takaran">
              <i v-for="(e, k) in elemenSmkp" :key="e.nama"
                 :style="{ width: e.bobot + '%', background: warnaSmkp[k % warnaSmkp.length] }"
                 :title="`${e.nama} — ${e.bobot}%`"></i>
            </div>

            <ul class="jual-takaran-daftar">
              <li v-for="(e, k) in elemenSmkp" :key="e.nama">
                <span class="jual-takaran-titik"
                      :style="{ background: warnaSmkp[k % warnaSmkp.length] }"></span>
                <span class="jual-takaran-nama">{{ e.nama }}</span>
                <span class="jual-takaran-bobot num">{{ e.bobot }}%</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ GALERI ══════════ -->
    <section class="jual-lugas jual-lugas-gelap"
             style="border-top:1px solid rgba(255,255,255,.08)">
      <div class="jual-lebar py-16 md:py-20">
        <div class="flex flex-wrap items-end justify-between gap-4 max-w-3xl">
          <div>
            <p class="jual-mata jual-mata-terang">Lapangan</p>
            <h2 class="jual-h2 jual-h2-terang">Potret kegiatan</h2>
          </div>
        </div>

        <div class="jual-pita-galeri mt-9">
          <article v-for="g in galeri" :key="g.judul">
            <!-- Pita bawah foto dipotong: berkas galerinya membawa
                 tulisan dan lencana penyunting yang terbakar di dalam
                 gambarnya. Lihat catatan pada .jual-aspek-foto. -->
            <span class="jual-galeri-bingkai">
              <img v-if="g.gambarUrl" :src="g.gambarUrl" alt="" loading="lazy" decoding="async"
                   class="jual-galeri-foto">
            </span>
            <div class="mt-3.5">
              <div class="text-[13px] font-bold">{{ g.judul }}</div>
              <p class="jual-tubuh-kecil jual-tubuh-terang mt-1">{{ g.ket }}</p>
              <button v-if="g.videoUrl" type="button" class="jual-tautan jual-tautan-terang mt-2.5"
                      @click="videoTerbuka = true">Putar video</button>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- ══════════ STANDAR / KLIEN ══════════ -->
    <section class="jual-lugas" style="border-bottom:1px solid #E3E7E2">
      <div class="jual-lebar py-12 md:py-14">
        <div class="grid lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)] gap-x-12 gap-y-7 items-center">
          <div>
            <p class="jual-mata jual-mata-aksen">{{ klien.length ? 'Dipercaya' : 'Acuan' }}</p>
            <h3 class="jual-h4 mt-2">
              {{ klien.length ? 'Terpercaya di industri' : 'Mengacu pada standar' }}
            </h3>
            <p class="jual-tubuh-kecil mt-2">
              {{ klien.length
                ? 'Dipakai perusahaan pertambangan di seluruh Indonesia.'
                : 'Setiap penilaian bersandar pada regulasi dan standar yang berlaku.' }}
            </p>
          </div>

          <div v-if="klien.length" class="flex flex-wrap items-center gap-8 lg:justify-end">
            <img v-for="c in klien" :key="c.url" :src="c.url" :alt="c.nama"
                 class="h-8 w-auto object-contain opacity-70">
          </div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
            <div v-for="x in standar" :key="x.kode" class="jual-kartu" style="padding:.85rem 1rem">
              <div class="text-[12px] font-bold">{{ x.kode }}</div>
              <div class="jual-tubuh-kecil mt-1">{{ x.ket }}</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ PILAR ══════════ -->
    <section id="pilar" class="jual-lugas jual-lugas-gelap scroll-mt-[66px]">
      <div class="jual-lebar py-16 md:py-24">
        <div class="max-w-2xl">
          <p class="jual-mata jual-mata-terang">Kerangka kerja</p>
          <h2 class="jual-h2 jual-h2-terang">Delapan aspek, satu sistem</h2>
          <p class="jual-tubuh jual-tubuh-terang mt-5">
            Tujuh huruf pada <strong class="text-white">EQOHSEE</strong> mewakili satu aspek
            masing-masing, ditambah Konservasi Minerba di luar akronim.
          </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mt-10">
          <button v-for="[slug, item] in pilarList" :key="slug" type="button"
                  class="jual-gelap-kartu text-left"
                  :class="pilarTerpilih === slug ? 'jual-gelap-kartu-aktif' : ''"
                  @click="togglePilar(slug)">
            <span class="jual-tanda-gelap" :style="{ '--c': item.warna }">
              <IkonPilar :nama="item.ikon" :ukuran="20" />
            </span>
            <h3 class="text-[14px] font-bold mt-4">{{ item.nama }}</h3>
            <p class="jual-tubuh-kecil jual-tubuh-terang mt-1.5">{{ item.ket }}</p>
            <span class="block text-[11.5px] font-bold mt-4"
                  :style="{ color: pilarTerpilih === slug ? '#F57C00' : item.light }">
              {{ pilarTerpilih === slug ? 'Tutup rincian' : 'Lihat rincian' }}
            </span>
          </button>
        </div>

        <div v-if="pilarTerpilih && pilar[pilarTerpilih]" class="jual-gelap-kartu mt-3"
             style="padding:1.75rem">
          <template v-for="[slug, item] in pilarList" :key="slug">
            <div v-if="slug === pilarTerpilih">
              <h3 class="jual-h3">{{ item.nama }}</h3>
              <p class="jual-tubuh jual-tubuh-terang mt-3 max-w-2xl">{{ item.ringkas }}</p>

              <div class="grid md:grid-cols-3 gap-5 mt-6">
                <div v-for="c in item.cakupan" :key="c[0]" class="pl-3"
                     :style="{ borderLeft: `2px solid ${item.warna}` }">
                  <div class="text-[12.5px] font-bold">{{ c[0] }}</div>
                  <div class="jual-tubuh-kecil jual-tubuh-terang mt-1">{{ c[1] }}</div>
                </div>
              </div>

              <div class="flex flex-wrap gap-1.5 mt-6">
                <span v-for="m in item.modul" :key="m" class="jual-label"
                      :style="{ background: `${item.warna}26`, color: item.light }">{{ m }}</span>
              </div>
            </div>
          </template>
        </div>
      </div>
    </section>

    <!-- ══════════ MODUL ══════════ -->
    <section id="modul" class="jual-lugas scroll-mt-[66px]">
      <div class="jual-lebar py-16 md:py-24">
        <div class="max-w-2xl">
          <p class="jual-mata jual-mata-aksen">Aplikasi di dalamnya</p>
          <h2 class="jual-h2">{{ modul.length }} modul, satu akun</h2>
          <p class="jual-tubuh mt-5">
            {{ modul.filter((m) => m.status === 'aktif').length }} modul sudah aktif dan siap
            dipakai. Semua modul berbagi data perusahaan, pengguna, dan peran yang sama.
          </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mt-10">
          <component :is="item.url ? 'a' : 'div'" v-for="item in modul" :key="item.nama"
                     :href="item.url ?? undefined" class="jual-modul"
                     :class="item.url ? '' : 'jual-modul-mati'"
                     :style="{ '--c': item.url ? item.pilarWarna : '#D4DAD3' }">
            <div class="flex items-start justify-between gap-3">
              <span class="jual-tanda" :style="{ '--c': item.url ? item.pilarWarna : '#9AA3A0' }">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path :d="item.ikon" />
                </svg>
              </span>
              <span class="jual-status"
                    :class="item.status === 'aktif' ? 'jual-status-hidup' : 'jual-status-nanti'">
                {{ item.status === 'aktif' ? 'Aktif' : 'Segera' }}
              </span>
            </div>

            <h3 class="text-[14.5px] font-bold mt-4" style="letter-spacing:-.014em">{{ item.nama }}</h3>
            <p class="jual-tubuh-kecil mt-2 flex-1">{{ item.ket }}</p>

            <span class="jual-label mt-4 self-start" :style="{ '--c': item.pilarWarna }">
              {{ item.pilarNama }}
            </span>
          </component>
        </div>
      </div>
    </section>

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

    <!-- ══════════ FITUR ══════════ -->
    <section id="fitur" class="jual-lugas scroll-mt-[66px]" style="border-top:1px solid #E3E7E2">
      <div class="jual-lebar py-16 md:py-24">
        <div class="max-w-2xl">
          <p class="jual-mata jual-mata-aksen">Fitur unggulan</p>
          <h2 class="jual-h2">Dibuat untuk lapangan, bukan sekadar laporan</h2>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mt-10">
          <div v-for="(item, i) in fitur" :key="item.judul" class="jual-kartu"
               :style="{ '--c': warnaFitur[i % warnaFitur.length] }">
            <span class="jual-tanda">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path v-for="(d, k) in ikonFitur[i % ikonFitur.length]" :key="k" :d="d" />
              </svg>
            </span>
            <h3 class="jual-h4 mt-4">{{ item.judul }}</h3>
            <p class="jual-tubuh-kecil mt-2">{{ item.ket }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ ALUR ══════════ -->
    <section id="alur" class="jual-lugas scroll-mt-[66px]" style="background:#FFFFFF">
      <div class="jual-lebar py-16 md:py-24">
        <div class="max-w-2xl">
          <p class="jual-mata jual-mata-aksen">Cara kerja</p>
          <h2 class="jual-h2">Empat langkah, satu siklus</h2>
        </div>

        <!-- Rel penghubung hanya digambar pada lebar yang benar-benar
             menampung empat kolom sejajar. Pada dua kolom ia akan
             menyambungkan langkah 2 ke langkah 3 yang berada di baris
             berbeda — menggambar urutan yang tidak pernah terjadi. -->
        <div class="jual-alur mt-10">
          <span class="jual-alur-rel" aria-hidden="true"></span>
          <div v-for="(item, i) in alur" :key="item.judul" class="jual-alur-butir">
            <span class="jual-langkah-angka">{{ String(i + 1).padStart(2, '0') }}</span>
            <h3 class="jual-h4 mt-4">{{ item.judul }}</h3>
            <p class="jual-tubuh-kecil mt-2">{{ item.ket }}</p>
          </div>
        </div>

        <!-- Tanpa bingkai kartu, dan sengaja.

             Sebelum ini halaman memuat enam kisi kartu berturut-turut —
             pilar, modul, harga, fitur, alur, tentang — dan keseragaman
             sepanjang itu terbaca sebagai halaman yang tidak dipikirkan,
             berapa pun rapinya tiap kartu. Empat butir terakhir ini tidak
             menuntut bingkai untuk dapat dibaca. -->
        <div id="tentang" class="grid gap-x-8 gap-y-8 sm:grid-cols-2 lg:grid-cols-4
                                 mt-16 pt-12 scroll-mt-[66px]"
             style="border-top:1px solid #E3E7E2">
          <div v-for="(t, i) in tentang" :key="t[0]">
            <span class="jual-tanda" :style="{ '--c': warnaFitur[i % warnaFitur.length] }">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path v-for="(d, k) in ikonTentang[i]" :key="k" :d="d" />
              </svg>
            </span>
            <h3 class="jual-h4 mt-4">{{ t[0] }}</h3>
            <p class="jual-tubuh-kecil mt-2">{{ t[1] }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ AJAKAN PENUTUP ══════════ -->
    <section class="jual-lugas" style="background:#FFFFFF">
      <div class="jual-lebar pb-16 md:pb-24">
        <div class="jual-lugas jual-lugas-gelap rounded-2xl px-7 py-12 md:px-14 md:py-16">
          <div class="max-w-2xl">
            <p class="jual-mata jual-mata-terang">Mulai</p>
            <h2 class="jual-h2 jual-h2-terang">Siap menaikkan level keselamatan?</h2>
            <p class="jual-tubuh jual-tubuh-terang mt-5">
              Ambil paketnya, atau mulai dari satu aplikasi yang paling dibutuhkan lebih dulu.
              Pemesanannya tidak menuntut akun.
            </p>
            <div class="flex flex-wrap items-center gap-3 mt-8">
              <Link href="/katalog" class="jual-tombol jual-tombol-terang">Lihat katalog</Link>
              <Link href="/login" class="jual-tombol jual-tombol-garis">Masuk ke platform</Link>
            </div>
          </div>
        </div>
      </div>
    </section>

    <footer class="jual-lugas" style="background:#FFFFFF;border-top:1px solid #E3E7E2">
      <div class="jual-lebar py-8 flex flex-wrap items-center justify-between gap-4">
        <Wordmark :tinggi="20" />
        <p class="jual-tubuh-kecil">
          Platform Terpadu Keselamatan Pertambangan · {{ tahun }}
        </p>
      </div>
    </footer>
  </div>

  <div v-if="videoTerbuka && hero.video" class="fixed inset-0 z-50 grid place-items-center bg-black/85 p-5" @click.self="videoTerbuka = false"><div class="w-full max-w-4xl"><div class="flex items-center justify-between mb-3"><span class="text-[13px] font-bold text-white">Operasional Tambang</span><button type="button" class="text-white text-xl" @click="videoTerbuka = false">×</button></div><video :src="hero.video" :poster="hero.poster ?? undefined" class="w-full rounded-2xl" controls autoplay playsinline></video></div></div>
</template>
