<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BlankLayout from '../Layouts/BlankLayout.vue';
import Wordmark from '../Components/Wordmark.vue';
import IkonPilar from '../Components/IkonPilar.vue';
import IkonPadat from '../Components/IkonPadat.vue';
import { ikonPadat, type JalurPadat } from '../ikonPadat';
import { bolehLatarVideo } from '../latarVideo';

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
  /* Glyph padat untuk ubin berdimensi; `ikon` yang lama tetap ada
     karena halaman /pilar masih menggambarnya sebagai garis. */
  ikonPadat: JalurPadat[];
};

const props = defineProps<{
  hero: { video: string | null; poster: string | null };
  galeri: { judul: string; ket: string; aspek: string; gambarUrl: string | null; videoUrl: string | null }[];
  klien: { nama: string; url: string }[];
  standar: { kode: string; ket: string }[];
  elemenSmkp: { kode: string; nama: string; bobot: number; modul: string }[];
  smkpAngka: { poin: number; butir: number };
  masalah: { judul: string; ket: string; sumber: string }[];
  aman: { judul: string; ket: string }[];
  tanya: { t: string; j: string }[];
  kontak: { whatsapp: string; email: string };
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

/* Tiga foto galeri pertama yang benar-benar punya gambar.
   Disaring, bukan diambil tiga teratas begitu saja: kartu galeri boleh
   saja bergambar null, dan <img src="null"> menggambar ikon gambar
   rusak — tepat di sebelah angka yang seharusnya meyakinkan orang. */
const fotoTumpuk = computed(() =>
  props.galeri.map((g) => g.gambarUrl).filter((u): u is string => !!u).slice(0, 3));

const pilarJumlah = computed(() => Object.keys(props.pilar).length);


/**
 * Video yang sedang diputar — bukan sekadar penanda "modal terbuka".
 *
 * Semula berupa boolean, dan modalnya selalu memutar hero.video dengan
 * judul yang dipaku "Operasional Tambang". Keenam kartu galeri sudah
 * membawa videoUrl-nya masing-masing — inspeksi, survei, pajanan kerja,
 * higiene, mutu, reklamasi — dan tidak satu pun pernah terpakai: menekan
 * "Putar video" pada kartu mana pun memutar video yang sama.
 *
 * Yang membacanya tidak melihat galat. Ia melihat enam janji berbeda
 * yang semuanya membuka rekaman yang sama, lalu berhenti menekan
 * tombolnya.
 */
type VideoAktif = { judul: string; src: string; poster: string | null };

const videoAktif = ref<VideoAktif | null>(null);

function putar(judul: string, src: string | null, poster: string | null = null) {
  if (!src) return;

  videoAktif.value = { judul, src, poster };
}

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

const pakaiVideo = ref(bolehLatarVideo());
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
const warnaFitur = ['#F36F0F', '#1E88E5', '#16883F', '#7E57C2', '#B4500A', '#0891B2'];

/* Nama glyph padat untuk keenam kartu fitur. Nama, bukan jalur:
   bentuknya tinggal di resources/js/ikonPadat.ts supaya satu gambar
   tidak pernah punya dua salinan yang boleh berbeda. */
const ikonFitur: string[] = ['shield', 'dokumen', 'layers', 'orang', 'gembok', 'kisi'];

/* Searah dengan daftar `aman` di controller — urutannya yang memasangkan
   ikon dengan judulnya, jadi keduanya harus ikut berubah bersama. */
const ikonAman: string[] = ['layers', 'orang', 'gembok', 'shield'];

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
const warnaSmkp = ['#F36F0F', '#2D8CF0', '#16A34A', '#8B5CF6', '#06B6D4', '#F43F5E', '#EAB308'];

const tentang: [string, string][] = [
  ['Sesuai regulasi', 'Mengacu pada standar dan regulasi resmi Indonesia.'],
  ['Akses fleksibel', 'Berbasis web, terbuka dari kantor maupun dari site.'],
  ['Dukungan penuh', 'Tim HSE siap membantu penerapan di lapangan.'],
  ['Keamanan data', 'Data terenkripsi dalam pengiriman dan terpisah per perusahaan.'],
];

/* Searah dengan daftar `tentang` di bawah — urutannya yang memasangkan
   ikon dengan judulnya, jadi keduanya harus ikut berubah bersama. */
const ikonTentang: string[] = ['regulasi', 'globe', 'orang', 'gembok'];

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

/**
 * Memilih aspek — TIDAK menutupnya lagi bila ditekan dua kali.
 *
 * Selama rinciannya terbuka di bawah kisi, "tekan lagi untuk menutup"
 * masuk akal: ia mengembalikan halaman ke tinggi semula. Sesudah pindah
 * ke samping, menutupnya hanya menyisakan kolom kosong di sebelah kanan
 * — dan yang menekannya dua kali karena ragu justru kehilangan isi yang
 * baru saja ia baca. Panel itu selalu berisi sesuatu.
 */
function pilihPilar(slug: string) {
  pilarTerpilih.value = slug;
}

/* Aspek pertama terbuka sejak awal.
   Kolom kanan yang kosong saat halaman dibuka membuat kisi di kiri
   tampak salah lebar, dan yang membacanya tidak punya petunjuk bahwa
   kartunya memang dapat ditekan. */
pilarTerpilih.value = Object.keys(props.pilar)[0] ?? null;

/**
 * Tautan WhatsApp — dengan pesan yang sudah terisi.
 *
 * ── NOMORNYA TIDAK DITULIS DI SINI ──
 *
 * Dibaca dari config('pembelian.kontak.whatsapp'), nomor yang sama yang
 * sudah dipakai /katalog. Ditulis ulang di halaman depan, ia akan
 * menjadi nomor kedua yang boleh berbeda — dan yang berbeda cepat atau
 * lambat adalah nomor yang tidak ada yang menjawab, tercetak pada
 * tombol paling menonjol di halaman.
 *
 * Bernilai null bila nomornya belum diatur. Seluruh tombol WhatsApp di
 * halaman ini bergantung pada null itu, bukan menggambar tautan wa.me
 * kosong yang membuka pemilih kontak dan membuat orang mengira aplikasi
 * ini rusak.
 */
const waUrl = computed(() => (props.kontak.whatsapp
  ? 'https://wa.me/' + props.kontak.whatsapp
    + '?text=' + encodeURIComponent(
      'Halo, saya ingin melihat demo EQOHSEE untuk perusahaan saya.')
  : null));

/** Cadangan bila WhatsApp belum diatur: surel, lalu katalog. */
const ajakUrl = computed(() => waUrl.value
  ?? (props.kontak.email ? 'mailto:' + props.kontak.email : null));

/**
 * Pertanyaan yang sedang terbuka — satu pada satu waktu.
 *
 * Indeks, bukan kumpulan indeks: enam jawaban yang terbuka sekaligus
 * mengembalikan halaman ke bentuk daftar panjang yang justru dihindari
 * oleh akordeon.
 */
const tanyaBuka = ref<number | null>(0);

function bukaTanya(i: number) {
  tanyaBuka.value = tanyaBuka.value === i ? null : i;
}

/**
 * Angka pada bagian bukti — CAKUPAN produk, bukan hasil pelanggan.
 *
 * ── PERBEDAAN YANG MENENTUKAN ──
 *
 * "349 poin butir audit" dapat diperiksa siapa pun dengan membuka modul
 * auditnya. "Waktu audit berkurang 40%" tidak dapat diperiksa siapa pun
 * sampai ada pelanggan yang mengukurnya — dan angka hasil yang dikarang
 * di halaman produk keselamatan adalah jenis klaim yang akan dikutip
 * KTT ke atasannya sebelum ada yang sempat meralatnya.
 *
 * Karena itu tiap angka di bawah diturunkan dari data yang sudah ada di
 * aplikasi ini, bukan ditulis sebagai angka.
 */
const bukti = computed(() => [
  { nilai: String(props.modul.length), satuan: 'modul',
    ket: 'Satu akun, satu basis data perusahaan.' },
  { nilai: String(props.elemenSmkp.length), satuan: 'elemen SMKP',
    ket: `Terpetakan penuh, ${props.smkpAngka.butir} butir bernilai ${props.smkpAngka.poin} poin.` },
  { nilai: String(props.jumlahItem), satuan: 'item penilaian',
    ket: 'Pengukuran tingkat kematangan keselamatan, berbobot resmi.' },
  { nilai: String(pilarJumlah.value), satuan: 'aspek',
    ket: 'Dari pembelajaran sampai konservasi minerba.' },
]);

/* Esc menutup pemutar video. Modal yang hanya dapat ditutup dengan
   menekan tepat pada silangnya adalah modal yang terasa menjebak. */
function tekanTombol(e: KeyboardEvent) {
  if (e.key === 'Escape') videoAktif.value = null;
}

onMounted(() => window.addEventListener('keydown', tekanTombol));
onBeforeUnmount(() => window.removeEventListener('keydown', tekanTombol));
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
    <!-- Menumpang DI ATAS video, bukan sebagai bilah krem di atasnya.
         Sebagai bilah tersendiri ia memotong hero dengan garis keras
         tepat di bawah logo, dan yang terbaca bukan satu halaman
         melainkan dua yang kebetulan bertumpuk. -->
    <header class="jual-kepala" :class="digulir ? 'jual-kepala-turun' : ''">
      <div class="jual-lebar flex items-center gap-8 h-full">
        <Link href="/" class="shrink-0 opacity-90 hover:opacity-100 transition-opacity duration-300">
          <Wordmark :tinggi="26" />
        </Link>

        <nav class="ml-auto hidden md:flex items-center gap-7">
          <a v-for="item in [['#modul','Modul'],['#smkp','SMKP'],['#keamanan','Keamanan'],['#harga','Harga'],['#tanya','Tanya jawab']]"
             :key="item[0]" :href="item[0]" class="jual-nav">{{ item[1] }}</a>
        </nav>

        <Link href="/login" class="jual-nav ml-auto md:ml-0">Masuk</Link>
        <!-- Hitam hanya di sini, dan hanya setelah bilahnya turun ke
             krem. Di atas video yang gelap, hitam tidak terbaca sama
             sekali — yang dipesan untuk tombol ini adalah perannya
             sebagai satu-satunya benda paling pekat di layar, bukan
             kode warnanya pada keadaan yang membuatnya lenyap. -->
        <Link href="/katalog" class="jual-tombol jual-tombol-kecil"
              :class="digulir ? '' : 'jual-tombol-terang'">Beli sekarang</Link>
      </div>
    </header>

    <!-- Tengara <main>.

         Tanpa ini tidak ada satu pun unsur yang menyatakan "di sinilah
         isinya": pembaca layar hanya menemukan banner dan contentinfo,
         lalu harus menyusuri seluruh halaman dari awal tiap kali
         kembali. Pintasan "lompat ke isi" yang dipakai orang setiap
         hari bersandar pada tengara ini. -->
    <main>

    <!-- ══════════ HERO ══════════ -->
    <!-- Video KEMBALI menjadi latar penuh.

         Dibingkai di kolom kanan, ia kehilangan gunanya: yang membuat
         orang berhenti di halaman tambang adalah melihat tambangnya
         bergerak selebar layar, bukan melihat sebuah kotak berisi
         rekaman. Tirainya condong ke kiri — gelap tempat tulisan
         berada, membuka tempat gambarnya perlu terlihat. -->
    <section id="beranda" class="jual-hero jual-lugas relative overflow-hidden">
      <video v-if="hero.video && pakaiVideo"
             :src="hero.video" :poster="hero.poster ?? undefined"
             autoplay muted loop playsinline preload="metadata" aria-hidden="true"
             v-paralaks="-0.12" class="jual-hero-media"></video>
      <img v-else-if="hero.poster" :src="hero.poster" alt="" class="jual-hero-media">

      <div class="jual-hero-tirai"></div>

      <div class="jual-lebar relative">
        <div class="pt-36 pb-12 md:pt-44 md:pb-16">
          <div class="max-w-[48rem]">
            <p v-singkap class="jual-mata jual-mata-aksen">Delapan aspek · satu platform</p>

            <h1 v-belah="60" class="jual-judul jual-judul-hero mt-6">
              Keselamatan tambang,<br>
              <a href="#modul" class="jual-pil-panah" aria-label="Lihat modulnya">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M5 12h13M13 6l6 6-6 6"/>
                </svg>
              </a><span class="jual-judul-tipis-hero">terukur dan terbukti.</span>
            </h1>

            <p v-singkap="120" class="jual-tubuh-besar jual-hero-teks mt-7 max-w-2xl">
              Laporan bahaya, inspeksi, izin kerja, investigasi insiden, dan bukti SMKP
              tersusun di satu tempat — selaras Permen ESDM 26/2018 dan Kepdirjen Minerba
              185.K/2019, siap dibuka saat audit internal dijadwalkan.
            </p>

            <!-- CTA ganda: satu membawa ke katalog berharga terbuka, satu
                 lagi ke WhatsApp. Keduanya jalan konversi yang berbeda —
                 yang siap membeli tidak mau diajak mengobrol dulu, dan
                 yang belum siap tidak mau dihadapkan formulir pesanan.

                 Tombol WhatsApp hilang sendiri bila nomornya belum
                 diatur. Tombol paling menonjol di halaman yang membuka
                 pemilih kontak kosong terbaca sebagai aplikasi rusak. -->
            <div v-singkap="180" class="flex flex-wrap items-center gap-3 mt-9">
              <Link href="/katalog" class="jual-tombol jual-tombol-aksen">Lihat harga &amp; paket</Link>
              <a v-if="waUrl" :href="waUrl" target="_blank" rel="noopener"
                 class="jual-tombol jual-tombol-garis jual-tombol-wa">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                  <path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.05h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.11.82.83-3.03-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.23-8.23 2.2 0 4.26.86 5.82 2.41a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.22-8.23 8.22Zm4.52-6.16c-.25-.13-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.78.97-.15.16-.29.18-.53.06-.25-.13-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.71-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.43.13-.15.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.13-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.16 0-.43.06-.65.31-.23.25-.86.84-.86 2.05s.88 2.38 1 2.54c.13.17 1.74 2.65 4.2 3.72.59.25 1.05.4 1.4.52.59.18 1.13.16 1.55.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.06-.11-.23-.17-.48-.29Z"/>
                </svg>
                Minta demo via WhatsApp
              </a>
              <Link v-else href="/login" class="jual-tombol jual-tombol-garis">Masuk ke platform</Link>
            </div>
          </div>

          <!-- Pita tiga bagian di kaki hero — bentuk yang paling
               menandai acuannya. Ketiganya menjawab pertanyaan yang
               berbeda: seberapa banyak isinya, seperti apa layarnya,
               dan harus mulai dari mana. -->
          <div v-singkap="260" v-paralaks="0.045" class="jual-pita-hero mt-14 md:mt-20">
            <div v-if="fotoTumpuk.length" class="jual-tumpuk">
              <span class="jual-tumpuk-foto" aria-hidden="true">
                <img v-for="g in fotoTumpuk" :key="g" :src="g" alt="" loading="lazy" decoding="async">
              </span>
              <span>
                <b class="num">{{ jumlahItem }}</b>
                <span>Item penilaian terstandar</span>
              </span>
            </div>

            <Link href="/katalog" class="jual-keping">
              <span class="jual-keping-teks">
                <b>Dasbor HSE</b>
                <small>Hazard, temuan, dan kepatuhan MCU dalam satu layar</small>
              </span>
              <span v-if="fotoTumpuk[0]" class="jual-keping-foto">
                <img :src="fotoTumpuk[0]" alt="" loading="lazy" decoding="async">
              </span>
            </Link>

            <div class="jual-mulai">
              <div>
                <b>Mulai dari satu modul</b>
                <small>{{ modul.length }} modul terpadu, dipakai terpisah maupun sekaligus.</small>
              </div>
              <a href="#modul" class="jual-bulat-panah" aria-label="Lihat daftar modul">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M5 12h13M13 6l6 6-6 6"/>
                </svg>
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ STANDAR / KLIEN ══════════ -->
    <section class="jual-lugas" style="background:#FFFFFF;border-bottom:1px solid #E5E1D8">
      <div class="jual-lebar py-12 md:py-14">
        <div class="grid lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)] gap-x-12 gap-y-7 items-center">
          <div>
            <p v-singkap class="jual-mata jual-mata-aksen">{{ klien.length ? 'Dipercaya' : 'Acuan' }}</p>
            <!-- h2, bukan h3: bagian ini setingkat dengan bagian lain
                 di halaman, dan h1 → h3 adalah sendi yang terlewat.
                 Pembaca layar yang menelusuri daftar judul membaca
                 lompatan itu sebagai satu tingkat yang hilang, bukan
                 sebagai judul yang sengaja dibuat kecil. Ukurannya
                 tetap dari kelas .jual-h4. -->
            <h2 class="jual-h4 mt-2">
              {{ klien.length ? 'Terpercaya di industri' : 'Mengacu pada standar' }}
            </h2>
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

    <!-- ══════════ MASALAH ══════════

         Ditaruh SEBELUM daftar modul, dan itu bukan selera urutan.
         Daftar modul yang dibaca sebelum masalahnya terbaca sebagai
         katalog; dibaca sesudahnya, tiap modul terbaca sebagai jawaban
         atas sesuatu yang baru saja dikenali pembacanya di tempat
         kerjanya sendiri.

         Tiap angka membawa sumbernya di kakinya. Angka tanpa sumber di
         halaman jualan terbaca sebagai angka karangan — dan yang
         membacanya di sini adalah KTT yang akan mengutipnya ke
         atasannya. -->
    <section id="masalah" class="jual-lugas scroll-mt-[66px]">
      <div class="jual-lebar py-16 md:py-24">
        <div class="max-w-2xl">
          <p v-singkap class="jual-mata jual-mata-aksen">Keadaan di lapangan</p>
          <h2 v-belah="60" class="jual-h2">Yang membuat pengawasan keselamatan melelahkan</h2>
          <p class="jual-tubuh mt-5">
            Bukan karena tidak ada yang bekerja. Karena buktinya tersebar di kertas, di
            ponsel orang per orang, dan di berkas yang baru dicari ketika auditor sudah
            dijadwalkan.
          </p>
        </div>

        <div class="jual-masalah mt-10">
          <article v-for="(m, i) in masalah" :key="m.judul" v-singkap class="jual-masalah-kartu">
            <span class="jual-masalah-nomor num" aria-hidden="true">{{ String(i + 1).padStart(2, '0') }}</span>
            <h3 class="jual-h4 mt-4">{{ m.judul }}</h3>
            <p class="jual-tubuh-kecil mt-2.5">{{ m.ket }}</p>
            <p v-if="m.sumber" class="jual-sumber">{{ m.sumber }}</p>
          </article>
        </div>
      </div>
    </section>

    <!-- ══════════ MODUL ══════════ -->
    <section id="modul" class="jual-lugas scroll-mt-[66px]">
      <div class="jual-lebar py-16 md:py-24">
        <div class="max-w-2xl">
          <p v-singkap class="jual-mata jual-mata-aksen">Aplikasi di dalamnya</p>
          <h2 v-belah="60" class="jual-h2">{{ modul.length }} modul, satu akun</h2>
          <p class="jual-tubuh mt-5">
            {{ modul.filter((m) => m.status === 'aktif').length }} modul sudah aktif dan siap
            dipakai. Semua modul berbagi data perusahaan, pengguna, dan peran yang sama.
          </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mt-10">
          <component :is="item.url ? 'a' : 'div'" v-for="item in modul" :key="item.nama"
                     :href="item.url ?? undefined" class="jual-modul"
                     :class="item.url ? '' : 'jual-modul-mati'"
                     :style="{ '--c': item.url ? item.pilarWarna : '#D6D1C5' }">
            <div class="flex items-start justify-between gap-3">
              <span class="jual-tanda ikon-3d" :style="{ '--c': item.url ? item.pilarWarna : '#A39A93' }">
                <IkonPadat :jalur="item.ikonPadat" :ukuran="22" />
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

    <!-- ══════════ PILAR ══════════ -->
    <section id="pilar" class="jual-lugas scroll-mt-[66px]">
      <div class="jual-lebar py-16 md:py-24">
        <div class="max-w-2xl">
          <p class="jual-mata jual-mata-aksen">Kerangka kerja</p>
          <h2 v-belah="60" class="jual-h2">Delapan aspek, satu sistem</h2>
          <p class="jual-tubuh mt-5">
            Tujuh huruf pada <strong style="color:var(--j-tinta)">EQOHSEE</strong> mewakili satu aspek
            masing-masing, ditambah Konservasi Minerba di luar akronim.
          </p>
        </div>

        <!-- Kisi di kiri, rincian di KANAN — bukan di bawah.
             Rincian yang terbuka di bawah kisi mendorong seluruh halaman
             turun, dan yang baru saja menekan kartunya kehilangan kartu
             itu dari pandangan tepat pada saat ia ingin membandingkannya
             dengan yang lain. Di samping, kartunya tetap terlihat dan
             perpindahan antaraspek terbaca sebagai satu gerakan.

             Di bawah 1024px tidak ada "samping" yang tersisa, jadi
             panelnya turun ke bawah kisinya — tetap dengan transisi yang
             sama. -->
        <div class="jual-pilar-tata mt-10">
          <div class="jual-pilar-kisi">
            <button v-for="[slug, item] in pilarList" :key="slug" type="button"
                    class="jual-pilar-kartu"
                    :class="pilarTerpilih === slug ? 'jual-pilar-kartu-aktif' : ''"
                    :aria-pressed="pilarTerpilih === slug"
                    @click="pilihPilar(slug)">
              <!-- Warna aspeknya tinggal pada GORESAN ikonnya saja.
                   Sebelumnya tiap kartu membawa ubin dan tautan berwarna
                   sendiri, dan delapan kartu berdampingan terbaca sebagai
                   pelangi — tidak ada yang menonjol karena semuanya
                   menonjol. Yang berwarna penuh sekarang hanya kartu yang
                   sedang dipilih, satu pada satu waktu. -->
              <span class="jual-pilar-tanda"><IkonPilar :nama="item.ikon" :ukuran="19" /></span>
              <span class="jual-pilar-nama">{{ item.nama }}</span>
              <span class="jual-pilar-ket">{{ item.ket }}</span>
            </button>
          </div>

          <Transition name="jual-panel" mode="out-in">
            <aside v-if="pilarTerpilih && pilar[pilarTerpilih]" :key="pilarTerpilih"
                   class="jual-pilar-panel">
              <span class="jual-pilar-panel-tanda ikon-3d" :style="{ '--c': pilar[pilarTerpilih].warna }">
                <IkonPadat :jalur="ikonPadat(pilar[pilarTerpilih].ikon)" :ukuran="26" />
              </span>

              <h3 class="jual-h3 mt-4">{{ pilar[pilarTerpilih].nama }}</h3>
              <p class="jual-tubuh-kecil mt-2.5">
                {{ pilar[pilarTerpilih].ringkas }}
              </p>

              <ul class="jual-pilar-cakupan">
                <li v-for="c in pilar[pilarTerpilih].cakupan" :key="c[0]"
                    :style="{ '--c': pilar[pilarTerpilih].warna }">
                  <b>{{ c[0] }}</b>
                  <span>{{ c[1] }}</span>
                </li>
              </ul>

              <p class="jual-pilar-modul-judul">Ditopang modul</p>
              <div class="flex flex-wrap gap-1.5 mt-2">
                <span v-for="m in pilar[pilarTerpilih].modul" :key="m" class="jual-label"
                      :style="{ '--c': pilar[pilarTerpilih].warna }">{{ m }}</span>
              </div>
            </aside>
          </Transition>
        </div>
      </div>
    </section>

    <!-- ══════════ KEPATUHAN SMKP ══════════

         Bagian ini yang paling menentukan, dan yang paling mudah
         merugikan bila salah tulis.

         Bilah bobot menjawab "seberapa besar tiap elemen"; tabel di
         bawahnya menjawab pertanyaan yang sebenarnya dibawa KTT ke sini:
         "untuk elemen ini, bukti saya ada di mana". Keduanya membaca
         nama dan bobot dari elemen.json yang sama dengan modul auditnya
         — bukan disalin ke sini, karena dua salinan berarti dua nama
         untuk satu elemen yang sama.

         Penyangkalan di kakinya WAJIB ada dan tidak boleh diperhalus.
         Perangkat lunak tidak dapat menjamin kelulusan audit, dan
         kewajiban hukumnya tetap melekat pada perusahaan dan KTT. -->
    <section id="smkp" class="jual-lugas scroll-mt-[66px]" style="border-top:1px solid #E5E1D8">
      <div class="jual-lebar py-16 md:py-24">
        <div class="grid lg:grid-cols-[minmax(0,24rem)_minmax(0,1fr)] gap-x-14 gap-y-10 items-start">
          <div>
            <p v-singkap class="jual-mata jual-mata-aksen">Kepatuhan</p>
            <h2 v-belah="60" class="jual-h2">Selaras SMKP Minerba</h2>
            <p class="jual-tubuh mt-5">
              Tujuh elemen wajib menurut Kepdirjen Minerba 185.K/37.04/DJB/2019, turunan
              Permen ESDM 26/2018 dan Kepmen ESDM 1827 K/30/MEM/2018. Bobotnya berjumlah
              tepat seratus — dan Implementasi sendirian menanggung sepertiganya.
            </p>

            <div class="jual-takaran mt-7">
              <i v-for="(e, k) in elemenSmkp" :key="e.kode"
                 :style="{ width: e.bobot + '%', background: warnaSmkp[k % warnaSmkp.length] }"
                 :title="`${e.nama} — ${e.bobot}%`"></i>
            </div>

            <p class="jual-tubuh-kecil mt-4">
              Instrumen auditnya tersedia utuh di dalam platform:
              <strong style="color:var(--j-tinta)">{{ smkpAngka.butir }} butir</strong>
              bernilai <strong style="color:var(--j-tinta)">{{ smkpAngka.poin }} poin</strong>,
              dengan halaman acuan tiap butir ikut tercatat.
            </p>
          </div>

          <div>
            <!-- Tabel, bukan kisi kartu. Yang dicari pembacanya adalah
                 BARIS elemennya sendiri, dan menemukan satu baris di
                 antara tujuh kartu sejajar menuntut membaca ketujuhnya.

                 Di bawah 48rem ia runtuh jadi kartu berlabel lewat
                 td::before — bukan digeser ke samping, karena tabel yang
                 harus digeser di ponsel adalah tabel yang tidak dibaca. -->
            <table class="jual-peta">
              <caption class="sr-only">Pemetaan elemen SMKP Minerba terhadap modul EQOHSEE</caption>
              <thead>
                <tr>
                  <th scope="col">Elemen</th>
                  <th scope="col">Bobot</th>
                  <th scope="col">Bukti tersusun di modul</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(e, k) in elemenSmkp" :key="e.kode">
                  <td data-kolom="Elemen">
                    <span class="jual-peta-titik"
                          :style="{ background: warnaSmkp[k % warnaSmkp.length] }"></span>
                    <span class="jual-peta-kode num">{{ e.kode }}</span>
                    <span class="jual-peta-nama">{{ e.nama }}</span>
                  </td>
                  <td data-kolom="Bobot" class="jual-peta-bobot num">{{ e.bobot }}%</td>
                  <td data-kolom="Bukti tersusun di modul" class="jual-peta-modul">{{ e.modul }}</td>
                </tr>
              </tbody>
            </table>

            <p class="jual-sangkal">
              <span aria-hidden="true">!</span>
              <span>
                EQOHSEE adalah <strong>alat bantu pemenuhan SMKP</strong> — bukan pengganti
                kewajiban hukum, dan bukan jaminan kelulusan audit. Tanggung jawab penerapan
                serta pelaporannya tetap berada pada perusahaan dan Kepala Teknik Tambang.
              </span>
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ ANGKA ══════════

         Semuanya CAKUPAN produk, tidak satu pun hasil pelanggan.

         "349 poin butir audit" dapat diperiksa siapa pun dengan membuka
         modulnya. "Waktu audit berkurang 40%" tidak dapat diperiksa
         siapa pun sampai ada pelanggan yang mengukurnya — dan pada
         halaman produk keselamatan, angka hasil yang dikarang akan
         dikutip KTT ke atasannya sebelum ada yang sempat meralatnya. -->
    <section class="jual-lugas" style="background:#FFFFFF;border-top:1px solid #E5E1D8">
      <div class="jual-lebar py-14 md:py-16">
        <div class="jual-angka-deret">
          <div v-for="b in bukti" :key="b.satuan" v-singkap>
            <p class="jual-angka-besar num">{{ b.nilai }}</p>
            <p class="jual-angka-satuan">{{ b.satuan }}</p>
            <p class="jual-tubuh-kecil mt-2">{{ b.ket }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ GALERI ══════════ -->
    <section id="lapangan" class="jual-lugas scroll-mt-[66px]"
             style="border-top:1px solid #E5E1D8">
      <div class="jual-lebar py-16 md:py-20">
        <div class="flex flex-wrap items-end justify-between gap-4 max-w-3xl">
          <div>
            <p class="jual-mata jual-mata-aksen">Lapangan</p>
            <h2 v-belah="60" class="jual-h2">Potret kegiatan</h2>
          </div>
        </div>

        <div class="jual-pita-galeri mt-9">
          <article v-for="g in galeri" :key="g.judul" v-singkap>
            <!-- Pita bawah foto dipotong: berkas galerinya membawa
                 tulisan dan lencana penyunting yang terbakar di dalam
                 gambarnya. Lihat catatan pada .jual-aspek-foto. -->
            <span class="jual-galeri-bingkai">
              <img v-if="g.gambarUrl" :src="g.gambarUrl" alt="" loading="lazy" decoding="async"
                   class="jual-galeri-foto">
            </span>
            <div class="mt-3.5">
              <div class="text-[13px] font-bold">{{ g.judul }}</div>
              <p class="jual-tubuh-kecil mt-1">{{ g.ket }}</p>
              <!-- Bilah jingga, bukan tautan bergaris bawah. Enam kartu
                   berfoto besar dengan tautan setipis itu di kakinya
                   membuat fotonya terbaca sebagai gambar hiasan, bukan
                   sebagai sesuatu yang dapat dibuka. -->
              <button v-if="g.videoUrl" type="button" class="jual-bilah-aksi"
                      @click="putar(g.judul, g.videoUrl, g.gambarUrl)">
                <span>Putar video</span>
                <i aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                </i>
              </button>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- ══════════ KEAMANAN DATA ══════════

         Empat pernyataan, masing-masing menunjuk mekanisme yang benar-
         benar ada di kode ini — bukan daftar lencana.

         Yang sengaja tidak ada: ISO 27001 dan angka uptime. Keduanya
         proof point yang diminta, tetapi keduanya juga klaim yang harus
         dibuktikan pihak ketiga — dan lencana kepatuhan yang tidak
         dimiliki adalah jenis kebohongan yang paling mudah diperiksa. -->
    <section id="keamanan" class="jual-lugas scroll-mt-[66px]">
      <div class="jual-lebar py-16 md:py-24">
        <div class="max-w-2xl">
          <p v-singkap class="jual-mata jual-mata-aksen">Keamanan data</p>
          <h2 v-belah="60" class="jual-h2">Dipisah di server, bukan disembunyikan di layar</h2>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mt-10">
          <div v-for="(a, i) in aman" :key="a.judul" class="jual-kartu"
               :style="{ '--c': warnaFitur[i % warnaFitur.length] }">
            <span class="jual-tanda ikon-3d">
              <IkonPadat :jalur="ikonPadat(ikonAman[i % ikonAman.length])" :ukuran="22" />
            </span>
            <h3 class="jual-h4 mt-4">{{ a.judul }}</h3>
            <p class="jual-tubuh-kecil mt-2">{{ a.ket }}</p>
          </div>
        </div>

        <p class="jual-tubuh-kecil mt-7">
          Pemrosesan data pribadi mengikuti UU No. 27 Tahun 2022 tentang Pelindungan Data
          Pribadi. Rinciannya ada pada
          <a href="/kebijakan-privasi" class="jual-tautan">kebijakan privasi</a>.
        </p>
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
          <p v-singkap class="jual-mata jual-mata-aksen">Pembelian</p>
          <h2 v-belah="60" class="jual-h2">Miliki platformnya</h2>
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
    <section id="fitur" class="jual-lugas scroll-mt-[66px]" style="border-top:1px solid #E5E1D8">
      <div class="jual-lebar py-16 md:py-24">
        <div class="max-w-2xl">
          <p v-singkap class="jual-mata jual-mata-aksen">Fitur unggulan</p>
          <h2 v-belah="60" class="jual-h2">Dibuat untuk lapangan, bukan sekadar laporan</h2>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mt-10">
          <div v-for="(item, i) in fitur" :key="item.judul" class="jual-kartu"
               :style="{ '--c': warnaFitur[i % warnaFitur.length] }">
            <span class="jual-tanda ikon-3d">
              <IkonPadat :jalur="ikonPadat(ikonFitur[i % ikonFitur.length])" :ukuran="22" />
            </span>
            <h3 class="jual-h4 mt-4">{{ item.judul }}</h3>
            <p class="jual-tubuh-kecil mt-2">{{ item.ket }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ ALUR ══════════ -->
    <!-- Latar krem, bukan putih. Bulatan nomor dan kartu putih di
         dalamnya hanya terbaca sebagai bentuk bila latarnya bukan
         putih juga — di atas putih, keduanya lenyap jadi teks
         melayang. -->
    <section id="alur" class="jual-lugas scroll-mt-[66px]">
      <div class="jual-lebar py-16 md:py-24">
        <div class="max-w-2xl">
          <p v-singkap class="jual-mata jual-mata-aksen">Cara kerja</p>
          <h2 v-belah="60" class="jual-h2">Empat langkah, satu siklus</h2>
        </div>

        <!-- Rel penghubung hanya digambar pada lebar yang benar-benar
             menampung empat kolom sejajar. Pada dua kolom ia akan
             menyambungkan langkah 2 ke langkah 3 yang berada di baris
             berbeda — menggambar urutan yang tidak pernah terjadi. -->
        <div class="jual-alur mt-10">
          <span class="jual-alur-rel" aria-hidden="true"></span>
          <div v-for="(item, i) in alur" :key="item.judul" v-singkap class="jual-alur-butir">
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
             style="border-top:1px solid #E5E1D8">
          <div v-for="(t, i) in tentang" :key="t[0]">
            <span class="jual-tanda ikon-3d" :style="{ '--c': warnaFitur[i % warnaFitur.length] }">
              <IkonPadat :jalur="ikonPadat(ikonTentang[i])" :ukuran="22" />
            </span>
            <h3 class="jual-h4 mt-4">{{ t[0] }}</h3>
            <p class="jual-tubuh-kecil mt-2">{{ t[1] }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ TANYA JAWAB ══════════

         Akordeon, satu terbuka pada satu waktu. Enam jawaban yang
         terbuka sekaligus mengembalikan halaman ke daftar panjang yang
         justru dihindari akordeon.

         Memakai <button> dengan aria-expanded, bukan <div> yang
         ditempeli @click: yang membuka halaman ini dengan papan tik atau
         pembaca layar tetap dapat membuka jawabannya, dan tetap
         diberitahu keadaan mana yang sedang terbuka. -->
    <section id="tanya" class="jual-lugas scroll-mt-[66px]" style="border-top:1px solid #E5E1D8">
      <div class="jual-lebar py-16 md:py-24">
        <div class="grid lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] gap-x-14 gap-y-9 items-start">
          <div>
            <p v-singkap class="jual-mata jual-mata-aksen">Tanya jawab</p>
            <h2 v-belah="60" class="jual-h2">Yang biasa ditanyakan</h2>
            <p class="jual-tubuh mt-5">
              Belum terjawab? Sebutkan keadaan site Anda — kami balas dengan jawaban yang
              menyebut modulnya, bukan brosur.
            </p>
            <a v-if="ajakUrl" :href="ajakUrl" target="_blank" rel="noopener"
               class="jual-tombol jual-tombol-lain mt-6">Tanya langsung</a>
          </div>

          <div class="jual-tanya">
            <div v-for="(q, i) in tanya" :key="q.t" class="jual-tanya-butir"
                 :class="tanyaBuka === i ? 'jual-tanya-buka' : ''">
              <button type="button" class="jual-tanya-kepala"
                      :aria-expanded="tanyaBuka === i" @click="bukaTanya(i)">
                <span>{{ q.t }}</span>
                <i aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"
                       stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9l6 6 6-6"/>
                  </svg>
                </i>
              </button>
              <div class="jual-tanya-isi"><p>{{ q.j }}</p></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ AJAKAN PENUTUP ══════════ -->
    <section class="jual-lugas" style="background:#FFFFFF">
      <div class="jual-lebar pb-16 md:pb-24">
        <div class="jual-lugas jual-ajakan rounded-[26px] px-7 py-12 md:px-14 md:py-16">
          <div class="max-w-2xl">
            <p class="jual-mata jual-mata-aksen">Mulai</p>
            <h2 v-belah="60" class="jual-h2">Siap menaikkan level keselamatan?</h2>
            <p class="jual-tubuh mt-5">
              Ambil paketnya, atau mulai dari satu aplikasi yang paling dibutuhkan lebih dulu.
              Pemesanannya tidak menuntut akun.
            </p>
            <div class="flex flex-wrap items-center gap-3 mt-8">
              <Link href="/katalog" class="jual-tombol jual-tombol-aksen">Lihat katalog</Link>
              <a v-if="waUrl" :href="waUrl" target="_blank" rel="noopener"
                 class="jual-tombol jual-tombol-lain jual-tombol-wa">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                  <path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.05h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.11.82.83-3.03-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.23-8.23 2.2 0 4.26.86 5.82 2.41a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.22-8.23 8.22Zm4.52-6.16c-.25-.13-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.78.97-.15.16-.29.18-.53.06-.25-.13-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.71-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.43.13-.15.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.13-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.16 0-.43.06-.65.31-.23.25-.86.84-.86 2.05s.88 2.38 1 2.54c.13.17 1.74 2.65 4.2 3.72.59.25 1.05.4 1.4.52.59.18 1.13.16 1.55.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.06-.11-.23-.17-.48-.29Z"/>
                </svg>
                Konsultasi via WhatsApp
              </a>
              <Link v-else href="/login" class="jual-tombol jual-tombol-lain">Masuk ke platform</Link>
            </div>
          </div>
        </div>
      </div>
    </section>

    </main>

    <footer class="jual-lugas" style="background:#FFFFFF;border-top:1px solid #E5E1D8">
      <div class="jual-lebar py-8 flex flex-wrap items-center justify-between gap-x-8 gap-y-4"
           :class="waUrl ? 'jual-kaki-apung' : ''">
        <Wordmark :tinggi="20" />

        <!-- Kebijakan privasi WAJIB tertaut dari sini. UU PDP menuntut
             dasar dan tujuan pemrosesan dapat dibaca sebelum orang
             menyerahkan datanya — dan halaman ini adalah tempat pertama
             ia diminta menyerahkannya. -->
        <nav class="flex flex-wrap items-center gap-x-6 gap-y-2">
          <a href="/kebijakan-privasi" class="jual-nav">Kebijakan privasi</a>
          <a href="/privacy-policy" class="jual-nav">Privacy policy</a>
          <a v-if="waUrl" :href="waUrl" target="_blank" rel="noopener" class="jual-nav">WhatsApp</a>
          <a v-if="kontak.email" :href="'mailto:' + kontak.email" class="jual-nav">{{ kontak.email }}</a>
        </nav>

        <p class="jual-tubuh-kecil w-full lg:w-auto">
          Platform Terpadu Keselamatan Pertambangan · {{ tahun }}
        </p>
      </div>
    </footer>
  </div>

  <!-- Tombol WhatsApp mengambang.

       Tetap terlihat sepanjang halaman digulir, karena keputusan untuk
       bertanya jarang datang di hero: ia datang di tengah tabel SMKP
       atau di bawah daftar harga, dan menggulir balik ke atas untuk
       mencari tombolnya adalah langkah yang sebagian orang tidak ambil.

       Tidak digambar sama sekali bila nomornya belum diatur — bukan
       digambar lalu dimatikan. Tombol mengambang yang tidak menuju ke
       mana pun menutupi isi halaman sambil tidak memberi apa pun. -->
  <a v-if="waUrl" :href="waUrl" target="_blank" rel="noopener" class="jual-apung"
     aria-label="Hubungi kami lewat WhatsApp">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.05h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.11.82.83-3.03-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.23-8.23 2.2 0 4.26.86 5.82 2.41a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.22-8.23 8.22Zm4.52-6.16c-.25-.13-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.78.97-.15.16-.29.18-.53.06-.25-.13-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.71-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.43.13-.15.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.13-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.16 0-.43.06-.65.31-.23.25-.86.84-.86 2.05s.88 2.38 1 2.54c.13.17 1.74 2.65 4.2 3.72.59.25 1.05.4 1.4.52.59.18 1.13.16 1.55.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.06-.11-.23-.17-.48-.29Z"/>
    </svg>
    <span>Tanya cepat</span>
  </a>

  <!-- Pemutar video: judul dan berkasnya dari kartu yang ditekan.
       Ditutup dengan Esc, latar, atau tombol silangnya. -->
  <Transition name="jual-pemutar">
    <div v-if="videoAktif" class="jual-pemutar" role="dialog" aria-modal="true"
         :aria-label="videoAktif.judul" @click.self="videoAktif = null">
      <div class="jual-pemutar-isi">
        <div class="jual-pemutar-kepala">
          <span>{{ videoAktif.judul }}</span>
          <button type="button" aria-label="Tutup video" @click="videoAktif = null">&times;</button>
        </div>
        <video :key="videoAktif.src" :src="videoAktif.src"
               :poster="videoAktif.poster ?? undefined"
               class="jual-pemutar-video" controls autoplay playsinline></video>
      </div>
    </div>
  </Transition>
</template>
