<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BlankLayout from '../Layouts/BlankLayout.vue';
import Wordmark from '../Components/Wordmark.vue';
import IkonPilar from '../Components/IkonPilar.vue';
import IkonPadat from '../Components/IkonPadat.vue';
import { ikonPadat, type JalurPadat } from '../ikonPadat';

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
const warnaFitur = ['#F36F0F', '#1E88E5', '#16883F', '#7E57C2', '#B4500A', '#0891B2'];

/* Nama glyph padat untuk keenam kartu fitur. Nama, bukan jalur:
   bentuknya tinggal di resources/js/ikonPadat.ts supaya satu gambar
   tidak pernah punya dua salinan yang boleh berbeda. */
const ikonFitur: string[] = ['shield', 'dokumen', 'layers', 'orang', 'gembok', 'kisi'];

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
    <header class="jual-kepala jual-kepala-turun" :class="digulir ? 'jual-kepala-berbayang' : ''">
      <div class="jual-lebar flex items-center gap-8 h-full">
        <Link href="/" class="shrink-0 opacity-90 hover:opacity-100 transition-opacity duration-300">
          <Wordmark :tinggi="26" />
        </Link>

        <nav class="ml-auto hidden md:flex items-center gap-7">
          <a v-for="item in [['#pilar','Pilar'],['#modul','Modul'],['#harga','Harga'],['#fitur','Fitur'],['#alur','Cara kerja']]"
             :key="item[0]" :href="item[0]" class="jual-nav">{{ item[1] }}</a>
        </nav>

        <Link href="/login" class="jual-nav ml-auto md:ml-0">Masuk</Link>
        <!-- SATU-SATUNYA hitam di halaman ini, dan itu disengaja.
             Yang paling gelap di layar seharusnya cuma satu benda;
             dipakai juga oleh tombol lain, ia berhenti menandai apa
             pun. Tombol ajakan di badan halaman karena itu jingga. -->
        <Link href="/katalog" class="jual-tombol jual-tombol-kecil">Beli sekarang</Link>
      </div>
    </header>

    <!-- ══════════ HERO ══════════ -->
    <section id="beranda" class="jual-lugas relative overflow-hidden">
      <div class="jual-lebar relative">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_30rem] gap-x-14 gap-y-14 items-center
                    pt-32 pb-16 md:pt-40 md:pb-24">
          <div class="max-w-[46rem]">
            <p v-singkap class="jual-mata jual-mata-aksen">Delapan aspek · satu platform</p>

            <h1 v-singkap="60" class="jual-judul mt-6">
              Keselamatan tambang,<br>
              <a href="#modul" class="jual-pil-panah" aria-label="Lihat modulnya">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M5 12h13M13 6l6 6-6 6"/>
                </svg>
              </a><span class="jual-judul-tipis">terukur dan terbukti.</span>
            </h1>

            <p v-singkap="120" class="jual-tubuh-besar mt-7 max-w-xl">
              Platform keselamatan pertambangan terpadu untuk pembelajaran, penilaian kinerja,
              inspeksi, kinerja energi, hingga sertifikasi — mengikuti regulasi keselamatan
              pertambangan Indonesia.
            </p>

            <div v-singkap="180" class="flex flex-wrap items-center gap-3 mt-9">
              <Link href="/katalog" class="jual-tombol jual-tombol-aksen">Beli platform</Link>
              <Link href="/login" class="jual-tombol jual-tombol-lain">Masuk ke platform</Link>
            </div>

            <div v-if="fotoTumpuk.length" v-singkap="240" class="jual-tumpuk mt-11">
              <span class="jual-tumpuk-foto" aria-hidden="true">
                <img v-for="g in fotoTumpuk" :key="g" :src="g" alt="" loading="lazy" decoding="async">
              </span>
              <span>
                <b class="num">{{ jumlahItem }}</b>
                <span>Item penilaian terstandar</span>
              </span>
            </div>

            <div v-singkap="300" class="jual-statistik mt-9 max-w-2xl">
              <div v-for="s in [[elemenSmkp.length, 'Elemen SMKP'], [modul.length, 'Modul terpadu'],
                                [pilarJumlah, 'Aspek dijaga'], ['24/7', 'Akses platform']]"
                   :key="String(s[1])">
                <b>{{ s[0] }}</b><span>{{ s[1] }}</span>
              </div>
            </div>
          </div>

          <!-- Videonya DIBINGKAI, bukan lagi terbentang sebagai latar.

               Di halaman krem, video di belakang tulisan gelap tidak
               menyisakan beda terang yang cukup untuk dibaca — berapa
               pun tirai yang ditumpuk di atasnya, yang didapat cuma
               krem yang kotor. Dibingkai, ia tetap bergerak dan tetap
               memperlihatkan tambangnya, dan dasbornya menumpang di
               sudutnya sebagai bukti produk. -->
          <div v-singkap="360" class="relative">
            <div class="jual-tayang">
              <video v-if="hero.video && !kurangiGerak"
                     :src="hero.video" :poster="hero.poster ?? undefined"
                     autoplay muted loop playsinline preload="metadata" aria-hidden="true"></video>
              <img v-else-if="hero.poster" :src="hero.poster" alt="">
            </div>

            <aside class="jual-dasbor absolute -bottom-12 -left-4 w-[17rem] hidden sm:block lg:-left-32">
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
      </div>
    </section>

    <!-- ══════════ SMKP ══════════ -->
    <section id="beranda-lanjut" class="jual-lugas scroll-mt-[66px]"
             style="border-top:1px solid rgba(255,255,255,.08)">
      <div class="jual-lebar py-16 md:py-20">
        <div class="grid lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] gap-x-14 gap-y-10 items-start">
          <div>
            <p class="jual-mata jual-mata-aksen">Kerangka</p>
            <h2 v-singkap="60" class="jual-h2">SMKP Minerba</h2>
            <p class="jual-tubuh mt-4">
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
    <section class="jual-lugas"
             style="border-top:1px solid rgba(255,255,255,.08)">
      <div class="jual-lebar py-16 md:py-20">
        <div class="flex flex-wrap items-end justify-between gap-4 max-w-3xl">
          <div>
            <p class="jual-mata jual-mata-aksen">Lapangan</p>
            <h2 v-singkap="60" class="jual-h2">Potret kegiatan</h2>
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

    <!-- ══════════ STANDAR / KLIEN ══════════ -->
    <section class="jual-lugas" style="border-bottom:1px solid #E5E1D8">
      <div class="jual-lebar py-12 md:py-14">
        <div class="grid lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)] gap-x-12 gap-y-7 items-center">
          <div>
            <p v-singkap class="jual-mata jual-mata-aksen">{{ klien.length ? 'Dipercaya' : 'Acuan' }}</p>
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
    <section id="pilar" class="jual-lugas scroll-mt-[66px]">
      <div class="jual-lebar py-16 md:py-24">
        <div class="max-w-2xl">
          <p class="jual-mata jual-mata-aksen">Kerangka kerja</p>
          <h2 v-singkap="60" class="jual-h2">Delapan aspek, satu sistem</h2>
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
                      :style="{ background: `${pilar[pilarTerpilih].warna}1F`,
                                color: pilar[pilarTerpilih].light }">{{ m }}</span>
              </div>
            </aside>
          </Transition>
        </div>
      </div>
    </section>

    <!-- ══════════ MODUL ══════════ -->
    <section id="modul" class="jual-lugas scroll-mt-[66px]">
      <div class="jual-lebar py-16 md:py-24">
        <div class="max-w-2xl">
          <p v-singkap class="jual-mata jual-mata-aksen">Aplikasi di dalamnya</p>
          <h2 v-singkap="60" class="jual-h2">{{ modul.length }} modul, satu akun</h2>
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
          <h2 v-singkap="60" class="jual-h2">Miliki platformnya</h2>
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
          <h2 v-singkap="60" class="jual-h2">Dibuat untuk lapangan, bukan sekadar laporan</h2>
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
          <h2 v-singkap="60" class="jual-h2">Empat langkah, satu siklus</h2>
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

    <!-- ══════════ AJAKAN PENUTUP ══════════ -->
    <section class="jual-lugas" style="background:#FFFFFF">
      <div class="jual-lebar pb-16 md:pb-24">
        <div class="jual-lugas jual-ajakan rounded-[26px] px-7 py-12 md:px-14 md:py-16">
          <div class="max-w-2xl">
            <p class="jual-mata jual-mata-aksen">Mulai</p>
            <h2 v-singkap="60" class="jual-h2">Siap menaikkan level keselamatan?</h2>
            <p class="jual-tubuh mt-5">
              Ambil paketnya, atau mulai dari satu aplikasi yang paling dibutuhkan lebih dulu.
              Pemesanannya tidak menuntut akun.
            </p>
            <div class="flex flex-wrap items-center gap-3 mt-8">
              <Link href="/katalog" class="jual-tombol jual-tombol-aksen">Lihat katalog</Link>
              <Link href="/login" class="jual-tombol jual-tombol-lain">Masuk ke platform</Link>
            </div>
          </div>
        </div>
      </div>
    </section>

    <footer class="jual-lugas" style="background:#FFFFFF;border-top:1px solid #E5E1D8">
      <div class="jual-lebar py-8 flex flex-wrap items-center justify-between gap-4">
        <Wordmark :tinggi="20" />
        <p class="jual-tubuh-kecil">
          Platform Terpadu Keselamatan Pertambangan · {{ tahun }}
        </p>
      </div>
    </footer>
  </div>

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
