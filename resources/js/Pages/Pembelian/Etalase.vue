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
 * tampil lengkap dengan keterangannya, hanya tanpa tombol beli. Yang
 * disembunyikan cara membelinya, bukan keberadaannya: katalog yang
 * separuh kosong terbaca sebagai perusahaan yang tidak punya apa-apa
 * untuk dijual, bukan sebagai harga yang belum diumumkan.
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
type Paket = { id: number; nama: string; ket: string | null; harga: number; masa: string };

const props = defineProps<{
  paket: Paket | null;
  aplikasi: Aplikasi[];
  pilar: Record<string, { nama: string; warna: string; deep: string }>;
  adaHarga: boolean;
  kontak: { whatsapp: string; email: string };
  tahun: number;
}>();

/** id produk => banyaknya. Nol berarti tidak dipilih. */
const pilih = reactive<Record<number, number>>({});

const saring = ref<string | null>(null);

const terjual = computed(() => props.aplikasi.filter((a) => a.id !== null));

const tampil = computed(() => (saring.value === null
  ? props.aplikasi
  : props.aplikasi.filter((a) => a.pilar === saring.value)));

/** Pilar yang benar-benar punya kartu — chip untuk pilar kosong menyesatkan. */
const pilarDipakai = computed(() => Object.entries(props.pilar)
  .filter(([slug]) => props.aplikasi.some((a) => a.pilar === slug)));

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

/**
 * Bilah keranjang menyingkir begitu formulirnya terlihat.
 *
 * Tanpa ini, pada ponsel bilah itu duduk di atas isian terbawah selama
 * seluruh pengisian — persis pada layar tersempit, tempat ruangnya
 * paling mahal. Ia berguna ketika pilihannya berada beberapa layar di
 * atas tombol pesannya; begitu keduanya terlihat bersama, ia hanya
 * menutupi.
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

function ambilSemua() {
  for (const a of terjual.value) pilih[a.id as number] = 1;
  keForm();
}

const f = useForm<Record<string, any>>({
  pembeli_nama: '', pembeli_perusahaan: '', pembeli_email: '',
  pembeli_telepon: '', catatan: '', produk: {} as Record<number, number>,
});

function kirim() {
  f.produk = Object.fromEntries(terpilih.value.map((p) => [p.id, pilih[p.id]]));

  f.post('/katalog/pesan', { preserveScroll: true });
}

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

const waUrl = computed(() => (props.kontak.whatsapp
  ? 'https://wa.me/' + props.kontak.whatsapp.replace(/\D/g, '')
  : null));

/**
 * Kemana tombol "minta penawaran" menuju bila tidak ada satu pun cara
 * hubung yang diatur.
 *
 * Tanpa cadangan ini, ajakan "sebutkan kebutuhan Anda, penawarannya
 * kami kirim" berdiri tanpa satu pun tombol di bawahnya — kalimat yang
 * meminta sesuatu lalu tidak menyediakan caranya. Daftar modul bukan
 * jawaban atas pertanyaan harga, tetapi ia tujuan yang benar-benar ada,
 * dan itu lebih baik daripada jalan buntu.
 */
const tanya = computed(() => (waUrl.value
  ?? (props.kontak.email ? 'mailto:' + props.kontak.email : null)));

const langkah = [
  ['Pilih', 'Ambil paket menyeluruh, atau aplikasi satuan yang Anda butuhkan.'],
  ['Tagihan terbit', 'Nomor tagihan dan tautan pembayaran muncul seketika — tanpa perlu akun.'],
  ['Bayar QRIS', 'Pindai kodenya dari aplikasi bank atau dompet digital mana pun.'],
  ['Lisensi aktif', 'Bukti bayar diperiksa, lisensinya terbit, dan akses Anda menyala.'],
];
</script>

<template>
  <Head title="Beli EQOHSEE — Paket dan Aplikasi" />

  <div class="bg-cam-bg text-cam-ink scroll-smooth pb-24">

    <!-- ══════════ kepala ══════════ -->
    <header class="fixed inset-x-0 top-0 z-40 border-b border-white/10 bg-cam-ink/85 backdrop-blur-md text-white">
      <div class="max-w-6xl mx-auto px-5 h-[66px] flex items-center gap-3">
        <Link href="/" class="shrink-0"><Wordmark :tinggi="30" /></Link>
        <nav class="ml-auto hidden md:flex items-center gap-1 text-[12.5px] font-semibold">
          <a v-for="item in [['#paket','Paket'],['#aplikasi','Aplikasi'],['#cara','Cara Beli']]"
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
      <div class="relative max-w-6xl mx-auto px-5 py-16 md:py-20">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_360px] gap-9 lg:gap-12 items-center">
          <div>
            <span class="inline-flex items-center gap-2 glass rounded-full px-3.5 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.18em] text-cam-lime-light">
              <span class="w-1.5 h-1.5 rounded-full bg-cam-lime animate-pulse"></span>
              {{ aplikasi.length }} Aplikasi · Satu Platform
            </span>

            <h1 class="font-display text-[34px] sm:text-[44px] xl:text-[54px] font-black mt-5 leading-[1.06]">
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
          <aside class="glass rounded-2xl p-6">
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

            <ul class="space-y-2.5 mt-6 pt-5 border-t border-white/10">
              <li v-for="t in ['Data terpisah per perusahaan','Pembaruan aplikasi termasuk','Pendampingan pemasangan','Sesuai regulasi Minerba Indonesia']"
                  :key="t" class="flex gap-2.5 text-[11.5px] text-white/60">
                <span class="shrink-0 w-4 h-4 rounded-full bg-cam-lime/25 text-cam-lime-light grid place-items-center text-[9px]">✓</span>
                {{ t }}
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

      <div v-if="paket"
           class="relative mt-10 rounded-3xl overflow-hidden brand-gradient text-white shadow-card">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_300px] gap-8 p-8 md:p-11">
          <div>
            <h3 class="font-display text-[24px] md:text-[28px] font-black">{{ paket.nama }}</h3>
            <p v-if="paket.ket" class="text-[13px] text-white/65 mt-3 max-w-xl leading-relaxed">{{ paket.ket }}</p>

            <div class="flex flex-wrap gap-1.5 mt-6">
              <span v-for="a in aplikasi" :key="a.nama"
                    class="text-[10.5px] font-semibold rounded-full px-2.5 py-1 bg-white/10 text-white/70">
                {{ a.nama }}
              </span>
            </div>
          </div>

          <div class="lg:border-l lg:border-white/15 lg:pl-8 flex flex-col justify-center">
            <div class="text-[10.5px] font-bold uppercase tracking-[0.2em] text-cam-lime-light">Harga</div>
            <div class="num font-display text-[32px] font-black mt-1.5 leading-none">{{ rupiah(paket.harga) }}</div>
            <div class="text-[11.5px] text-white/50 mt-1.5">{{ paket.masa }}</div>

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
      <div v-else class="mt-10 rounded-3xl border border-stone-200 bg-white shadow-card px-7 py-9 text-center">
        <h3 class="font-display text-[22px] font-black text-cam-ink">Paket disusun sesuai kebutuhan</h3>
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
    </section>

    <!-- ══════════ aplikasi satuan ══════════ -->
    <section id="aplikasi" class="bg-gradient-to-b from-cam-bg via-white to-cam-bg scroll-mt-[80px]">
      <div class="max-w-6xl mx-auto px-5 py-16 md:py-20">
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
          <button v-for="[slug, p] in pilarDipakai" :key="slug" type="button"
                  class="etalase-chip" :class="saring === slug ? 'etalase-chip-aktif' : ''"
                  :style="saring === slug ? { background: p.deep, borderColor: p.deep } : { borderColor: p.warna + '55', color: p.deep }"
                  @click="saring = saring === slug ? null : slug">
            {{ p.nama }}
          </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mt-8">
          <article v-for="a in tampil" :key="a.nama"
                   class="relative flex flex-col bg-white rounded-2xl shadow-card border border-stone-100 p-6">
            <span class="absolute inset-x-0 top-0 h-[3px] rounded-t-2xl"
                  :style="{ background: `linear-gradient(90deg, ${a.pilarDeep}, ${a.pilarWarna})` }"></span>

            <div class="w-11 h-11 rounded-xl grid place-items-center text-white"
                 :style="{ background: `linear-gradient(135deg, ${a.pilarDeep}, ${a.pilarWarna})` }">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path :d="a.ikon" />
              </svg>
            </div>

            <h3 class="text-[14.5px] font-bold text-cam-ink mt-4">{{ a.nama }}</h3>
            <p class="text-[12.5px] text-stone-500 mt-2 leading-relaxed flex-1">{{ a.ket }}</p>

            <div class="text-[11px] font-bold mt-4" :style="{ color: a.pilarDeep }">Pilar {{ a.pilarNama }}</div>

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
    <section id="cara" class="max-w-5xl mx-auto px-5 py-16 md:py-20 scroll-mt-[80px]">
      <div class="text-center max-w-xl mx-auto">
        <span class="text-[10.5px] font-bold uppercase tracking-[0.22em] text-cam-lime-dark">Cara Beli</span>
        <h2 class="font-display text-[30px] md:text-[38px] font-black text-cam-ink mt-3">Empat langkah</h2>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mt-10">
        <div v-for="(l, i) in langkah" :key="l[0]"
             class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
          <div class="stat stat-sm text-cam-lime/35">{{ String(i + 1).padStart(2, '0') }}</div>
          <h3 class="text-[13.5px] font-bold text-cam-ink mt-2">{{ l[0] }}</h3>
          <p class="text-[12px] text-stone-500 mt-1.5 leading-relaxed">{{ l[1] }}</p>
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

  <!-- ══════════ bilah keranjang ══════════

       Melekat di bawah layar, bukan hanya di panel yang jauh: pada
       ponsel, daftar 21 kartu berarti pilihannya berada beberapa layar
       di atas tombol pesannya, dan yang memilih tidak punya cara tahu
       bahwa pilihannya tercatat. -->
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
