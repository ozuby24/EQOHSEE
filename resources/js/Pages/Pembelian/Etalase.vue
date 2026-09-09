<script setup lang="ts">
/**
 * Etalase jual EQOHSEE — terbuka tanpa login.
 *
 * ── TOTALNYA DIHITUNG DI LAYAR HANYA UNTUK DIBACA ──
 *
 * Angka di bawah ini pengingat bagi yang memilih, BUKAN nilai yang
 * dikirim. Yang dikirim hanya daftar id beserta banyaknya; harganya
 * dibaca ulang server dari basis data. Kalau totalnya ikut dikirim,
 * menyunting satu medan tersembunyi cukup untuk memesan seluruh katalog
 * seharga nol rupiah — dan tagihannya akan terlihat wajar sepenuhnya di
 * layar admin, sebab nol itu memang yang tersimpan.
 *
 * ── KARTU TANPA HARGA TETAP DIGAMBAR ──
 *
 * Aplikasi yang harganya belum ditetapkan punya `id` null. Ia tetap
 * tampil lengkap dengan keterangan, ikon, dan warna pilarnya, hanya
 * tanpa tombol beli. Yang disembunyikan cara membelinya, bukan
 * keberadaannya: katalog yang separuh kosong terbaca sebagai perusahaan
 * yang tidak punya apa-apa untuk dijual, bukan sebagai harga yang belum
 * diumumkan.
 *
 * ── GAMBARNYA BOLEH TIDAK ADA ──
 *
 * Tiap foto dan rekaman diperiksa keberadaannya di server (lihat
 * App\Support\Media) dan dikirim sebagai null bila berkasnya belum
 * ditaruh. Halaman ini menggambar gradien dan vektor sebagai gantinya —
 * bukan bingkai gambar rusak, yang di halaman jual lebih buruk daripada
 * tidak ada gambar sama sekali.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import Wordmark from '../../Components/Wordmark.vue';
import IkonPilar from '../../Components/IkonPilar.vue';

defineOptions({ layout: BlankLayout });

type Aplikasi = {
  id: number | null; nama: string; ket: string; ikon: string;
  harga: number | null; masa: string | null;
  pilar: string; pilarNama: string; pilarWarna: string; pilarDeep: string;
};
type Pilar = {
  nama: string; ket: string; warna: string; deep: string; light: string;
  ikon: string; foto: string | null; jumlah: number;
};
type Paket = { id: number; nama: string; ket: string | null; harga: number; masa: string };

const props = defineProps<{
  paket: Paket | null;
  aplikasi: Aplikasi[];
  pilar: Record<string, Pilar>;
  adaHarga: boolean;
  kontak: { whatsapp: string; email: string };
  latar: { video: string | null; poster: string | null; paket: string | null };
  tahun: number;
}>();

/** id produk => banyaknya. Nol berarti tidak dipilih. */
const pilih = reactive<Record<number, number>>({});

const saring = ref<string | null>(null);

const terjual = computed(() => props.aplikasi.filter((a) => a.id !== null));

const tampil = computed(() => (saring.value === null
  ? props.aplikasi
  : props.aplikasi.filter((a) => a.pilar === saring.value)));

const pilarList = computed(() => Object.entries(props.pilar));

/** Semua yang dapat dibeli, paket dan satuan, dalam satu daftar. */
const semua = computed<{ id: number; nama: string; harga: number }[]>(() => [
  ...(props.paket ? [{ id: props.paket.id, nama: props.paket.nama, harga: props.paket.harga }] : []),
  ...terjual.value.map((a) => ({ id: a.id as number, nama: a.nama, harga: a.harga as number })),
]);

const terpilih = computed(() => semua.value.filter((p) => (pilih[p.id] ?? 0) > 0));

const total = computed(() =>
  terpilih.value.reduce((n, p) => n + p.harga * (pilih[p.id] ?? 0), 0));

const jumlahButir = computed(() =>
  terpilih.value.reduce((n, p) => n + (pilih[p.id] ?? 0), 0));

/**
 * Harga satuan termurah, dipakai kalimat hero.
 *
 * Dihitung dari yang benar-benar berharga saja; nol dari kartu tanpa
 * harga akan membuat halaman depan menjanjikan "mulai Rp 0".
 */
const termurah = computed(() => {
  const h = terjual.value.map((a) => a.harga as number).filter((n) => n > 0);

  return h.length ? Math.min(...h) : null;
});

/**
 * Empat angka hero.
 *
 * "0 Siap dibeli" tidak pernah terpajang. Angka nol di halaman jual
 * tidak dibaca sebagai harga yang belum diumumkan — ia dibaca sebagai
 * perusahaan yang tidak punya apa pun untuk dijual, tepat di baris
 * pertama yang dilihat calon pembeli.
 */
const angka = computed<[string | number, string][]>(() => [
  [props.aplikasi.length, 'Aplikasi'],
  ...(terjual.value.length
    ? [[terjual.value.length, 'Siap dibeli'] as [number, string]]
    : [['Terpadu', 'Satu basis data'] as [string, string]]),
  ['QRIS', 'Cara bayar'],
  ['Tanpa akun', 'Untuk memesan'],
]);

function rupiah(n: number | null) {
  return n === null ? '' : 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

function ubah(id: number, n: number) {
  pilih[id] = Math.max(0, Math.min(99, (pilih[id] ?? 0) + n));
}

function buang(id: number) {
  pilih[id] = 0;
}

function keForm() {
  document.getElementById('pesan')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function keAplikasi(slug: string) {
  saring.value = saring.value === slug ? null : slug;
  document.getElementById('aplikasi')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function ambilSemua() {
  for (const a of terjual.value) pilih[a.id as number] = 1;
  keForm();
}

/**
 * Pengguna yang meminta gerakan dikurangi mendapat poster diam.
 *
 * Bukan sekadar sopan santun: latar bergerak memicu mual dan pusing pada
 * sebagian orang. Dibaca sekali saat pemasangan — bukan lewat kelas CSS
 * — supaya videonya memang tidak diunduh sama sekali.
 */
const kurangiGerak = ref(
  typeof window !== 'undefined'
  && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true,
);

const f = useForm<Record<string, any>>({
  pembeli_nama: '', pembeli_perusahaan: '', pembeli_email: '',
  pembeli_telepon: '', catatan: '', produk: {} as Record<number, number>,
});

function kirim() {
  f.produk = Object.fromEntries(terpilih.value.map((p) => [p.id, pilih[p.id]]));

  f.post('/katalog/pesan', { preserveScroll: true });
}

const waUrl = computed(() => (props.kontak.whatsapp
  ? 'https://wa.me/' + props.kontak.whatsapp.replace(/\D/g, '')
  : null));

/**
 * Kemana tombol "minta penawaran" menuju bila tidak ada satu pun cara
 * hubung yang diatur.
 *
 * Tanpa cadangan ini, ajakan "sebutkan kebutuhan Anda, penawarannya kami
 * kirim" berdiri tanpa satu pun tombol di bawahnya — kalimat yang
 * meminta sesuatu lalu tidak menyediakan caranya.
 */
const tanya = computed(() => (waUrl.value
  ?? (props.kontak.email ? 'mailto:' + props.kontak.email : null)));

/**
 * Empat langkah membeli, digambar dengan vektornya sendiri.
 *
 * Angka bulat 01–04 sendirian tidak menceritakan apa pun; yang
 * menjelaskan bentuknya. Digambar sebagai path inline, bukan pustaka
 * ikon: repo ini sudah sekali melepas pustaka dari CDN, dan ikon yang
 * gagal dimuat pada jaringan site tambang meninggalkan kotak kosong
 * persis di tempat yang paling menjelaskan.
 */
const langkah: { judul: string; ket: string; jalur: string[] }[] = [
  {
    judul: 'Pilih',
    ket: 'Ambil paket menyeluruh, atau aplikasi satuan yang Anda butuhkan.',
    jalur: ['M6.5 7.5h11l1.4 10.2a2 2 0 0 1-2 2.3H7.1a2 2 0 0 1-2-2.3L6.5 7.5Z',
            'M9 7.5V6a3 3 0 0 1 6 0v1.5', 'm10 13.6 1.6 1.6 3-3.2'],
  },
  {
    judul: 'Tagihan terbit',
    ket: 'Nomor tagihan dan tautan pembayaran muncul seketika — tanpa perlu akun.',
    jalur: ['M6 3.2h9.2L19 7v13.8H6V3.2Z', 'M15 3.2V7h4', 'M9 12h7', 'M9 15.5h7', 'M9 8.5h3'],
  },
  {
    judul: 'Bayar QRIS',
    ket: 'Pindai kodenya dari aplikasi bank atau dompet digital mana pun.',
    jalur: ['M4 4h6v6H4V4Z', 'M14 4h6v6h-6V4Z', 'M4 14h6v6H4v-6Z',
            'M14 14h2.5v2.5H14V14Z', 'M19.5 14H20v2.5', 'M17 19.5h3', 'M14 19.5h.5'],
  },
  {
    judul: 'Lisensi aktif',
    ket: 'Bukti bayar diperiksa, lisensinya terbit, dan akses Anda menyala.',
    jalur: ['M8.5 13.5a4 4 0 1 1 3.9-4.9L20 8.6l1.5 1.6-1.6 1.7-1.6-1.1-1.6 1.4-1.7-1.3-2.6 2.4a4 4 0 0 1-3.9 0Z',
            'M7.2 9.4h.01'],
  },
];

/** Alasan membeli, dengan vektornya masing-masing. */
const alasan: { judul: string; ket: string; jalur: string[] }[] = [
  {
    judul: 'Data terpisah per perusahaan',
    ket: 'Tiap perusahaan hanya melihat datanya sendiri, dijaga di lapisan basis data.',
    jalur: ['M12 2.8 4.6 5.7v5.6c0 4.5 3.1 8.6 7.4 9.9 4.3-1.3 7.4-5.4 7.4-9.9V5.7L12 2.8Z',
            'm8.9 11.9 2.2 2.2 4-4.4'],
  },
  {
    judul: 'Pembaruan aplikasi termasuk',
    ket: 'Modul baru dan perbaikan masuk tanpa biaya tambahan selama masa berlaku.',
    jalur: ['M20.5 12a8.5 8.5 0 1 1-2.6-6.1', 'M20.5 4.5V10h-5.5'],
  },
  {
    judul: 'Pendampingan pemasangan',
    ket: 'Pemasangan di server Anda, pemindahan data awal, dan pelatihan penggunanya.',
    jalur: ['M16.5 20v-1.6a3.4 3.4 0 0 0-3.4-3.4H6.9a3.4 3.4 0 0 0-3.4 3.4V20',
            'M10 11.6a3.6 3.6 0 1 0 0-7.2 3.6 3.6 0 0 0 0 7.2Z',
            'M20.5 20v-1.6a3.4 3.4 0 0 0-2.5-3.3', 'M15.5 4.6a3.4 3.4 0 0 1 0 6.6'],
  },
  {
    judul: 'Sesuai regulasi Minerba',
    ket: 'Kepdirjen 185.K/2019, Permen ESDM 26/2018, dan seri SNI ISO yang berlaku.',
    jalur: ['M9 3.5h9.5v17H5.5v-14', 'M5.5 6.5 9 3.5v3H5.5Z', 'M9 11h6', 'M9 14.5h6', 'M9 17.5h4'],
  },
];

/**
 * Bilah keranjang menyingkir begitu formulirnya terlihat.
 *
 * Tanpa ini, pada ponsel bilah itu duduk di atas isian terbawah selama
 * seluruh pengisian — persis pada layar tersempit, tempat ruangnya paling
 * mahal. Ia berguna ketika pilihannya berada beberapa layar di atas
 * tombol pesannya; begitu keduanya terlihat bersama, ia hanya menutupi.
 */
const formTerlihat = ref(false);
let pengamat: IntersectionObserver | null = null;

onMounted(() => {
  const el = document.getElementById('pesan');

  /* IntersectionObserver tidak ada pada peramban yang sangat lama.
     Tanpa penjagaan ini halamannya gagal dipasang seluruhnya — dan yang
     hilang bukan bilahnya saja melainkan katalognya. */
  if (! el || typeof IntersectionObserver === 'undefined') return;

  pengamat = new IntersectionObserver(
    ([e]) => { formTerlihat.value = e.isIntersecting; },
    { threshold: 0.12 },
  );

  pengamat.observe(el);
});

onBeforeUnmount(() => pengamat?.disconnect());
</script>

<template>
  <Head title="Beli EQOHSEE — Paket dan Aplikasi" />

  <div class="bg-cam-bg text-cam-ink scroll-smooth">

    <!-- ══════════ kepala ══════════ -->
    <header class="fixed inset-x-0 top-0 z-40 border-b border-white/10 bg-cam-ink/85 backdrop-blur-md text-white">
      <div class="max-w-6xl mx-auto px-5 h-[66px] flex items-center gap-3">
        <Link href="/" class="shrink-0"><Wordmark :tinggi="30" /></Link>
        <nav class="ml-auto hidden md:flex items-center gap-1 text-[12.5px] font-semibold">
          <a v-for="item in [['#paket','Paket'],['#aspek','Aspek'],['#aplikasi','Aplikasi'],['#cara','Cara Beli']]"
             :key="item[0]" :href="item[0]"
             class="px-3 py-2 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">{{ item[1] }}</a>
        </nav>
        <Link href="/login"
              class="ml-2 glass rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:bg-white/15 transition">
          Masuk
        </Link>
      </div>
    </header>

    <!-- ══════════ hero ══════════ -->
    <section class="relative brand-gradient text-white overflow-hidden pt-[66px]">
      <!-- Rekaman tambang sungguhan sebagai latar, sama dengan halaman
           depan. `muted` bukan pilihan gaya melainkan syarat: peramban
           menolak memutar video bersuara tanpa pengguna mengklik lebih
           dulu, dan penolakan itu tidak memunculkan galat — videonya
           hanya diam di bingkai pertama. -->
      <video v-if="latar.video && !kurangiGerak"
             :src="latar.video" :poster="latar.poster ?? undefined"
             autoplay muted loop playsinline preload="metadata" aria-hidden="true"
             class="absolute inset-0 w-full h-full object-cover opacity-[0.28]"></video>
      <img v-else-if="latar.poster" :src="latar.poster" alt=""
           class="absolute inset-0 w-full h-full object-cover opacity-[0.24]">

      <!-- Garis kontur DI BAWAH selubung gelap, bukan di atasnya.

           Digambar di atas, garisnya melintasi judul dan membuat huruf
           putih setebal 56px terbaca bergaris — hiasan yang merusak
           satu-satunya kalimat yang harus terbaca lebih dulu. Peta
           topografi memang gambar yang setiap hari dibaca orang tambang,
           tetapi tempatnya di belakang. -->
      <svg class="etalase-topo" viewBox="0 0 1200 420" preserveAspectRatio="none" aria-hidden="true">
        <g fill="none" stroke="currentColor" stroke-width="1.1">
          <path d="M-20 340C160 300 250 250 420 262s250 84 430 52 300-96 390-92" />
          <path d="M-20 300C160 258 250 206 420 219s250 86 430 53 300-99 390-95" />
          <path d="M-20 258C160 214 250 160 420 174s250 88 430 54 300-101 390-97" />
          <path d="M-20 214C160 168 250 112 420 127s250 90 430 55 300-104 390-100" />
          <path d="M-20 168C160 120 250 62 420 78s250 92 430 56 300-106 390-102" />
          <path d="M-20 120C160 70 250 10 420 27s250 94 430 57 300-109 390-105" />
        </g>
      </svg>

      <div class="absolute inset-0 bg-gradient-to-r from-cam-black/95 via-cam-black/70 to-cam-black/30"></div>

      <div class="relative max-w-6xl mx-auto px-5 py-16 md:py-24">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_370px] gap-9 lg:gap-12 items-center">
          <div>
            <span class="inline-flex items-center gap-2 glass rounded-full px-3.5 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.18em] text-cam-lime-light">
              <span class="w-1.5 h-1.5 rounded-full bg-cam-lime animate-pulse"></span>
              {{ aplikasi.length }} Aplikasi · Satu Platform
            </span>

            <h1 class="font-display text-[34px] sm:text-[44px] xl:text-[56px] font-black mt-5 leading-[1.05]">
              Beli sekali,<span class="sheen block">pakai seluruh platformnya.</span>
            </h1>

            <p class="text-[14px] md:text-[15.5px] mt-5 leading-relaxed max-w-xl text-white/75">
              EQOHSEE menjual paket menyeluruh maupun aplikasi satuan — pelatihan, kelayakan
              kerja, hazard report, investigasi insiden, sampai kendali biaya. Semua berbagi satu
              basis data perusahaan, pengguna, dan peran.
            </p>

            <div class="flex flex-wrap gap-2.5 mt-8">
              <a href="#paket" class="lime-gradient shadow-glow rounded-xl text-white px-6 py-3.5 text-[13.5px] font-bold">
                Lihat paket
              </a>
              <a href="#aplikasi" class="glass rounded-xl px-6 py-3.5 text-[13.5px] font-bold">
                Beli satuan
              </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mt-10 max-w-2xl">
              <div v-for="s in angka" :key="String(s[1])" class="glass rounded-xl px-4 py-3.5">
                <div class="stat stat-sm text-cam-lime-light">{{ s[0] }}</div>
                <div class="text-[10.5px] text-white/55 mt-1.5">{{ s[1] }}</div>
              </div>
            </div>
          </div>

          <!-- ringkas harga -->
          <aside class="glass rounded-2xl p-6 relative overflow-hidden">
            <!-- Cahaya sudut, memberi kedalaman pada panel kaca yang
                 kalau rata terbaca sebagai kotak abu. -->
            <span class="etalase-kilau" aria-hidden="true"></span>

            <template v-if="paket">
              <div class="text-[10.5px] font-bold uppercase tracking-[0.2em] text-cam-lime-light">Paket menyeluruh</div>
              <div class="num font-display text-[34px] font-black mt-2 leading-none">{{ rupiah(paket.harga) }}</div>
              <div class="text-[11.5px] text-white/50 mt-1.5">{{ paket.masa }} · seluruh aplikasi di dalamnya</div>
              <button type="button" class="w-full lime-gradient shadow-glow rounded-xl text-white px-5 py-3 text-[13px] font-bold mt-5"
                      @click="ubah(paket.id, 1); keForm()">
                Ambil paket ini
              </button>
              <div v-if="termurah !== null" class="text-[11.5px] text-white/45 mt-3 text-center">
                atau beli satuan mulai <span class="num font-semibold text-white/70">{{ rupiah(termurah) }}</span>
              </div>
            </template>

            <template v-else-if="termurah !== null">
              <div class="text-[10.5px] font-bold uppercase tracking-[0.2em] text-cam-lime-light">Aplikasi satuan</div>
              <div class="num font-display text-[34px] font-black mt-2 leading-none">{{ rupiah(termurah) }}</div>
              <div class="text-[11.5px] text-white/50 mt-1.5">harga mulai · {{ terjual.length }} aplikasi siap dibeli</div>
              <a href="#aplikasi" class="block text-center w-full lime-gradient shadow-glow rounded-xl text-white px-5 py-3 text-[13px] font-bold mt-5">
                Pilih aplikasi
              </a>
            </template>

            <!-- Harga belum diumumkan. Yang ditawarkan percakapan, bukan
                 tombol beli yang tidak menuju ke mana-mana. -->
            <template v-else>
              <div class="text-[10.5px] font-bold uppercase tracking-[0.2em] text-cam-lime-light">Penawaran</div>
              <p class="text-[13px] text-white/70 mt-3 leading-relaxed">
                Harga disusun menurut jumlah pengguna dan aplikasi yang dipilih. Sebutkan
                kebutuhan Anda, penawarannya kami kirim.
              </p>
              <a v-if="tanya" :href="tanya" :target="waUrl ? '_blank' : undefined" rel="noopener"
                 class="block text-center w-full lime-gradient shadow-glow rounded-xl text-white px-5 py-3 text-[13px] font-bold mt-5">
                Minta penawaran
              </a>
              <a v-else href="/#modul"
                 class="block text-center w-full lime-gradient shadow-glow rounded-xl text-white px-5 py-3 text-[13px] font-bold mt-5">
                Lihat seluruh modul
              </a>
            </template>

            <ul class="space-y-3 mt-6 pt-5 border-t border-white/10">
              <li v-for="a in alasan" :key="a.judul" class="flex gap-3">
                <span class="shrink-0 w-7 h-7 rounded-lg bg-cam-lime/15 text-cam-lime-light grid place-items-center">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path v-for="(d, i) in a.jalur" :key="i" :d="d" />
                  </svg>
                </span>
                <span class="text-[11.5px] text-white/60 leading-snug pt-0.5">{{ a.judul }}</span>
              </li>
            </ul>
          </aside>
        </div>
      </div>
    </section>

    <!-- ══════════ paket ══════════ -->
    <section id="paket" class="max-w-6xl mx-auto px-5 py-16 md:py-20 scroll-mt-[80px]">
      <div class="text-center max-w-xl mx-auto">
        <span class="text-[10.5px] font-bold uppercase tracking-[0.22em] text-cam-lime-dark">Paling banyak diambil</span>
        <h2 class="font-display text-[30px] md:text-[38px] font-black text-cam-ink mt-3">Satu paket, semuanya</h2>
        <p class="text-[13.5px] text-stone-500 mt-3 leading-relaxed">
          Membeli seluruh aplikasi terpisah selalu lebih mahal daripada paketnya — dan yang
          terpisah tidak berbagi data begitu saja.
        </p>
      </div>

      <div v-if="paket" class="relative mt-10 rounded-3xl overflow-hidden text-white shadow-card brand-gradient">
        <!-- Sama seperti kartu aspek: pita bawahnya membawa lencana
             penyunting yang terbakar di dalam berkasnya. -->
        <img v-if="latar.paket" :src="latar.paket" alt=""
             class="absolute inset-x-0 top-0 w-full h-[124%] object-cover object-top opacity-25">
        <div class="absolute inset-0 bg-gradient-to-r from-cam-black/95 via-cam-black/80 to-cam-black/45"></div>

        <div class="relative grid lg:grid-cols-[minmax(0,1fr)_320px] gap-8 p-8 md:p-11">
          <div>
            <span class="inline-flex items-center gap-2 rounded-full bg-cam-lime/20 text-cam-lime-light px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em]">
              Paket menyeluruh
            </span>
            <h3 class="font-display text-[24px] md:text-[30px] font-black mt-4">{{ paket.nama }}</h3>
            <p v-if="paket.ket" class="text-[13px] text-white/70 mt-3 max-w-xl leading-relaxed">{{ paket.ket }}</p>

            <div class="flex flex-wrap gap-1.5 mt-6">
              <span v-for="a in aplikasi" :key="a.nama"
                    class="inline-flex items-center gap-1.5 text-[10.5px] font-semibold rounded-full pl-1.5 pr-2.5 py-1 bg-white/[0.07] border border-white/10 text-white/75">
                <span class="w-4 h-4 rounded grid place-items-center shrink-0"
                      :style="{ background: `linear-gradient(135deg, ${a.pilarDeep}, ${a.pilarWarna})` }">
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff"
                       stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path :d="a.ikon" />
                  </svg>
                </span>
                {{ a.nama }}
              </span>
            </div>
          </div>

          <div class="lg:border-l lg:border-white/15 lg:pl-8 flex flex-col justify-center">
            <div class="text-[10.5px] font-bold uppercase tracking-[0.2em] text-cam-lime-light">Harga</div>
            <div class="num font-display text-[34px] font-black mt-1.5 leading-none">{{ rupiah(paket.harga) }}</div>
            <div class="text-[11.5px] text-white/55 mt-1.5">{{ paket.masa }}</div>

            <span class="inline-flex items-center gap-2 mt-5">
              <button type="button" class="beli-plusmin" aria-label="Kurangi" @click="ubah(paket.id, -1)">−</button>
              <span class="num w-7 text-center text-[14px] font-bold">{{ pilih[paket.id] ?? 0 }}</span>
              <button type="button" class="beli-plusmin" aria-label="Tambah" @click="ubah(paket.id, 1)">+</button>
            </span>

            <button type="button"
                    class="lime-gradient shadow-glow rounded-xl text-white px-5 py-3 text-[13px] font-bold mt-4"
                    @click="ubah(paket.id, 1); keForm()">
              Beli paket
            </button>
          </div>
        </div>
      </div>

      <!-- Paketnya belum berharga. Disebutkan, bukan dibiarkan sebagai
           lubang di tengah halaman. -->
      <div v-else class="relative mt-10 rounded-3xl overflow-hidden border border-stone-200 bg-white shadow-card px-7 py-10 text-center">
        <span class="etalase-bintik" aria-hidden="true"></span>
        <div class="relative">
          <span class="inline-grid place-items-center w-14 h-14 rounded-2xl lime-gradient text-white">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M20 11.5a8 8 0 1 1-3.4-6.5" /><path d="M9.5 9.5a2.6 2.6 0 1 1 3.1 2.6v1.6" />
              <path d="M12.6 17h.01" />
            </svg>
          </span>
          <h3 class="font-display text-[22px] font-black text-cam-ink mt-4">Paket disusun sesuai kebutuhan</h3>
          <p class="text-[13px] text-stone-500 mt-3 max-w-lg mx-auto leading-relaxed">
            Jumlah pengguna, aplikasi yang dipakai, dan lama berlangganan menentukan harganya.
            Sebutkan kebutuhan Anda — penawarannya kami susun.
          </p>
          <a v-if="tanya" :href="tanya" :target="waUrl ? '_blank' : undefined" rel="noopener"
             class="inline-block lime-gradient shadow-glow rounded-xl text-white px-6 py-3 text-[13px] font-bold mt-6">
            Minta penawaran
          </a>
          <a v-else href="#aplikasi"
             class="inline-block lime-gradient shadow-glow rounded-xl text-white px-6 py-3 text-[13px] font-bold mt-6">
            Lihat aplikasinya
          </a>
        </div>
      </div>
    </section>

    <!-- ══════════ aspek: foto lapangan ══════════ -->
    <section id="aspek" class="bg-cam-ink text-white scroll-mt-[80px]">
      <div class="max-w-6xl mx-auto px-5 py-16 md:py-20">
        <div class="text-center max-w-xl mx-auto">
          <span class="text-[10.5px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">Cakupan</span>
          <h2 class="font-display text-[30px] md:text-[38px] font-black mt-3">Aspek yang ditangani</h2>
          <p class="text-[13.5px] text-white/50 mt-3 leading-relaxed">
            Klik satu aspek untuk melihat aplikasi yang menopangnya.
          </p>
        </div>

        <div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3 mt-10">
          <button v-for="[slug, w] in pilarList" :key="slug" type="button"
                  class="etalase-aspek group"
                  :class="saring === slug ? 'etalase-aspek-aktif' : ''"
                  @click="keAplikasi(slug)">
            <!-- Pita bawah foto DIPOTONG, dan itu bukan pilihan gaya.

                 Berkas galerinya membawa tulisan yang terbakar di dalam
                 gambarnya: occhealth.jpg mencetak "Occupational Health"
                 di tengah bawah, dan lima berkas lain membawa lencana
                 bintang penyunting di kanan bawah. Ditampilkan utuh,
                 kartunya menyebut nama aspeknya dua kali — sekali
                 sebagai label, sekali sebagai tulisan buram yang tidak
                 sejajar dengan apa pun — dan halaman jual yang begitu
                 terbaca sebagai contoh templat, bukan sebagai produk.

                 Tingginya dilebihkan lalu dijangkarkan ke atas; sisanya
                 terpotong oleh overflow-hidden kartunya. Berkas fotonya
                 sendiri tidak disentuh: menggantinya dengan yang bersih
                 membuat baris ini tidak lagi berguna, tetapi juga tidak
                 merusak apa pun. -->
            <img v-if="w.foto" :src="w.foto" alt="" loading="lazy" decoding="async"
                 class="absolute inset-x-0 top-0 w-full h-[124%] object-cover object-top
                        opacity-45 group-hover:opacity-60 transition-opacity duration-300">
            <!-- Belum ada fotonya. Gradien pilarnya, dengan ikon aspeknya
                 sendiri sebagai cap besar — supaya kartunya tetap punya
                 bentuk, bukan sekadar bidang warna di antara kartu
                 berfoto. -->
            <span v-else class="absolute inset-0 grid place-items-center overflow-hidden"
                  :style="{ background: `linear-gradient(140deg, ${w.deep}, ${w.warna})` }">
              <span class="etalase-aspek-cap"><IkonPilar :nama="w.ikon" :ukuran="150" /></span>
            </span>

            <span class="absolute inset-0"
                  :style="{ background: `linear-gradient(180deg, ${w.deep}22 0%, #0B1117E6 78%)` }"></span>

            <span class="relative flex flex-col h-full p-4">
              <span class="w-10 h-10 rounded-xl grid place-items-center text-white shrink-0"
                    :style="{ background: `linear-gradient(135deg, ${w.deep}, ${w.light})` }">
                <IkonPilar :nama="w.ikon" :ukuran="20" />
              </span>
              <span class="mt-auto pt-6">
                <span class="block text-[13.5px] font-bold">{{ w.nama }}</span>
                <span class="block num text-[10.5px] mt-1" :style="{ color: w.light }">
                  {{ w.jumlah }} aplikasi
                </span>
              </span>
            </span>
          </button>
        </div>
      </div>
    </section>

    <!-- ══════════ aplikasi satuan ══════════ -->
    <section id="aplikasi" class="relative bg-gradient-to-b from-cam-bg via-white to-cam-bg scroll-mt-[80px] overflow-hidden">
      <span class="etalase-aura etalase-aura-kiri" aria-hidden="true"></span>
      <span class="etalase-aura etalase-aura-kanan" aria-hidden="true"></span>

      <div class="relative max-w-6xl mx-auto px-5 py-16 md:py-20">
        <div class="text-center max-w-xl mx-auto">
          <span class="text-[10.5px] font-bold uppercase tracking-[0.22em] text-cam-lime-dark">Aplikasi satuan</span>
          <h2 class="font-display text-[30px] md:text-[38px] font-black text-cam-ink mt-3">
            Ambil yang dipakai saja
          </h2>
          <p class="text-[13.5px] text-stone-500 mt-3 leading-relaxed">
            Tiap aplikasi berdiri sendiri dan tetap terhubung dengan yang lain begitu ditambahkan.
          </p>
        </div>

        <!-- saring per pilar -->
        <div class="flex flex-wrap justify-center gap-1.5 mt-8">
          <button type="button" class="etalase-chip" :class="saring === null ? 'etalase-chip-aktif' : ''"
                  @click="saring = null">
            Semua <span class="num opacity-60">{{ aplikasi.length }}</span>
          </button>
          <button v-for="[slug, w] in pilarList" :key="slug" type="button"
                  class="etalase-chip inline-flex items-center gap-1.5"
                  :class="saring === slug ? 'etalase-chip-aktif' : ''"
                  :style="saring === slug ? { background: w.deep, borderColor: w.deep, color: '#fff' } : { borderColor: w.warna + '55', color: w.deep }"
                  @click="saring = saring === slug ? null : slug">
            <span class="w-1.5 h-1.5 rounded-full" :style="{ background: saring === slug ? '#fff' : w.warna }"></span>
            {{ w.nama }}
          </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mt-8">
          <article v-for="a in tampil" :key="a.nama" class="etalase-kartu group">
            <span class="absolute inset-x-0 top-0 h-[3px]"
                  :style="{ background: `linear-gradient(90deg, ${a.pilarDeep}, ${a.pilarWarna})` }"></span>

            <!-- Ikon modulnya sendiri, dibesarkan dan diredupkan sebagai
                 cap air. Tiap kartu jadi berbeda tanpa satu berkas gambar
                 pun ditambahkan, dan bentuknya memang bentuk yang sama
                 dengan ikon kecil di atasnya. -->
            <svg class="etalase-cap" viewBox="0 0 24 24" fill="none" :stroke="a.pilarWarna"
                 stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path :d="a.ikon" />
            </svg>

            <div class="relative flex flex-col h-full">
              <div class="w-12 h-12 rounded-xl grid place-items-center text-white shadow-sm transition-transform duration-300 group-hover:scale-105"
                   :style="{ background: `linear-gradient(135deg, ${a.pilarDeep}, ${a.pilarWarna})` }">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path :d="a.ikon" />
                </svg>
              </div>

              <h3 class="text-[14.5px] font-bold text-cam-ink mt-4">{{ a.nama }}</h3>
              <p class="text-[12.5px] text-stone-500 mt-2 leading-relaxed flex-1">{{ a.ket }}</p>

              <div class="inline-flex items-center gap-1.5 text-[11px] font-bold mt-4"
                   :style="{ color: a.pilarDeep }">
                <span class="w-1.5 h-1.5 rounded-full" :style="{ background: a.pilarWarna }"></span>
                Pilar {{ a.pilarNama }}
              </div>

              <div class="flex items-end justify-between gap-3 mt-4 pt-4 border-t border-stone-100">
                <div v-if="a.id !== null">
                  <div class="num text-[17px] font-black text-cam-ink leading-none">{{ rupiah(a.harga) }}</div>
                  <div class="text-[10.5px] text-stone-400 mt-1">{{ a.masa }}</div>
                </div>
                <div v-else class="text-[11.5px] text-stone-400 leading-snug">
                  Hubungi kami<br>untuk penawaran
                </div>

                <span v-if="a.id !== null" class="inline-flex items-center gap-1.5 shrink-0">
                  <button type="button" class="beli-plusmin" aria-label="Kurangi" @click="ubah(a.id, -1)">−</button>
                  <span class="num w-6 text-center text-[13px] font-bold">{{ pilih[a.id] ?? 0 }}</span>
                  <button type="button" class="beli-plusmin" aria-label="Tambah" @click="ubah(a.id, 1)">+</button>
                </span>
              </div>
            </div>
          </article>
        </div>

        <div v-if="terjual.length > 1" class="text-center mt-8">
          <button type="button" class="text-[12.5px] font-bold text-cam-lime-dark hover:underline"
                  @click="ambilSemua">
            Pilih semua {{ terjual.length }} aplikasi
          </button>
        </div>
      </div>
    </section>

    <!-- ══════════ cara beli ══════════ -->
    <section id="cara" class="max-w-6xl mx-auto px-5 py-16 md:py-20 scroll-mt-[80px]">
      <div class="text-center max-w-xl mx-auto">
        <span class="text-[10.5px] font-bold uppercase tracking-[0.22em] text-cam-lime-dark">Cara Beli</span>
        <h2 class="font-display text-[30px] md:text-[38px] font-black text-cam-ink mt-3">Empat langkah</h2>
      </div>

      <!-- Rel penghubung, hanya pada lebar yang benar-benar menampung
           empat kolom sejajar. Pada dua kolom ia akan menyambungkan
           langkah 2 ke langkah 3 yang berada di baris berbeda —
           menggambar urutan yang salah. -->
      <div class="relative grid gap-5 sm:grid-cols-2 lg:grid-cols-4 mt-11">
        <span class="etalase-rel" aria-hidden="true"></span>

        <div v-for="(l, i) in langkah" :key="l.judul" class="relative text-center">
          <span class="etalase-langkah">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path v-for="(d, j) in l.jalur" :key="j" :d="d" />
            </svg>
            <span class="etalase-langkah-angka num">{{ i + 1 }}</span>
          </span>
          <h3 class="text-[13.5px] font-bold text-cam-ink mt-4">{{ l.judul }}</h3>
          <p class="text-[12px] text-stone-500 mt-1.5 leading-relaxed max-w-[16rem] mx-auto">{{ l.ket }}</p>
        </div>
      </div>

      <!-- alasan, lebih lengkap daripada ringkasan di hero -->
      <div class="grid gap-x-8 gap-y-7 sm:grid-cols-2 mt-14 max-w-4xl mx-auto">
        <div v-for="a in alasan" :key="a.judul" class="flex gap-4">
          <span class="shrink-0 w-10 h-10 rounded-xl grid place-items-center text-white lime-gradient shadow-sm">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path v-for="(d, i) in a.jalur" :key="i" :d="d" />
            </svg>
          </span>
          <div>
            <h3 class="text-[13.5px] font-bold text-cam-ink">{{ a.judul }}</h3>
            <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed">{{ a.ket }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ pemesanan ══════════ -->
    <section id="pesan" class="max-w-6xl mx-auto px-5 pb-16 md:pb-20 scroll-mt-[80px]">
      <div class="grid gap-4 lg:grid-cols-[1fr_.8fr] items-start">

        <div class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-6 py-4 border-b border-stone-100">
            <h3 class="text-[14.5px] font-bold text-cam-ink">Pesanan Anda</h3>
            <p class="text-[11.5px] text-stone-500 mt-0.5">
              Harga dihitung ulang di server saat tagihan dibuat.
            </p>
          </header>

          <ul v-if="terpilih.length" class="divide-y divide-stone-100">
            <!-- Menumpuk di layar sempit, berjajar mulai sm.

                 Berjajar pada 390px, empat bagian berlebar tetap
                 mendesak namanya sampai tersisa "Websi…" dan memecah
                 "Rp 80.000.000" menjadi dua baris di tengah angka.
                 Terlihat saat dipotret; tidak terlihat sama sekali pada
                 lebar meja kerja. -->
            <li v-for="p in terpilih" :key="p.id"
                class="px-6 py-3.5 flex flex-col sm:flex-row sm:items-center gap-2.5 sm:gap-3">
              <span class="min-w-0 flex-1 flex items-start gap-2">
                <span class="min-w-0 flex-1">
                  <span class="block text-[12.5px] font-semibold text-cam-ink">{{ p.nama }}</span>
                  <span class="block num text-[11px] text-stone-400 mt-0.5">
                    {{ rupiah(p.harga) }} per butir
                  </span>
                </span>
                <button type="button" class="sm:hidden text-stone-300 hover:text-red-500 text-lg leading-none shrink-0"
                        aria-label="Buang" @click="buang(p.id)">×</button>
              </span>

              <span class="flex items-center gap-3 shrink-0">
                <span class="inline-flex items-center gap-1.5">
                  <button type="button" class="beli-plusmin" aria-label="Kurangi" @click="ubah(p.id, -1)">−</button>
                  <span class="num w-6 text-center text-[13px] font-bold">{{ pilih[p.id] }}</span>
                  <button type="button" class="beli-plusmin" aria-label="Tambah" @click="ubah(p.id, 1)">+</button>
                </span>

                <span class="num text-[13px] font-bold text-cam-ink ml-auto sm:ml-0 sm:w-28 sm:text-right">
                  {{ rupiah(p.harga * pilih[p.id]) }}
                </span>

                <button type="button"
                        class="hidden sm:block text-stone-300 hover:text-red-500 text-lg leading-none shrink-0"
                        aria-label="Buang" @click="buang(p.id)">×</button>
              </span>
            </li>
          </ul>

          <p v-else class="px-6 py-10 text-center text-[12.5px] text-stone-400">
            Belum ada yang dipilih. Ambil paketnya, atau tambahkan aplikasi satuan di atas.
          </p>

          <div class="px-6 py-4 flex items-baseline justify-between border-t border-stone-100 bg-stone-50/60">
            <span class="text-[12.5px] font-bold text-stone-500">Total</span>
            <span class="num text-[22px] font-black text-cam-ink">{{ rupiah(total) }}</span>
          </div>
        </div>

        <form class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden"
              @submit.prevent="kirim">
          <header class="px-6 py-4 border-b border-stone-100">
            <h3 class="text-[14.5px] font-bold text-cam-ink">Data pembeli</h3>
            <p class="text-[11.5px] text-stone-500 mt-0.5">Tidak perlu membuat akun lebih dulu.</p>
          </header>

          <div class="px-6 py-5 grid gap-2.5">
            <input v-model="f.pembeli_nama" required placeholder="Nama pembeli" class="beli-isian" />
            <p v-if="f.errors.pembeli_nama" class="text-[11.5px] text-red-600">{{ f.errors.pembeli_nama }}</p>

            <input v-model="f.pembeli_perusahaan" placeholder="Perusahaan (opsional)" class="beli-isian" />
            <input v-model="f.pembeli_email" type="email" placeholder="Email (opsional)" class="beli-isian" />
            <p v-if="f.errors.pembeli_email" class="text-[11.5px] text-red-600">{{ f.errors.pembeli_email }}</p>

            <input v-model="f.pembeli_telepon" placeholder="Telepon / WhatsApp (opsional)" class="beli-isian" />
            <textarea v-model="f.catatan" rows="2" placeholder="Catatan (opsional)" class="beli-isian"></textarea>

            <p v-if="f.errors.produk" class="text-[11.5px] text-red-600">{{ f.errors.produk }}</p>

            <button type="submit" class="eq-btn-utama justify-center"
                    :disabled="!terpilih.length || f.processing">
              Buat tagihan
            </button>

            <p class="text-[10.5px] text-stone-400 leading-snug">
              Tagihan dibuat lebih dulu. Pembayarannya lewat QRIS pada halaman yang muncul
              sesudahnya, dan lisensinya terbit setelah bukti bayar diperiksa.
            </p>
          </div>
        </form>
      </div>
    </section>

    <footer class="border-t border-stone-100 bg-white">
      <div class="max-w-6xl mx-auto px-5 py-7 flex flex-wrap items-center justify-between gap-3">
        <div class="font-extrabold tracking-wide text-lg">E<span class="text-cam-orange">Q</span>OHSEE</div>
        <p class="text-[11.5px] text-stone-400">Platform Terpadu Keselamatan Pertambangan · {{ tahun }}</p>
      </div>
    </footer>
  </div>

  <!-- ══════════ bilah keranjang ══════════ -->
  <transition name="etalase-bilah">
    <div v-if="terpilih.length && !formTerlihat" class="fixed inset-x-0 bottom-0 z-40 px-3 pb-3">
      <div class="max-w-3xl mx-auto rounded-2xl bg-cam-ink text-white shadow-glow
                  px-4 py-3 flex items-center gap-3">
        <span class="min-w-0">
          <span class="block num text-[17px] font-black leading-none">{{ rupiah(total) }}</span>
          <span class="block text-[10.5px] text-white/50 mt-1">
            {{ jumlahButir }} butir · {{ terpilih.length }} jenis
          </span>
        </span>
        <button type="button" class="ml-auto lime-gradient rounded-xl px-5 py-2.5 text-[12.5px] font-bold shrink-0"
                @click="keForm">
          Lanjut memesan
        </button>
      </div>
    </div>
  </transition>
</template>
