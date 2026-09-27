<script setup lang="ts">
/**
 * Halaman depan eqohsee.id.
 *
 * Tata letaknya mengikuti desain "EQOHSEE Website": kepala gelap, judul
 * Archivo yang dipersempit, label IBM Plex Mono bernomor per bagian,
 * garis rambut alih-alih kartu bertumpuk. ISINYA tidak disalin dari
 * desain itu — setiap angka, nama modul, harga, dan kalimat aspek dibaca
 * dari server (Modules, Pillars, Smkp, produk pembelian), sehingga
 * halaman ini tidak pernah menyebut "19 modul" ketika yang aktif dua
 * puluh tiga, atau harga yang berbeda dari tagihan yang akhirnya terbit.
 *
 * Tiga klaim desain sengaja TIDAK dipakai karena tidak benar untuk
 * produk ini: bekerja tanpa sinyal (aplikasi Android-nya pembungkus
 * web, tanpa perekaman luring — lihat jawaban tanya-jawab tentang
 * sinyal), harga satuan yang seragam, dan semboyan berbahasa Inggris
 * sebagai keterangan aspek.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import BlankLayout from '../Layouts/BlankLayout.vue';
import { bolehLatarVideo } from '../latarVideo';
import '../../css/landing.css';

defineOptions({ layout: BlankLayout });

type Pilar = {
  nama: string; ket: string; warna: string; ringkas: string;
  cakupan: [string, string][]; modul: string[];
};
type Modul = {
  nama: string; status: string; ket: string; ikon: string; pilar: string;
  pilarNama: string; pilarWarna: string; url: string | null;
};
type Produk = { id: number; nama: string; ket?: string | null; harga: number; masa: string };

const props = defineProps<{
  hero: { video: string | null; poster: string | null };
  galeri: { judul: string; ket: string; aspek: string; gambarUrl: string | null; videoUrl: string | null }[];
  klien: { nama: string; url: string }[];
  standar: { kode: string; ket: string }[];
  elemenSmkp: { kode: string; nama: string; bobot: number; modul: string }[];
  smkpAngka: { poin: number; butir: number };
  aman: { judul: string; ket: string }[];
  tanya: { t: string; j: string }[];
  kontak: { whatsapp: string; email: string };
  jumlahItem: number;
  modul: Modul[];
  pilar: Record<string, Pilar>;
  alur: { judul: string; ket: string }[];
  katalog: { paket: Produk | null; layanan: Produk | null; aplikasi: Produk[] } | null;
  layarPonsel: { url: string; alt: string }[];
  tautan: { masuk: string; katalog: string; pesan: string; privasi: string };
  tahun: number;
}>();

const rp = (n: number) => 'Rp ' + n.toLocaleString('id-ID');

/* Bilangan kecil di judul ditulis dengan kata: "Tujuh huruf, delapan
   aspek" — bukan "Tujuh huruf, 8 aspek". Tetap dihitung dari datanya. */
const KATA = ['nol', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas', 'dua belas'];
const kata = (n: number) => KATA[n] ?? String(n);
const Kata = (n: number) => { const k = kata(n); return k.charAt(0).toUpperCase() + k.slice(1); };

/* ═══════════ kepala & navigasi ═══════════ */

const NAV: [string, string][] = [
  ['kerangka', 'Kerangka'], ['modul', 'Modul'], ['smkp', 'Audit SMKP'],
  ['aplikasi', 'Aplikasi lapangan'], ['alur', 'Cara kerja'], ['harga', 'Harga'],
  ['tanya', 'Tanya jawab'], ['kontak', 'Kontak'],
];

const menuBuka = ref(false);
const bagianKini = ref('');

/* ═══════════ kerangka EQOHSEE ═══════════ */

const pilarDaftar = computed(() => Object.entries(props.pilar));
const SINGKAT: Record<string, string> = { occhealth: 'Occ. Health', konservasi: 'Konservasi' };
const huruf = computed(() => pilarDaftar.value.map(([slug, p]) => ({
  slug,
  ch: slug === 'konservasi' ? '+K' : p.nama.charAt(0).toUpperCase(),
  singkat: SINGKAT[slug] ?? p.nama,
})));
const pilarKini = ref<string>(props.pilar.safety ? 'safety' : (Object.keys(props.pilar)[0] ?? ''));
const pilarAktif = computed(() => props.pilar[pilarKini.value] ?? null);

/* ═══════════ modul ═══════════ */

const saring = ref<string>('semua');
const modulAktif = computed(() => props.modul.filter((m) => m.status === 'aktif').length);
const chip = computed(() => {
  const ada = new Map<string, { nama: string; n: number }>();
  for (const m of props.modul) {
    const x = ada.get(m.pilar) ?? { nama: SINGKAT[m.pilar] ?? m.pilarNama, n: 0 };
    x.n++;
    ada.set(m.pilar, x);
  }
  /* Urut menurut kerangka EQOHSEE, bukan menurut urutan kemunculan. */
  const urut = Object.keys(props.pilar);
  return [
    { kunci: 'semua', nama: 'Semua', n: props.modul.length },
    ...[...ada.entries()]
      .sort((a, b) => urut.indexOf(a[0]) - urut.indexOf(b[0]))
      .map(([kunci, x]) => ({ kunci, ...x })),
  ];
});
const modulTampil = computed(() => (saring.value === 'semua'
  ? props.modul : props.modul.filter((m) => m.pilar === saring.value)));

/**
 * Dari panel aspek: turun ke modul yang disebut, lalu menyorotnya.
 *
 * Bukan menyaring menurut aspek — modul yang menopang sebuah aspek belum
 * tentu beraspek utama sama (Keselamatan Operasi menopang Energy tetapi
 * berpilar Engineering), dan saringan aspek akan menyembunyikan tepat
 * modul yang baru saja ditekan.
 */
const sorot = ref<string | null>(null);
async function lihatModul(nama: string) {
  saring.value = 'semua';
  await nextTick();
  const el = [...document.querySelectorAll<HTMLElement>('[data-modul]')].find((x) => x.dataset.modul === nama);
  (el ?? document.getElementById('modul'))?.scrollIntoView({ behavior: 'smooth', block: el ? 'center' : 'start' });
  if (el) { sorot.value = nama; window.setTimeout(() => { if (sorot.value === nama) sorot.value = null; }, 2200); }
}

/* ═══════════ SMKP ═══════════ */

const warnaBobot = (b: number) => (b >= 35 ? 'var(--ld-aksen)' : b >= 15 ? 'var(--ld-hitam)' : '#3A4450');
const teksBobot = (b: number) => (b >= 35 ? 'var(--ld-hitam)' : '#FFFFFF');

/* ═══════════ hero ═══════════ */

const pakaiVideo = ref(bolehLatarVideo());

/* Foto hero: rekaman inspeksi lapangan dari galeri, cadangannya poster
   hero. Bukan berkas tersendiri — halaman ini tidak memajang tambang
   yang berbeda dari galerinya sendiri. */
const heroMedia = computed(() => {
  const g = props.galeri.find((x) => x.aspek === 'safety' && (x.gambarUrl || x.videoUrl));
  return {
    gambar: g?.gambarUrl ?? props.hero.poster,
    video: g?.videoUrl ?? null,
  };
});

const angka = computed(() => [
  { nilai: String(props.jumlahItem), satuan: '', ket: 'item penilaian Safety Maturity Level' },
  { nilai: String(props.elemenSmkp.length), satuan: 'elemen', ket: `audit SMKP Minerba, ${props.smkpAngka.poin} poin maksimum` },
  { nilai: String(modulAktif.value), satuan: 'modul', ket: 'aktif, dengan satu akun dan satu data' },
  { nilai: String(pilarDaftar.value.length), satuan: 'aspek', ket: 'tujuh huruf EQOHSEE ditambah Konservasi Minerba' },
]);

/* ═══════════ galeri & pemutar ═══════════ */

type VideoAktif = { judul: string; src: string; poster: string | null };
const videoAktif = ref<VideoAktif | null>(null);

function putar(judul: string, src: string | null, poster: string | null = null) {
  if (!src) return;
  videoAktif.value = { judul, src, poster };
}

/* ═══════════ keranjang ═══════════ */

const jumlah = reactive<Record<number, number>>({});
const MAKS = 99;
function ubah(id: number, d: number) {
  jumlah[id] = Math.max(0, Math.min(MAKS, (jumlah[id] ?? 0) + d));
}

const produkSemua = computed<Produk[]>(() => {
  const k = props.katalog;
  if (!k) return [];
  return [...(k.paket ? [k.paket] : []), ...(k.layanan ? [k.layanan] : []), ...k.aplikasi];
});
const baris = computed(() => produkSemua.value
  .filter((p) => (jumlah[p.id] ?? 0) > 0)
  .map((p) => ({ ...p, n: jumlah[p.id], sub: p.harga * jumlah[p.id] })));
const total = computed(() => baris.value.reduce((t, b) => t + b.sub, 0));
const butir = computed(() => baris.value.reduce((t, b) => t + b.n, 0));
const paketDipilih = computed(() => !!props.katalog?.paket && (jumlah[props.katalog.paket.id] ?? 0) > 0);
const satuanDipilih = computed(() => (props.katalog?.aplikasi ?? []).some((a) => (jumlah[a.id] ?? 0) > 0));

const form = useForm({
  pembeli_nama: '',
  pembeli_perusahaan: '',
  pembeli_email: '',
  pembeli_telepon: '',
});

/* Diperiksa di peramban lebih dulu, dengan kalimat berbahasa Indonesia.
   Server tetap memeriksa ulang — ini hanya supaya kesalahan yang paling
   sering tidak perlu menunggu satu perjalanan ke server. */
function periksa(): boolean {
  form.clearErrors();
  if (!form.pembeli_nama.trim()) form.setError('pembeli_nama', 'Nama pembeli wajib diisi.');
  if (form.pembeli_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.pembeli_email)) {
    form.setError('pembeli_email', 'Alamat email belum benar.');
  }
  return !form.hasErrors;
}

function buatTagihan() {
  if (!baris.value.length || !periksa()) return;
  /* Hanya ID dan banyaknya yang dikirim; harganya dihitung ulang server. */
  const produk = Object.fromEntries(baris.value.map((b) => [b.id, b.n]));
  form.transform((d) => ({ ...d, produk })).post(props.tautan.pesan, { preserveScroll: true });
}

/* ═══════════ tanya jawab ═══════════ */

const tanyaBuka = ref<number | null>(0);
const bukaTanya = (i: number) => { tanyaBuka.value = tanyaBuka.value === i ? null : i; };

/* ═══════════ kontak ═══════════ */

/* Nomor dan surel dibaca dari config('pembelian.kontak'), sama dengan
   /katalog. Tombol yang tujuannya belum diatur tidak digambar sama
   sekali — tautan wa.me kosong membuka pemilih kontak dan terbaca
   sebagai aplikasi rusak. */
const waUrl = computed(() => (props.kontak.whatsapp
  ? 'https://wa.me/' + props.kontak.whatsapp + '?text='
    + encodeURIComponent('Halo EQOHSEE, saya ingin menanyakan penawaran untuk perusahaan kami.')
  : null));
const waTampil = computed(() => {
  const n = props.kontak.whatsapp;
  if (!n) return '';
  const m = n.match(/^62(\d{3})(\d{4})(\d+)$/);
  return m ? `+62 ${m[1]}-${m[2]}-${m[3]}` : '+' + n;
});
const emailUrl = computed(() => (props.kontak.email
  ? 'mailto:' + props.kontak.email + '?subject=' + encodeURIComponent('Penawaran EQOHSEE') : null));

/* ═══════════ efek halaman ═══════════ */

function tekan(e: KeyboardEvent) {
  if (e.key !== 'Escape') return;
  videoAktif.value = null;
  menuBuka.value = false;
}

let pengamatMuncul: IntersectionObserver | null = null;
let pengamatBagian: IntersectionObserver | null = null;

onMounted(() => {
  window.addEventListener('keydown', tekan);

  /* Muncul saat digulir. Tanpa IntersectionObserver semuanya langsung
     tampak — isi tidak boleh bergantung pada efek. */
  const muncul = document.querySelectorAll<HTMLElement>('.ld-muncul');
  if ('IntersectionObserver' in window) {
    pengamatMuncul = new IntersectionObserver((cat) => {
      for (const c of cat) if (c.isIntersecting) { c.target.classList.add('tampak'); pengamatMuncul?.unobserve(c.target); }
    }, { rootMargin: '0px 0px -8% 0px' });
    muncul.forEach((el) => pengamatMuncul!.observe(el));

    /* Tautan navigasi bagian yang sedang dibaca. */
    pengamatBagian = new IntersectionObserver((cat) => {
      for (const c of cat) if (c.isIntersecting) bagianKini.value = c.target.id === 'beranda' ? '' : c.target.id;
    }, { rootMargin: '-45% 0px -50% 0px' });
    /* Hero ikut diamati supaya tanda "sedang dibaca" hilang lagi saat
       kembali ke atas — hero sendiri tidak punya tautan navigasi. */
    ['beranda', ...NAV.map(([id]) => id)].forEach((id) => {
      const el = document.getElementById(id); if (el) pengamatBagian!.observe(el);
    });
  } else {
    muncul.forEach((el) => el.classList.add('tampak'));
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', tekan);
  pengamatMuncul?.disconnect();
  pengamatBagian?.disconnect();
});

const PANAH = 'M5 12h14m-5-5 5 5-5 5';
const WA = 'M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.2-.6L3.5 21l1.7-4.6A8.2 8.2 0 0 1 4 11.5a8.4 8.4 0 0 1 9-8.4 8.4 8.4 0 0 1 8 8.4Z';
</script>

<template>
  <Head title="Platform Terpadu Keselamatan Pertambangan" />

  <div class="ld">
    <!-- ══════════ KEPALA ══════════ -->
    <header class="ld-kepala">
      <div class="ld-lebar ld-kepala-isi">
        <a href="#beranda" class="ld-merek" aria-label="EQOHSEE — ke atas">
          <img src="/brand/eqohsee-mark-128.png" alt="" width="30" height="30">
          <b>E<i>Q</i>OHSEE</b>
        </a>

        <nav class="ld-nav" aria-label="Bagian halaman">
          <a v-for="[id, nama] in NAV" :key="id" :href="`#${id}`"
             :class="{ kini: bagianKini === id }" :aria-current="bagianKini === id ? 'true' : undefined">{{ nama }}</a>
        </nav>

        <div class="ld-kepala-aksi">
          <Link :href="tautan.masuk" class="ld-tombol ld-tombol-aksen ld-tombol-kecil">Masuk ke Platform</Link>
          <button type="button" class="ld-menu-tombol" :aria-expanded="menuBuka" aria-controls="ld-menu"
                  :aria-label="menuBuka ? 'Tutup menu' : 'Buka menu'" @click="menuBuka = !menuBuka">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
              <path v-if="menuBuka" d="M6 6l12 12M18 6 6 18" />
              <path v-else d="M4 7h16M4 12h16M4 17h16" />
            </svg>
          </button>
        </div>
      </div>

      <Transition name="ld-menu">
        <nav v-if="menuBuka" id="ld-menu" class="ld-menu" aria-label="Menu">
          <a v-for="[id, nama] in NAV" :key="id" :href="`#${id}`" @click="menuBuka = false">
            {{ nama }}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="PANAH" /></svg>
          </a>
          <Link :href="tautan.masuk" class="ld-tombol ld-tombol-aksen">Masuk ke Platform</Link>
        </nav>
      </Transition>
    </header>

    <main>
      <!-- ══════════ HERO ══════════ -->
      <section id="beranda" class="ld-gelap">
        <div class="ld-lebar">
          <div class="ld-hero-grid">
            <div>
              <p class="ld-hero-mata">PLATFORM KESELAMATAN PERTAMBANGAN TERPADU</p>
              <h1 class="ld-h1">Keselamatan tambang, terukur dan terbukti.</h1>
              <p class="ld-hero-teks">
                Pembelajaran, penilaian kinerja, inspeksi, izin kerja, kinerja energi, hingga
                sertifikasi dalam satu basis data — mengikuti regulasi keselamatan pertambangan
                Indonesia.
              </p>
              <div class="ld-hero-aksi">
                <Link :href="tautan.masuk" class="ld-tombol ld-tombol-aksen">
                  Masuk ke Platform
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="PANAH" /></svg>
                </Link>
                <a href="#modul" class="ld-tombol ld-tombol-garis">Lihat {{ modul.length }} modul</a>
              </div>
            </div>

            <!-- Kartu di atas foto adalah CONTOH tampilan, memakai bidang
                 yang memang dicatat platform: kode HR-tahun-urut, tingkat
                 risiko Rendah/Sedang/Tinggi, hierarki kendali, tahap
                 Open → In Progress → Closed, dan batas akhirnya. -->
            <div class="ld-hero-media">
              <div class="ld-hero-bingkai">
                <video v-if="heroMedia.video && pakaiVideo" :src="heroMedia.video"
                       :poster="heroMedia.gambar ?? undefined" autoplay muted loop playsinline
                       preload="metadata" aria-hidden="true"></video>
                <img v-else-if="heroMedia.gambar" :src="heroMedia.gambar"
                     alt="Pengawas memeriksa unit di area tambang" fetchpriority="high">
                <span class="ld-chip-status" aria-hidden="true"><i></i>SUMP PIT 3 · 82% · sanggup 18 mm</span>
              </div>
              <div class="ld-kartu-temuan" aria-hidden="true">
                <div class="ld-kartu-temuan-atas">
                  <span class="ld-mono">HR-{{ tahun }}-0142</span>
                  <span class="ld-lencana-risiko">Risiko Tinggi</span>
                </div>
                <div class="ld-kartu-temuan-judul">Tanggul pengaman tergerus di tikungan KM 2,3</div>
                <div class="ld-tahap"><span class="lewat"></span><span class="kini"></span><span></span></div>
                <div class="ld-kartu-temuan-kaki"><span>In Progress · Rekayasa</span><b>batas akhir hari ini</b></div>
              </div>
            </div>
          </div>

          <div class="ld-angka-deret">
            <div v-for="a in angka" :key="a.ket">
              <div class="ld-angka ld-num">{{ a.nilai }}<small v-if="a.satuan">{{ a.satuan }}</small></div>
              <div class="ld-angka-ket">{{ a.ket }}</div>
            </div>
          </div>
        </div>
      </section>

      <!-- ══════════ ACUAN ══════════
           Logo perusahaan hanya bila sudah ditaruh di media/klien. Tanpa
           itu yang dipajang acuan yang dapat diperiksa kebenarannya. -->
      <section class="ld-acuan" aria-label="Acuan">
        <div class="ld-lebar ld-acuan-isi">
          <template v-if="klien.length">
            <span>Dipakai oleh</span>
            <img v-for="c in klien" :key="c.url" :src="c.url" :alt="c.nama" class="ld-acuan-klien" loading="lazy">
          </template>
          <template v-else>
            <span>Setiap penilaian bersandar pada</span>
            <span v-for="x in standar" :key="x.kode" class="ld-acuan-kode" :title="x.ket">{{ x.kode }}</span>
          </template>
        </div>
      </section>

      <!-- ══════════ 01 KERANGKA ══════════ -->
      <section id="kerangka" class="ld-abu">
        <div class="ld-lebar ld-blok">
          <div class="ld-kepala-bagian ld-muncul">
            <div>
              <p class="ld-mata">01 — Kerangka</p>
              <h2 class="ld-h2">Tujuh huruf, {{ kata(pilarDaftar.length) }} aspek, satu sistem.</h2>
            </div>
            <p class="ld-lead">
              Setiap huruf EQOHSEE mewakili satu aspek kerja; Konservasi Minerba berdiri di luar
              akronim sebagai kewajiban tersendiri. Pilih salah satu untuk melihat cakupannya.
            </p>
          </div>

          <div class="ld-huruf-deret" role="group" aria-label="Aspek EQOHSEE">
            <button v-for="h in huruf" :key="h.slug" type="button" class="ld-huruf"
                    :class="{ pilih: pilarKini === h.slug, kecil: h.ch.length > 1 }"
                    :aria-pressed="pilarKini === h.slug" :aria-label="pilar[h.slug].nama"
                    @click="pilarKini = h.slug">
              <b aria-hidden="true">{{ h.ch }}</b>
              <span aria-hidden="true">{{ h.singkat }}</span>
            </button>
          </div>

          <Transition name="ld-pilar" mode="out-in">
            <div v-if="pilarAktif" :key="pilarKini" class="ld-pilar-isi" aria-live="polite">
              <div>
                <div class="ld-pilar-tanda"><i :style="{ background: pilarAktif.warna }"></i><span>{{ pilarAktif.ket }}</span></div>
                <h3 class="ld-h3">{{ pilarAktif.nama }}</h3>
                <p class="ld-teks">{{ pilarAktif.ringkas }}</p>
              </div>
              <div>
                <p class="ld-label-kolom">Cakupan kerja</p>
                <div class="ld-daftar-garis" style="margin-top:10px">
                  <div v-for="c in pilarAktif.cakupan" :key="c[0]"><b>{{ c[0] }}</b><small>{{ c[1] }}</small></div>
                </div>
              </div>
              <div>
                <p class="ld-label-kolom">Ditopang modul</p>
                <div style="margin-top:10px">
                  <button v-for="m in pilarAktif.modul" :key="m" type="button" class="ld-tautan-modul"
                          @click="lihatModul(m)">
                    {{ m }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="PANAH" /></svg>
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </section>

      <!-- ══════════ 02 MODUL ══════════ -->
      <section id="modul" class="ld-putih ld-garis-atas">
        <div class="ld-lebar ld-blok">
          <div class="ld-kepala-bagian ld-muncul">
            <div>
              <p class="ld-mata">02 — Modul</p>
              <h2 class="ld-h2">{{ modul.length }} modul, satu akun.</h2>
            </div>
            <p class="ld-lead">
              Semua modul berbagi data perusahaan, pengguna, dan peran yang sama. Temuan inspeksi,
              izin kerja, dan pelatihan saling terhubung — tidak ada login berulang, tidak ada rekap
              ganda.
            </p>
          </div>

          <div class="ld-saring" role="group" aria-label="Saring modul menurut aspek">
            <button v-for="c in chip" :key="c.kunci" type="button" :class="{ pilih: saring === c.kunci }"
                    :aria-pressed="saring === c.kunci" @click="saring = c.kunci">
              {{ c.nama }}<span>{{ c.n }}</span>
            </button>
          </div>

          <div class="ld-modul-kisi">
            <article v-for="m in modulTampil" :key="m.nama" class="ld-modul" :data-modul="m.nama"
                     :class="{ sorot: sorot === m.nama }">
              <span class="ld-modul-ikon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path :d="m.ikon" /></svg>
              </span>
              <div style="flex:1;min-width:0">
                <div class="ld-modul-atas">
                  <h3 style="margin:0"><b>{{ m.nama }}</b><span v-if="m.status !== 'aktif'" class="ld-segera">SEGERA</span></h3>
                  <span class="ld-modul-pilar"><i :style="{ background: m.pilarWarna }"></i>{{ m.pilarNama }}</span>
                </div>
                <p>{{ m.ket }}</p>
              </div>
            </article>
          </div>
        </div>
      </section>

      <!-- ══════════ 03 AUDIT SMKP ══════════
           Nama dan bobot elemen dibaca dari elemen.json yang sama dengan
           modul auditnya. Penyangkalan di kakinya WAJIB ada: perangkat
           lunak tidak dapat menjamin kelulusan audit. -->
      <section id="smkp" class="ld-abu ld-garis-atas">
        <div class="ld-lebar ld-blok">
          <div class="ld-kepala-bagian ld-muncul">
            <div>
              <p class="ld-mata">03 — Audit SMKP Minerba</p>
              <h2 class="ld-h2">Bobot resmi, dihitung di server, bisa ditelusuri.</h2>
            </div>
            <p class="ld-lead">
              {{ Kata(elemenSmkp.length) }} elemen wajib menurut Kepdirjen 185.K/37.04/DJB/2019 —
              {{ smkpAngka.butir }} butir bernilai {{ smkpAngka.poin }} poin. Tiap butir membawa
              rujukan halamannya, butir N/A dikeluarkan dari pembagi, dan kategori temuan diturunkan
              dari nilai — bukan dipilih auditor.
            </p>
          </div>

          <div class="ld-bobot" role="img"
               :aria-label="'Bobot elemen: ' + elemenSmkp.map((e) => `${e.kode} ${e.nama} ${e.bobot}%`).join(', ')">
            <div v-for="e in elemenSmkp" :key="e.kode" :title="`${e.nama} — ${e.bobot}%`"
                 :style="{ flex: `${e.bobot} 1 0`, background: warnaBobot(e.bobot), color: teksBobot(e.bobot) }">{{ e.kode }}</div>
          </div>

          <div class="ld-elemen-kisi">
            <div v-for="e in elemenSmkp" :key="e.kode" class="ld-elemen">
              <span class="ld-mono">{{ e.kode }}</span>
              <span class="ld-elemen-nama"><b>{{ e.nama }}</b><small v-if="e.modul">Bukti dari {{ e.modul }}</small></span>
              <span class="ld-mono">{{ e.bobot }}%</span>
            </div>
          </div>

          <div class="ld-kategori">
            <div style="border-top-color:#DC2626">
              <span class="ld-mono" style="color:#B91C1C">CAPAIAN &lt; 50%</span>
              <b>Ketidaksesuaian mayor</b>
              <p>Bernomor NC berurutan sekaligus kode per jenis — [kode perusahaan]-MYR-01 dan seterusnya.</p>
            </div>
            <div style="border-top-color:#EAB308">
              <span class="ld-mono" style="color:#854D0E">50% – &lt; 100%</span>
              <b>Ketidaksesuaian minor</b>
              <p>Urutan kode MIN tersendiri, lengkap dengan rujukan halaman kriteria.</p>
            </div>
            <div style="border-top-color:#22C55E">
              <span class="ld-mono" style="color:#15803D">CAPAIAN 100%</span>
              <b>Kesesuaian</b>
              <p>Butir yang belum dinilai dihitung nol, supaya skor tidak tampak tinggi palsu.</p>
            </div>
          </div>

          <p class="ld-sangkal">
            <span aria-hidden="true">!</span>
            <span>
              EQOHSEE adalah <b>alat bantu pemenuhan SMKP</b> — bukan pengganti kewajiban hukum, dan
              bukan jaminan kelulusan audit. Tanggung jawab penerapan serta pelaporannya tetap berada
              pada perusahaan dan Kepala Teknik Tambang.
            </span>
          </p>
        </div>
      </section>

      <!-- ══════════ 04 APLIKASI LAPANGAN ══════════
           Hanya yang benar: aplikasi Android membungkus platform web yang
           sama. Tidak ada janji bekerja tanpa sinyal. -->
      <section id="aplikasi" class="ld-gelap" style="overflow:hidden">
        <div class="ld-lebar ld-blok ld-app-grid">
          <div class="ld-muncul">
            <p class="ld-mata">04 — Aplikasi lapangan</p>
            <h2 class="ld-h2">Lapor dari muka tambang, bukan dari meja kantor.</h2>
            <p class="ld-lead" style="margin-top:22px">
              Aplikasi Android EQOHSEE membawa platform yang sama ke ponsel pengawas — akun, data,
              dan aturan yang sama dengan versi web, tanpa salinan kedua yang boleh berbeda.
            </p>
            <div style="margin-top:32px;border-top:1px solid rgba(255,255,255,.14)">
              <div class="ld-app-butir">
                <span class="ld-mono">A</span>
                <div><b>Foto temuan langsung dari kamera</b><small>Laporan bahaya dan inspeksi melampirkan foto dari kamera atau galeri ponsel, lengkap dengan batas akhir tindak lanjutnya.</small></div>
              </div>
              <div class="ld-app-butir">
                <span class="ld-mono">B</span>
                <div><b>Izin kerja dan uji gas di satu tempat</b><small>Daftar periksa, uji gas beserta kesegarannya, dan izin yang bentrok di satu lokasi tersaring sebelum terbit.</small></div>
              </div>
              <div class="ld-app-butir">
                <span class="ld-mono">C</span>
                <div><b>Jujur saat jaringan putus</b><small>Tanpa jaringan, aplikasi mengatakannya dan menyediakan tombol coba lagi — bukan layar kosong yang disangka data hilang.</small></div>
              </div>
            </div>
            <a href="#kontak" class="ld-panah-teks" style="margin-top:28px">
              Minta aplikasi untuk perusahaan Anda
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="PANAH" /></svg>
            </a>
          </div>
          <div v-if="layarPonsel.length" class="ld-ponsel-deret">
            <figure v-for="l in layarPonsel" :key="l.url" class="ld-ponsel" style="margin:0">
              <img :src="l.url" :alt="l.alt" loading="lazy" decoding="async" width="600" height="1298">
            </figure>
          </div>
        </div>
      </section>

      <!-- ══════════ 05 CARA KERJA ══════════ -->
      <section id="alur" class="ld-putih">
        <div class="ld-lebar ld-blok">
          <div class="ld-muncul">
            <p class="ld-mata">05 — Cara kerja</p>
            <h2 class="ld-h2" style="max-width:760px">Empat langkah, satu siklus yang berulang.</h2>
          </div>
          <div class="ld-langkah">
            <div v-for="(a, i) in alur" :key="a.judul" class="ld-muncul">
              <span class="ld-mono">{{ i === alur.length - 1 ? `${String(i + 1).padStart(2, '0')} → 01` : String(i + 1).padStart(2, '0') }}</span>
              <b>{{ a.judul }}</b>
              <p>{{ a.ket }}</p>
            </div>
          </div>
          <div class="ld-jaminan">
            <div v-for="a in aman" :key="a.judul"><b>{{ a.judul }}</b><p>{{ a.ket }}</p></div>
          </div>
          <p class="ld-kecil" style="margin-top:20px">
            Pemrosesan data pribadi mengikuti UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi —
            rinciannya pada <a :href="tautan.privasi">kebijakan privasi</a>.
          </p>
        </div>
      </section>

      <!-- ══════════ 06 DI LAPANGAN ══════════
           Tiap kartu memutar rekamannya sendiri: putar(g.judul, g.videoUrl, …). -->
      <section id="lapangan" class="ld-abu ld-garis-atas">
        <div class="ld-lebar ld-blok">
          <div class="ld-muncul" style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:16px 48px">
            <div>
              <p class="ld-mata">06 — Di lapangan</p>
              <h2 class="ld-h2">Dipakai di tempat kerja.</h2>
            </div>
            <p class="ld-lead" style="max-width:440px;font-size:16px">Dari inspeksi harian sampai pemantauan energi dan reklamasi.</p>
          </div>
          <div class="ld-galeri">
            <figure v-for="(g, i) in galeri" :key="g.judul" class="ld-muncul">
              <component :is="g.videoUrl ? 'button' : 'div'" class="ld-galeri-foto"
                         :type="g.videoUrl ? 'button' : undefined"
                         :aria-label="g.videoUrl ? `Putar video ${g.judul}` : undefined"
                         @click="putar(g.judul, g.videoUrl, g.gambarUrl)">
                <img v-if="g.gambarUrl" :src="g.gambarUrl" :alt="g.videoUrl ? '' : g.judul" loading="lazy" decoding="async">
                <span v-if="g.videoUrl" class="ld-putar" aria-hidden="true">
                  <i><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z" /></svg></i>Putar video
                </span>
              </component>
              <figcaption>
                <span class="ld-mono">{{ String(i + 1).padStart(2, '0') }}</span>
                <span><b>{{ g.judul }}</b><small>{{ g.ket }}</small></span>
              </figcaption>
            </figure>
          </div>
        </div>
      </section>

      <!-- ══════════ 07 HARGA & PEMBELIAN ══════════
           Keranjang yang sungguhan: dikirim ke katalog.pesan — alur yang
           sama dengan /katalog. Tidak ada yang terpilih sejak awal. -->
      <section id="harga" class="ld-putih ld-garis-atas">
        <div class="ld-lebar ld-blok">
          <div class="ld-kepala-bagian ld-muncul">
            <div>
              <p class="ld-mata">07 — Harga &amp; pembelian</p>
              <h2 class="ld-h2">Beli per aplikasi, atau semuanya sekaligus.</h2>
            </div>
            <p class="ld-lead">
              Pemesanan tidak menuntut akun. Tagihan terbit lebih dulu, pembayaran lewat QRIS atau
              transfer bank, dan lisensi terbit setelah bukti bayar diperiksa.
            </p>
          </div>

          <div v-if="katalog" class="ld-harga-grid">
            <div class="ld-harga-kiri">
              <div v-if="katalog.paket" class="ld-produk gelap">
                <div class="ld-produk-atas"><span>Paket menyeluruh</span><b>{{ modul.length }} aplikasi</b></div>
                <div class="ld-produk-nama">{{ katalog.paket.nama }}</div>
                <div class="ld-produk-ket">{{ katalog.paket.ket || 'Seluruh aplikasi di dalamnya, pembaruan, dan pendampingan pemasangan.' }}</div>
                <div class="ld-produk-bawah">
                  <div><div class="ld-harga">{{ rp(katalog.paket.harga) }}</div><div class="ld-harga-masa">{{ katalog.paket.masa }}</div></div>
                  <div class="ld-stepper">
                    <button type="button" :disabled="!jumlah[katalog.paket.id]" aria-label="Kurangi paket" @click="ubah(katalog.paket.id, -1)">−</button>
                    <span aria-live="polite">{{ jumlah[katalog.paket.id] ?? 0 }}</span>
                    <button type="button" aria-label="Tambah paket" @click="ubah(katalog.paket.id, 1)">+</button>
                  </div>
                </div>
              </div>

              <div v-if="katalog.layanan" class="ld-produk">
                <div class="ld-produk-atas"><span>Layanan tahunan</span></div>
                <div class="ld-produk-nama" style="font-size:20px;color:var(--ld-hitam)">{{ katalog.layanan.nama }}</div>
                <div v-if="katalog.layanan.ket" class="ld-produk-ket">{{ katalog.layanan.ket }}</div>
                <div class="ld-produk-bawah">
                  <div><div class="ld-harga" style="font-size:26px;color:var(--ld-hitam)">{{ rp(katalog.layanan.harga) }}</div><div class="ld-harga-masa">{{ katalog.layanan.masa }}</div></div>
                  <div class="ld-stepper">
                    <button type="button" :disabled="!jumlah[katalog.layanan.id]" aria-label="Kurangi layanan" @click="ubah(katalog.layanan.id, -1)">−</button>
                    <span aria-live="polite">{{ jumlah[katalog.layanan.id] ?? 0 }}</span>
                    <button type="button" aria-label="Tambah layanan" @click="ubah(katalog.layanan.id, 1)">+</button>
                  </div>
                </div>
              </div>

              <div v-if="katalog.aplikasi.length" class="ld-satuan">
                <div class="ld-satuan-kepala"><b>Aplikasi satuan</b><span>{{ katalog.aplikasi.length }} aplikasi · harga per aplikasi</span></div>
                <div class="ld-satuan-daftar">
                  <div v-for="a in katalog.aplikasi" :key="a.id" class="ld-satuan-baris" :class="{ ada: (jumlah[a.id] ?? 0) > 0 }">
                    <span><b>{{ a.nama }}</b><small>{{ rp(a.harga) }} · {{ a.masa.toLowerCase() }}</small></span>
                    <div class="ld-stepper">
                      <button type="button" :disabled="!jumlah[a.id]" :aria-label="`Kurangi ${a.nama}`" @click="ubah(a.id, -1)">−</button>
                      <span>{{ jumlah[a.id] ?? 0 }}</span>
                      <button type="button" :aria-label="`Tambah ${a.nama}`" @click="ubah(a.id, 1)">+</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <form class="ld-ringkas" novalidate @submit.prevent="buatTagihan">
              <div class="ld-ringkas-kepala"><b>Ringkasan</b><span>{{ butir }} butir</span></div>
              <div v-if="!baris.length" class="ld-ringkas-kosong">Belum ada yang dipilih.</div>
              <div v-else>
                <div v-for="b in baris" :key="b.id" class="ld-ringkas-baris">
                  <span>{{ b.nama }}</span><span class="ld-mono">×{{ b.n }}</span><b>{{ rp(b.sub) }}</b>
                </div>
              </div>
              <p v-if="paketDipilih && satuanDipilih" class="ld-ringkas-catatan">
                Paket menyeluruh sudah mencakup seluruh aplikasi — aplikasi satuan tidak perlu dipilih lagi.
              </p>
              <div class="ld-total"><span>Total</span><b>{{ rp(total) }}</b></div>

              <div class="ld-form">
                <label>
                  <span>Nama pembeli</span>
                  <input v-model="form.pembeli_nama" class="ld-isian" :class="{ salah: form.errors.pembeli_nama }"
                         autocomplete="name" required maxlength="150">
                </label>
                <label>
                  <span>Perusahaan (opsional)</span>
                  <input v-model="form.pembeli_perusahaan" class="ld-isian" autocomplete="organization" maxlength="150">
                </label>
                <div class="ld-form-dua">
                  <label>
                    <span>Email</span>
                    <input v-model="form.pembeli_email" type="email" class="ld-isian" :class="{ salah: form.errors.pembeli_email }"
                           autocomplete="email" maxlength="150">
                  </label>
                  <label>
                    <span>WhatsApp</span>
                    <input v-model="form.pembeli_telepon" type="tel" class="ld-isian" :class="{ salah: form.errors.pembeli_telepon }"
                           autocomplete="tel" maxlength="40">
                  </label>
                </div>
                <p v-for="(g, k) in form.errors" :key="k" class="ld-galat" role="alert">{{ g }}</p>
                <button type="submit" class="ld-tombol ld-tombol-aksen" :disabled="!baris.length || form.processing">
                  {{ form.processing ? 'Membuat tagihan…' : 'Buat tagihan' }}
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="PANAH" /></svg>
                </button>
                <div class="ld-tahap-beli" aria-label="Tahap pembelian"><span>01 PILIH</span><span>→ 02 TAGIHAN</span><span>→ 03 BAYAR QRIS</span><span>→ 04 LISENSI TERBIT</span></div>
                <p class="ld-kecil">
                  Total di sini hanya pengingat; harga dihitung ulang server saat tagihan dibuat.
                  Rincian tiap aplikasi ada di <Link :href="tautan.katalog">katalog</Link>.
                </p>
              </div>
            </form>
          </div>

          <div v-else class="ld-produk" style="margin-top:48px;max-width:640px">
            <div class="ld-produk-nama" style="color:var(--ld-hitam)">Harga sesuai kebutuhan site Anda</div>
            <p class="ld-produk-ket">Sebutkan modul yang dibutuhkan dan jumlah pekerjanya — penawaran dikirim langsung oleh tim EQOHSEE.</p>
            <a href="#kontak" class="ld-tombol ld-tombol-aksen" style="margin-top:20px">Minta penawaran</a>
          </div>
        </div>
      </section>

      <!-- ══════════ TANYA JAWAB ══════════
           Tidak bernomor: bagian ini tambahan di luar desain, dan nomor
           08 milik Kontak sebagaimana di desainnya. -->
      <section v-if="tanya.length" id="tanya" class="ld-abu ld-garis-atas">
        <div class="ld-lebar ld-blok ld-tanya-grid">
          <div class="ld-muncul">
            <p class="ld-mata">Tanya jawab</p>
            <h2 class="ld-h2">Yang biasa ditanyakan.</h2>
            <p class="ld-lead" style="margin-top:18px;font-size:16px">
              Belum terjawab? Sebutkan keadaan site Anda — kami balas dengan jawaban yang menyebut
              modulnya, bukan brosur.
            </p>
          </div>
          <div class="ld-tanya">
            <div v-for="(q, i) in tanya" :key="q.t" class="ld-tanya-butir" :class="{ buka: tanyaBuka === i }">
              <button type="button" :aria-expanded="tanyaBuka === i" :aria-controls="`ld-tanya-${i}`" @click="bukaTanya(i)">
                <span>{{ q.t }}</span>
                <i aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg></i>
              </button>
              <div :id="`ld-tanya-${i}`" class="ld-tanya-isi" role="region"><div><p>{{ q.j }}</p></div></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ══════════ 08 KONTAK ══════════ -->
      <section id="kontak" class="ld-putih ld-garis-atas">
        <div class="ld-lebar ld-blok ld-kontak-grid" style="padding-top:clamp(64px,8vw,112px);padding-bottom:clamp(64px,8vw,112px)">
          <div class="ld-muncul">
            <p class="ld-mata">08 — Hubungi kami</p>
            <h2 class="ld-h2" style="font-size:clamp(32px,3.8vw,52px)">Butuh penawaran khusus atau bantuan pemasangan?</h2>
            <p class="ld-lead" style="margin-top:18px;max-width:460px">
              Pemilihan paket, pemasangan di site, dan pertanyaan tagihan dijawab langsung oleh tim EQOHSEE.
            </p>
          </div>
          <div style="display:grid;gap:12px">
            <a v-if="waUrl" :href="waUrl" target="_blank" rel="noopener" class="ld-kontak-kartu">
              <span class="ld-kontak-ikon" style="background:#E4F6EA">
                <svg viewBox="0 0 24 24" fill="none" stroke="#15803D" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="WA" /></svg>
              </span>
              <span class="ld-kontak-teks"><small>WHATSAPP</small><b>{{ waTampil }}</b></span>
              <span class="ld-kontak-aksi" style="background:var(--ld-aksen);color:var(--ld-hitam)">Chat
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="PANAH" /></svg>
              </span>
            </a>
            <a v-if="emailUrl" :href="emailUrl" class="ld-kontak-kartu">
              <span class="ld-kontak-ikon" style="background:#FFF0DE">
                <svg viewBox="0 0 24 24" fill="none" stroke="#A85400" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Zm0 1 8 6 8-6" /></svg>
              </span>
              <span class="ld-kontak-teks"><small>EMAIL</small><b>{{ kontak.email }}</b></span>
              <span class="ld-kontak-aksi" style="border:1px solid var(--ld-garis-2);font-weight:600">Kirim email</span>
            </a>
            <Link v-if="!waUrl && !emailUrl" :href="tautan.katalog" class="ld-kontak-kartu">
              <span class="ld-kontak-teks"><small>KATALOG</small><b>Lihat seluruh harga</b></span>
            </Link>
          </div>
        </div>
      </section>

      <!-- ══════════ AJAKAN ══════════ -->
      <section class="ld-gelap ld-ajakan">
        <img src="/brand/tambang.jpg" alt="" loading="lazy" decoding="async">
        <div class="ld-lebar ld-ajakan-isi">
          <h2>Siap menaikkan level keselamatan?</h2>
          <p class="ld-lead" style="margin-top:22px;max-width:480px">
            Masuk dengan akun perusahaan Anda, atau mulai dari modul yang paling dibutuhkan lebih dulu.
          </p>
          <div class="ld-hero-aksi" style="margin-top:32px">
            <Link :href="tautan.masuk" class="ld-tombol ld-tombol-aksen">
              Masuk ke Platform
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="PANAH" /></svg>
            </Link>
            <a href="#harga" class="ld-tombol ld-tombol-garis">Lihat harga &amp; paket</a>
          </div>
        </div>
      </section>
    </main>

    <!-- Kebijakan privasi WAJIB tertaut dari sini (UU PDP): halaman ini
         tempat pertama orang diminta menyerahkan datanya. -->
    <footer class="ld-kaki">
      <div class="ld-lebar ld-kaki-isi">
        <div class="ld-kaki-merek">
          <span class="ld-merek"><img src="/brand/eqohsee-mark-128.png" alt="" width="26" height="26"><b>E<i>Q</i>OHSEE</b></span>
          <span>PLATFORM TERPADU KESELAMATAN PERTAMBANGAN</span>
        </div>
        <nav aria-label="Tautan kaki">
          <a :href="tautan.privasi">Kebijakan privasi</a>
          <a href="/privacy-policy">Privacy policy</a>
          <a v-if="waUrl" :href="waUrl" target="_blank" rel="noopener">WhatsApp {{ waTampil }}</a>
          <a v-if="emailUrl" :href="emailUrl">{{ kontak.email }}</a>
          <span>© {{ tahun }} EQOHSEE</span>
        </nav>
      </div>
    </footer>

    <a v-if="waUrl" :href="waUrl" target="_blank" rel="noopener" class="ld-apung" aria-label="Chat WhatsApp">
      <span><svg viewBox="0 0 24 24" fill="none" stroke="#0B1117" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="WA" /></svg></span>
      <span>WhatsApp</span>
    </a>

    <!-- Pemutar video: judul dan berkasnya dari kartu yang ditekan
         (videoAktif.src), bukan rekaman hero. Esc, latar, atau silang
         menutupnya. -->
    <Transition name="ld-pemutar">
      <div v-if="videoAktif" class="ld-pemutar" role="dialog" aria-modal="true"
           :aria-label="videoAktif.judul" @click.self="videoAktif = null">
        <div class="ld-pemutar-isi">
          <div class="ld-pemutar-kepala">
            <span>{{ videoAktif.judul }}</span>
            <button type="button" aria-label="Tutup video" @click="videoAktif = null">&times;</button>
          </div>
          <video :key="videoAktif.src" :src="videoAktif.src" :poster="videoAktif.poster ?? undefined"
                 controls autoplay playsinline></video>
        </div>
      </div>
    </Transition>
  </div>
</template>
