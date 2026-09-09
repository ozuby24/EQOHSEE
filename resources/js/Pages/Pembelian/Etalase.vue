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
 * ── DAFTAR HARGA, BUKAN KISI KARTU ──
 *
 * Dua puluh satu kartu seragam berjajar tiga kolom adalah bentuk yang
 * sama dengan seluruh halaman jual yang dibangkitkan mesin, dan yang
 * membacanya mengenalinya sebelum sempat membaca satu kata pun.
 * Barangnya sendiri memang sebuah daftar harga: nama, satu kalimat,
 * angka. Ditulis sebagai daftar bergaris rambut dan dikelompokkan
 * menurut aspeknya, ia lebih cepat dibandingkan, lebih mudah dipindai,
 * dan tidak menyerupai apa pun kecuali daftar harga.
 *
 * ── KARTU TANPA HARGA TETAP DIGAMBAR ──
 *
 * Aplikasi yang harganya belum ditetapkan punya `id` null. Ia tetap
 * tampil lengkap dengan keterangannya, hanya tanpa tombol beli. Yang
 * disembunyikan cara membelinya, bukan keberadaannya.
 *
 * ── GAMBARNYA BOLEH TIDAK ADA ──
 *
 * Tiap foto dan rekaman diperiksa keberadaannya di server (lihat
 * App\Support\Media) dan dikirim sebagai null bila berkasnya belum
 * ditaruh. Yang tampil sebagai gantinya bidang warna aspeknya — bukan
 * bingkai gambar rusak, yang di halaman jual lebih buruk daripada tidak
 * ada gambar sama sekali.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import Wordmark from '../../Components/Wordmark.vue';

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

const terjual = computed(() => props.aplikasi.filter((a) => a.id !== null));

const pilarList = computed(() => Object.entries(props.pilar));

/** Aplikasi dikelompokkan menurut aspeknya, urut seperti daftar pilarnya. */
const kelompok = computed(() => pilarList.value.map(([slug, w]) => ({
  slug,
  pilar: w,
  butir: props.aplikasi.filter((a) => a.pilar === slug),
})).filter((k) => k.butir.length > 0));

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
 * Harga satuan termurah.
 *
 * Dihitung dari yang benar-benar berharga saja; nol dari butir tanpa
 * harga akan membuat halamannya menjanjikan "mulai Rp 0".
 */
const termurah = computed(() => {
  const h = terjual.value.map((a) => a.harga as number).filter((n) => n > 0);

  return h.length ? Math.min(...h) : null;
});

function rupiah(n: number | null) {
  return n === null ? '' : 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

/**
 * Harga TIDAK PERNAH disingkat.
 *
 * "Rp 80 jt" enak dibaca sekilas dan salah sebagai harga: yang membaca
 * tidak tahu apakah yang dimaksud 80.000.000 atau 80.500.000 yang
 * dibulatkan, dan pertanyaan itu tidak boleh ada pada halaman yang
 * meminta orang menekan tombol beli. Yang dihemat satu baris; yang
 * hilang kepercayaan pada angkanya.
 */

function ubah(id: number, n: number) {
  pilih[id] = Math.max(0, Math.min(99, (pilih[id] ?? 0) + n));
}

function buang(id: number) {
  pilih[id] = 0;
}

function ke(id: string) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function ambilSemua() {
  for (const a of terjual.value) pilih[a.id as number] = 1;
  ke('pesan');
}

/**
 * Pengguna yang meminta gerakan dikurangi mendapat poster diam.
 *
 * Bukan sekadar sopan santun: latar bergerak memicu mual dan pusing
 * pada sebagian orang. Dibaca sekali saat pemasangan — bukan lewat
 * kelas CSS — supaya videonya memang tidak diunduh sama sekali.
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
 * Tanpa cadangan ini, ajakan "sebutkan kebutuhan Anda" berdiri tanpa
 * satu pun tautan di bawahnya — kalimat yang meminta sesuatu lalu tidak
 * menyediakan caranya.
 */
const tanya = computed(() => (waUrl.value
  ?? (props.kontak.email ? 'mailto:' + props.kontak.email : null)));

const langkah = [
  ['Pilih', 'Ambil paket menyeluruh, atau aplikasi satuan yang Anda butuhkan.'],
  ['Tagihan terbit', 'Nomor tagihan dan tautan pembayaran muncul seketika, tanpa perlu akun.'],
  ['Bayar', 'Pindai QRIS dari aplikasi bank atau dompet digital mana pun.'],
  ['Lisensi aktif', 'Bukti bayar diperiksa, lisensinya terbit, dan akses Anda menyala.'],
];

const jaminan = [
  ['Data terpisah per perusahaan', 'Tiap perusahaan hanya melihat datanya sendiri, dijaga di lapisan basis data.'],
  ['Pembaruan termasuk', 'Modul baru dan perbaikan masuk tanpa biaya tambahan selama masa berlaku.'],
  ['Pendampingan pemasangan', 'Pemasangan di server Anda, pemindahan data awal, dan pelatihan penggunanya.'],
  ['Sesuai regulasi Minerba', 'Kepdirjen 185.K/2019, Permen ESDM 26/2018, dan seri SNI ISO yang berlaku.'],
];

/**
 * Dua baris contoh untuk kartu tiruan tagihan di hero.
 *
 * Diambil dari katalog yang sungguhan bila ada isinya — bukan nama dan
 * angka karangan. Yang dipajang di halaman jual sebagai contoh tagihan
 * harus berupa barang yang benar-benar dijual pada harga yang benar-benar
 * berlaku; contoh karangan yang lupa diganti adalah harga yang salah,
 * dipajang di tempat yang paling dipercaya orang.
 */
const contohTagihan = computed(() => {
  const b = terjual.value.slice(0, 2)
    .map((a) => ({ nama: a.nama, harga: a.harga as number }));

  return { baris: b, total: b.reduce((n, x) => n + x.harga, 0) };
});

/**
 * Kepala berubah setelah halaman digulir.
 *
 * Di puncak halaman ia mengambang tanpa garis di atas rekaman; sesudah
 * digulir ia mendapat latar dan garis bawah supaya menunya tetap
 * terbaca di atas isi terang. Diambil dari scrollY, bukan dari
 * IntersectionObserver pada unsur pengintai: pengintai setinggi nol
 * piksel tidak pernah memicu apa pun pada sebagian peramban.
 */
const digulir = ref(false);
let onScroll: (() => void) | null = null;

/**
 * Bilah keranjang menyingkir begitu formulirnya terlihat.
 *
 * Tanpa ini, pada ponsel bilah itu duduk di atas isian terbawah selama
 * seluruh pengisian — persis pada layar tersempit, tempat ruangnya
 * paling mahal.
 */
const formTerlihat = ref(false);
let pengamat: IntersectionObserver | null = null;

onMounted(() => {
  onScroll = () => { digulir.value = window.scrollY > 24; };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

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

onBeforeUnmount(() => {
  pengamat?.disconnect();
  if (onScroll) window.removeEventListener('scroll', onScroll);
});
</script>

<template>
  <Head title="Beli EQOHSEE — Paket dan Aplikasi" />

  <div class="jual">

    <!-- ══════════ kepala ══════════ -->
    <header class="jual-kepala" :class="digulir ? 'jual-kepala-turun' : ''">
      <div class="jual-lebar flex items-center gap-8 h-full">
        <Link href="/" class="shrink-0 opacity-90 hover:opacity-100 transition-opacity duration-300">
          <Wordmark :tinggi="26" />
        </Link>

        <nav class="ml-auto hidden md:flex items-center gap-7">
          <a v-for="item in [['#paket','Paket'],['#aspek','Cakupan'],['#harga','Harga'],['#cara','Cara beli']]"
             :key="item[0]" :href="item[0]" class="jual-nav">{{ item[1] }}</a>
        </nav>

        <!-- Terlihat juga di ponsel. Disembunyikan di bawah sm, pelanggan
             yang SUDAH membeli tidak punya satu pun jalan masuk dari
             halaman ini — dan halaman jual adalah alamat yang paling
             mudah mereka ingat. -->
        <Link href="/login" class="jual-nav ml-auto md:ml-0">Masuk</Link>

        <!-- Terang selagi kepala mengambang di atas rekaman, pekat sesudah
             halaman digulir. Satu bentuk untuk keduanya berarti salah
             satunya hampir tak terlihat. -->
        <a href="#harga" class="jual-tombol jual-tombol-kecil"
           :class="digulir ? '' : 'jual-tombol-terang'">Lihat harga</a>
      </div>
    </header>

    <!-- ══════════ hero ══════════ -->
    <section class="jual-hero">
      <video v-if="latar.video && !kurangiGerak"
             :src="latar.video" :poster="latar.poster ?? undefined"
             autoplay muted loop playsinline preload="metadata" aria-hidden="true"
             class="jual-hero-media"></video>
      <img v-else-if="latar.poster" :src="latar.poster" alt="" class="jual-hero-media">

      <span class="jual-hero-tirai" aria-hidden="true"></span>

      <div class="jual-lebar relative">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_auto] gap-x-14 gap-y-14 items-center
                    pt-32 pb-20 md:pt-40 md:pb-28">
          <div class="max-w-[40rem]">
            <p v-singkap class="jual-mata jual-mata-terang">Platform keselamatan pertambangan</p>

            <h1 v-singkap="60" class="jual-judul mt-6">
              Satu platform,<br>
              <span class="jual-judul-tipis">dibeli utuh atau sepotong.</span>
            </h1>

            <p v-singkap="120" class="jual-tubuh-besar jual-tubuh-terang mt-7 max-w-xl">
              {{ aplikasi.length }} aplikasi untuk pelatihan, kelayakan kerja, pelaporan bahaya,
              investigasi insiden, hingga kendali biaya — berbagi satu basis data perusahaan,
              pengguna, dan peran.
            </p>

            <div v-singkap="180" class="flex flex-wrap items-center gap-3 mt-9">
              <a href="#harga" class="jual-tombol jual-tombol-terang">Lihat daftar harga</a>
              <a href="#aspek" class="jual-tombol jual-tombol-garis">Apa saja yang ditangani</a>
            </div>
          </div>

          <!-- Potongan produk yang sesungguhnya, bukan gambar hiasan: ia
               memperlihatkan apa yang diterima pembeli sesudah menekan
               tombol. Halaman jual yang memperlihatkan barangnya lebih
               meyakinkan daripada yang memperlihatkan ikon tentang
               barangnya. -->
          <aside v-if="contohTagihan.baris.length" v-singkap="240" class="jual-mock">
            <div class="jual-mock-kepala">
              <span class="jual-mock-nomor">INV000318</span>
              <span class="jual-mock-lencana">Lunas</span>
            </div>

            <div class="pt-1.5">
              <div v-for="b in contohTagihan.baris" :key="b.nama" class="jual-mock-baris">
                <span class="truncate pr-2">{{ b.nama }}</span>
                <span>{{ rupiah(b.harga) }}</span>
              </div>
            </div>

            <div class="jual-mock-total">
              <span class="jual-tubuh-kecil">Total tagihan</span>
              <b>{{ rupiah(contohTagihan.total) }}</b>
            </div>

            <div class="jual-mock-bayar">
              <span class="jual-mock-qr" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4z" />
                  <path d="M14 14h2.5v2.5H14zM19.5 14H20v2.5M17 19.5h3M14 19.5h.5" />
                </svg>
              </span>
              <span class="min-w-0">
                <span class="block text-[12.5px] font-bold">Bayar dengan QRIS</span>
                <span class="block jual-tubuh-kecil">Nominal sudah tercantum di dalam kode</span>
              </span>
            </div>
          </aside>
        </div>
      </div>
    </section>

    <!-- ══════════ paket ══════════ -->
    <section id="paket" class="jual-bagian">
      <div class="jual-lebar">
        <div class="jual-kepala-bagian">
          <p v-singkap class="jual-mata jual-mata-aksen">Paket</p>
          <h2 v-singkap="60" class="jual-h2">Seluruhnya, dalam satu tagihan</h2>
          <p v-singkap="120" class="jual-tubuh mt-5 max-w-xl">
            Membeli seluruh aplikasi terpisah selalu lebih mahal daripada paketnya, dan yang
            terpisah tidak berbagi data begitu saja.
          </p>
        </div>

        <div v-if="paket" class="jual-paket">
          <div v-singkap class="jual-paket-isi">
            <h3 class="jual-h3">{{ paket.nama }}</h3>
            <p v-if="paket.ket" class="jual-tubuh mt-3 max-w-lg">{{ paket.ket }}</p>

            <ul class="jual-paket-daftar">
              <li v-for="a in aplikasi" :key="a.nama">
                <span class="jual-centang">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5"
                       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m5 12.5 4.5 4.5L19 7" />
                  </svg>
                </span>
                {{ a.nama }}
              </li>
            </ul>
          </div>

          <div v-singkap="80" class="jual-paket-harga">
            <p class="jual-mata jual-mata-terang">Harga</p>
            <p class="jual-angka mt-3">
              <span class="jual-angka-kecil">Rp</span>{{ paket.harga.toLocaleString('id-ID') }}
            </p>
            <p class="jual-tubuh-kecil jual-tubuh-terang mt-2">{{ paket.masa }}</p>

            <div class="flex items-center gap-3 mt-7">
              <span class="jual-hitung">
                <button type="button" aria-label="Kurangi" @click="ubah(paket.id, -1)">−</button>
                <span class="num">{{ pilih[paket.id] ?? 0 }}</span>
                <button type="button" aria-label="Tambah" @click="ubah(paket.id, 1)">+</button>
              </span>
            </div>

            <button type="button" class="jual-tombol jual-tombol-terang w-full jual-paket-tombol"
                    @click="ubah(paket.id, 1); ke('pesan')">
              Ambil paket ini
            </button>
          </div>
        </div>

        <!-- Paketnya belum berharga. Disebutkan, bukan dibiarkan sebagai
             lubang di tengah halaman. -->
        <div v-else v-singkap class="jual-kosong">
          <h3 class="jual-h3">Paket disusun sesuai kebutuhan</h3>
          <p class="jual-tubuh mt-3 max-w-lg">
            Jumlah pengguna, aplikasi yang dipakai, dan lama berlangganan menentukan harganya.
            Sebutkan kebutuhan Anda — penawarannya kami susun.
          </p>
          <a v-if="tanya" :href="tanya" :target="waUrl ? '_blank' : undefined" rel="noopener"
             class="jual-tombol mt-7">Minta penawaran</a>
          <a v-else href="#harga" class="jual-tombol mt-7">Lihat aplikasinya</a>
        </div>
      </div>
    </section>

    <!-- ══════════ cakupan ══════════ -->
    <section id="aspek" class="jual-bagian jual-bagian-gelap">
      <div class="jual-lebar">
        <div class="jual-kepala-bagian">
          <p v-singkap class="jual-mata jual-mata-terang">Cakupan</p>
          <h2 v-singkap="60" class="jual-h2 jual-h2-terang">Aspek yang ditangani</h2>
          <p v-singkap="120" class="jual-tubuh jual-tubuh-terang mt-5 max-w-xl">
            Klik satu aspek untuk melompat ke aplikasi yang menopangnya.
          </p>
        </div>

        <div class="jual-aspek-kisi">
          <button v-for="([slug, w], i) in pilarList" :key="slug" v-singkap="i * 40" type="button"
                  class="jual-aspek" @click="ke('grup-' + slug)">
            <span class="jual-aspek-bingkai">
              <img v-if="w.foto" :src="w.foto" alt="" loading="lazy" decoding="async"
                   class="jual-aspek-foto">
              <span v-else class="jual-aspek-foto jual-aspek-polos" :style="{ '--w': w.deep }"></span>
              <span class="jual-aspek-tirai"></span>
            </span>

            <span class="jual-aspek-baris">
              <span class="jual-aspek-nama">{{ w.nama }}</span>
              <span class="jual-aspek-jumlah">{{ String(w.jumlah).padStart(2, '0') }}</span>
            </span>
          </button>
        </div>
      </div>
    </section>

    <!-- ══════════ daftar harga ══════════ -->
    <section id="harga" class="jual-bagian">
      <div class="jual-lebar">
        <div class="jual-kepala-bagian">
          <p v-singkap class="jual-mata jual-mata-aksen">Harga</p>
          <h2 v-singkap="60" class="jual-h2">Aplikasi satuan</h2>
          <p v-singkap="120" class="jual-tubuh mt-5 max-w-xl">
            Tiap aplikasi berdiri sendiri dan tetap terhubung dengan yang lain begitu ditambahkan.
          </p>
        </div>

        <div v-for="grup in kelompok" :id="'grup-' + grup.slug" :key="grup.slug" class="jual-grup">
          <header v-singkap class="jual-grup-kepala">
            <span class="jual-grup-titik" :style="{ background: grup.pilar.warna }"></span>
            <h3 class="jual-grup-nama">{{ grup.pilar.nama }}</h3>
            <span class="jual-grup-jumlah">{{ grup.butir.length }} aplikasi</span>
          </header>

          <ul>
            <li v-for="a in grup.butir" :key="a.nama" v-singkap class="jual-baris">
              <span class="jual-baris-teks">
                <span class="jual-baris-nama">{{ a.nama }}</span>
                <span class="jual-baris-ket">{{ a.ket }}</span>
              </span>

              <span v-if="a.id !== null" class="jual-baris-aksi">
                <span class="jual-baris-harga">
                  <span class="num">{{ rupiah(a.harga) }}</span>
                  <span class="jual-baris-masa">{{ a.masa }}</span>
                </span>
                <span class="jual-hitung">
                  <button type="button" aria-label="Kurangi" @click="ubah(a.id, -1)">−</button>
                  <span class="num">{{ pilih[a.id] ?? 0 }}</span>
                  <button type="button" aria-label="Tambah" @click="ubah(a.id, 1)">+</button>
                </span>
              </span>

              <span v-else class="jual-baris-aksi">
                <a v-if="tanya" :href="tanya" :target="waUrl ? '_blank' : undefined" rel="noopener"
                   class="jual-tautan">Minta penawaran</a>
                <span v-else class="jual-baris-masa">Belum ditawarkan</span>
              </span>
            </li>
          </ul>
        </div>

        <p v-if="terjual.length > 1" v-singkap class="mt-10">
          <button type="button" class="jual-tombol jual-tombol-lain" @click="ambilSemua">
            Pilih seluruh {{ terjual.length }} aplikasi
          </button>
        </p>
      </div>
    </section>

    <!-- ══════════ cara beli ══════════ -->
    <section id="cara" class="jual-bagian jual-bagian-putih">
      <div class="jual-lebar">
        <div class="jual-kepala-bagian">
          <p v-singkap class="jual-mata jual-mata-aksen">Cara beli</p>
          <h2 v-singkap="60" class="jual-h2">Empat langkah, tanpa akun</h2>
        </div>

        <div class="jual-langkah">
          <div v-for="(l, i) in langkah" :key="l[0]" v-singkap="i * 40" class="jual-kartu">
            <span class="jual-langkah-angka">{{ String(i + 1).padStart(2, '0') }}</span>
            <h3 class="jual-h4 mt-4">{{ l[0] }}</h3>
            <p class="jual-tubuh-kecil mt-2">{{ l[1] }}</p>
          </div>
        </div>

        <p v-singkap class="jual-mata mt-14 mb-5">Yang termasuk</p>

        <div class="jual-jaminan">
          <div v-for="(j, i) in jaminan" :key="j[0]" v-singkap="i * 40" class="jual-kartu">
            <h3 class="jual-h4">{{ j[0] }}</h3>
            <p class="jual-tubuh-kecil mt-2">{{ j[1] }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ pemesanan ══════════ -->
    <section id="pesan" class="jual-bagian">
      <div class="jual-lebar">
        <div class="jual-kepala-bagian">
          <p v-singkap class="jual-mata jual-mata-aksen">Pesan</p>
          <h2 v-singkap="60" class="jual-h2">Buat tagihan</h2>
        </div>

        <div class="grid lg:grid-cols-[minmax(0,1fr)_23rem] gap-x-10 gap-y-8 items-start">
          <div v-singkap>
            <ul v-if="terpilih.length" class="jual-pesanan">
              <li v-for="p in terpilih" :key="p.id">
                <span class="jual-pesanan-teks">
                  <span class="jual-baris-nama">{{ p.nama }}</span>
                  <span class="jual-baris-masa">{{ rupiah(p.harga) }} per butir</span>
                </span>
                <span class="jual-pesanan-aksi">
                  <span class="jual-hitung">
                    <button type="button" aria-label="Kurangi" @click="ubah(p.id, -1)">−</button>
                    <span class="num">{{ pilih[p.id] }}</span>
                    <button type="button" aria-label="Tambah" @click="ubah(p.id, 1)">+</button>
                  </span>
                  <span class="jual-pesanan-jumlah">{{ rupiah(p.harga * pilih[p.id]) }}</span>
                  <button type="button" class="jual-buang" aria-label="Buang" @click="buang(p.id)">×</button>
                </span>
              </li>
            </ul>

            <p v-else class="jual-pesanan-kosong">
              Belum ada yang dipilih. Ambil paketnya, atau tambahkan aplikasi satuan di atas.
            </p>

            <div class="jual-total">
              <span>Total</span>
              <span>{{ rupiah(total) }}</span>
            </div>
          </div>

          <form v-singkap="80" class="jual-form" @submit.prevent="kirim">
            <p class="jual-mata">Data pembeli</p>
            <p class="jual-tubuh-kecil mt-2 mb-5">Tidak perlu membuat akun lebih dulu.</p>

            <label class="jual-isian">
              <span>Nama</span>
              <input v-model="f.pembeli_nama" required placeholder="Nama pembeli" />
            </label>
            <p v-if="f.errors.pembeli_nama" class="jual-galat">{{ f.errors.pembeli_nama }}</p>

            <label class="jual-isian">
              <span>Perusahaan</span>
              <input v-model="f.pembeli_perusahaan" placeholder="Opsional" />
            </label>

            <label class="jual-isian">
              <span>Email</span>
              <input v-model="f.pembeli_email" type="email" placeholder="Opsional" />
            </label>
            <p v-if="f.errors.pembeli_email" class="jual-galat">{{ f.errors.pembeli_email }}</p>

            <label class="jual-isian">
              <span>Telepon</span>
              <input v-model="f.pembeli_telepon" placeholder="Opsional" />
            </label>

            <label class="jual-isian">
              <span>Catatan</span>
              <textarea v-model="f.catatan" rows="2" placeholder="Opsional"></textarea>
            </label>

            <p v-if="f.errors.produk" class="jual-galat">{{ f.errors.produk }}</p>

            <button type="submit" class="jual-tombol w-full mt-6"
                    :disabled="!terpilih.length || f.processing">
              Buat tagihan
            </button>

            <p class="jual-tubuh-kecil mt-4">
              Pembayarannya lewat QRIS pada halaman yang muncul sesudahnya, dan lisensinya
              terbit setelah bukti bayar diperiksa.
            </p>
          </form>
        </div>
      </div>
    </section>

    <footer class="jual-kaki">
      <div class="jual-lebar flex flex-wrap items-center justify-between gap-4">
        <Wordmark :tinggi="20" />
        <p class="jual-tubuh-kecil">Platform Terpadu Keselamatan Pertambangan · {{ tahun }}</p>
      </div>
    </footer>
  </div>

  <!-- ══════════ bilah keranjang ══════════ -->
  <transition name="jual-bilah">
    <div v-if="terpilih.length && !formTerlihat" class="jual-bilah-bungkus">
      <div class="jual-bilah">
        <span>
          <span class="jual-bilah-angka">{{ rupiah(total) }}</span>
          <span class="jual-bilah-ket">{{ jumlahButir }} butir · {{ terpilih.length }} jenis</span>
        </span>
        <button type="button" class="jual-tombol jual-tombol-terang jual-tombol-kecil ml-auto shrink-0"
                @click="ke('pesan')">
          Lanjut memesan
        </button>
      </div>
    </div>
  </transition>
</template>
