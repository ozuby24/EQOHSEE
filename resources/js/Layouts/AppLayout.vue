<script setup lang="ts">
/**
 * Kerangka halaman Inertia.
 *
 * Menggambar bilah samping dan bilah atas yang sama dengan
 * layouts/app.blade.php, memakai kelas CSS yang sama pula (eq-*, dari
 * partials/eq-visual.blade.php). Gayanya sengaja tidak ditulis ulang di
 * sini: dua salinan aturan warna yang panjang pasti berbeda isinya cepat
 * atau lambat, dan bedanya baru ketahuan saat orang membandingkan dua
 * halaman berdampingan.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import type { PropBersama } from '../types';
import KopHalaman from '../Components/KopHalaman.vue';
import KutipanKaki from '../Components/KutipanKaki.vue';
import TurSelamatDatang from '../Components/TurSelamatDatang.vue';
import MuatBerjalan from '../Components/MuatBerjalan.vue';

/*
  `<Link>` HANYA untuk tujuan yang benar-benar dirender Inertia.

  `<a href>` selalu memicu navigasi peramban penuh — Inertia tidak
  mencegat klik anchor apa pun kecuali lewat komponennya sendiri. Ini
  pernah tertinggal: sidebar Vue ini menyalin markup sidebar Blade apa
  adanya, termasuk `<a href>`-nya, sehingga berpindah antar DUA halaman
  yang sama-sama Inertia pun tetap memuat ulang penuh — persis cacat
  yang seharusnya sudah tidak ada begitu keduanya sama-sama Vue.

  Dugaan bahwa <Link> otomatis jatuh ke navigasi penuh ketika tanggapannya
  bukan Inertia itu KELIRU, dan keliru dengan cara yang mahal: ia mengirim
  permintaan ber-header X-Inertia, menerima HTML utuh, lalu menampilkan
  modal galat dan tetap diam. Sempat terjadi persis begitu — seluruh menu
  memakai <Link>, dan dari halaman Vue tidak satu pun menu bisa diklik.

  Karena itu server menandai tiap tautan (`inertia: true/false`, dari
  App\Support\RuteInertia) dan komponen di bawah memilih bentuk tautannya
  sesuai tanda itu.
*/
const tautan = (inertia: boolean) => (inertia ? Link : 'a');

const halaman = usePage<PropBersama>();
const menu    = computed(() => halaman.props.menu);

/*
  Sampul hanya dikirim server pada halaman AWAL tiap modul — lihat
  HandleInertiaRequests::sampul(). Di sini tidak ada aturan kedua tentang
  kapan ia muncul: aturan yang ditulis di dua tempat akan berselisih, dan
  yang di sisi peramban adalah yang paling sulit diperiksa.
*/
const sampul  = computed(() => (halaman.props as Record<string, unknown>).sampul as any ?? null);

/*
  Pengenalan situs.

  Server mengirim LANGKAHNYA, bukan sekadar penanda, dan hanya kepada
  akun yang memang belum menyelesaikannya. Karena isinya sudah ada
  bersama halaman, sambutan bagi pengguna baru tidak menyentuh jaringan
  sama sekali — tidak ada permintaan yang dapat gagal, dan tidak ada
  kotak galat yang muncul lagi pada tiap penyegaran.

  Terbukanya DIPEGANG DI SINI sesudah itu, tidak terus terikat pada
  prop-nya: penanda di server baru berubah setelah permintaan
  penyelesaiannya sampai, dan sepanjang jeda itu tiap perpindahan
  halaman akan menyalakan ulang sambutan yang baru saja ditutup orangnya.
*/
const turBawaan  = computed(() => (halaman.props as Record<string, unknown>).tur as any[] | null ?? null);
const sampulHalaman = computed(() =>
  ((halaman.props as Record<string, unknown>).sampul as
    { gambar: string; webp?: string | null; keterangan?: string | null } | null) ?? null);
const turTerbuka = ref(Boolean(turBawaan.value?.length));

/* Membuka lagi dari menu akun.
 *
 * Pengguna lama tidak membawa `tur` pada propnya — server hanya
 * menyusunnya bagi yang belum menyelesaikan pengenalan — sehingga di
 * sini isinya memang diambil lewat /tur. Itu satu-satunya jalur yang
 * masih menyentuh jaringan, dan satu-satunya yang pantas menampilkan
 * pesan bila gagal. */
function bukaTur() {
  akunTerbuka.value = false;
  turTerbuka.value  = true;
}

/** Ikon modul yang sedang dibuka, untuk kotak pemindah modul. */
const ikonModul = computed(() => menu.value?.modul?.find((m) => m.aktif)?.ikon ?? null);

/** Butir menu yang sedang dibuka, untuk remah roti dan judul cadangan. */
const butirAktif = computed(() => menu.value?.grup
  ?.flatMap((g) => g.butir).find((b) => b.aktif) ?? null);

/**
 * Judul halaman.
 *
 * CADANGANNYA BUKAN KATA UMUM. "Dashboard" sebagai cadangan membuat
 * enam modul berbeda — Energi, Lingkungan, Biaya, Geoteknik, Peledakan,
 * Angkutan — mencetak judul yang sama persis di kopnya, sebab keenam
 * controllernya memang tidak mengirim `judul`. Yang terbaca bukan
 * "judulnya belum diisi" melainkan "saya sedang di dasbor", pada enam
 * halaman yang bukan dasbor.
 *
 * Jatuhnya ke butir menu yang sedang dibuka, lalu ke nama modulnya:
 * keduanya selalu ada, dan keduanya selalu menyebut tempat yang benar.
 */
const judul = computed(() => (halaman.props as Record<string, unknown>).judul as string
  || butirAktif.value?.label
  || menu.value?.label
  || 'EQOHSEE');
const subjudul   = computed(() => (halaman.props as Record<string, unknown>).subjudul as string | undefined);
const pengguna   = computed(() => halaman.props.pengguna);
const kilat      = computed(() => halaman.props.kilat);
const pengumuman = computed(() => halaman.props.pengumuman ?? 0);

const lacisTerbuka = ref(false);
const sempit       = ref(false);

/* ─────────── Kop halaman ───────────
 *
 * DIRENDER DI SINI, BUKAN DI TIAP HALAMAN. Kop adalah kerangka, sama
 * seperti bilah atas dan bilah samping: ia menyebut di mana pembacanya
 * berada. Ditulis ulang pada dua ratusan halaman, tingginya akan
 * berselisih, jarak remahnya berselisih, dan ukuran judulnya
 * berselisih — dan selisih itu terbaca sebagai aplikasi yang
 * dikerjakan beberapa orang yang tidak pernah bertemu.
 *
 * Halaman yang memang perlu kop khusus mengirim `kop: false` dari
 * controllernya lalu menggambar kopnya sendiri.
 */

const kopSendiri = computed(() => (halaman.props as Record<string, unknown>).kop === false);

/**
 * Isian tambahan kop yang dikirim halaman lewat controllernya.
 *
 * Kop dirender kerangka, sehingga halaman tidak dapat mengisi slotnya.
 * Yang dapat dikirimnya adalah DATA — dan itu memang batas yang benar:
 * kop yang dapat diisi markup sembarang akan segera berbeda-beda
 * bentuknya, dan seluruh alasan memindahkannya ke kerangka hilang.
 */
const kopIsi = computed(() => {
  const k = (halaman.props as Record<string, unknown>).kop;

  return k && typeof k === 'object' ? k as Record<string, any> : {};
});

const remahKop = computed<[string, string | null][]>(() => {
  const r: [string, string | null][] = [];
  const m = menu.value;

  if (!m) return r;

  // Akar modul menjadi tautan HANYA bila pembacanya tidak sedang
  // berdiri di sana. Remah yang menautkan ke halaman yang sedang
  // dibuka terlihat dapat ditekan dan tidak membawa ke mana pun.
  r.push([m.label, butirAktif.value && m.akar && !butirAktif.value.aktif ? m.akar : null]);

  if (butirAktif.value && butirAktif.value.label !== m.label) {
    r.push([butirAktif.value.label, null]);
  }

  return r;
});

/* ─────────── Bilah pindah sub-halaman ───────────
 *
 * HANYA DI HALAMAN BERANDA MODUL, bukan di tiap halaman. Bilah samping
 * sudah memuat daftar yang sama persis dan selalu terlihat; digambar
 * lagi di atas tiap halaman, yang bertambah bukan kemudahan berpindah
 * melainkan dua salinan daftar yang sama pada satu layar — dan yang
 * kedua memakan tiga baris tepat di tempat isi halaman seharusnya
 * dimulai.
 *
 * Di beranda modul ia bukan salinan melainkan INDEKS: halaman itu
 * memang bertugas memperkenalkan isi modulnya, dan di situlah daftar
 * lengkap justru yang dicari.
 */

const diBeranda = computed(() => {
  const pertama = menu.value?.grup?.[0]?.butir?.[0];

  return !!pertama && !!butirAktif.value && pertama.url === butirAktif.value.url;
});

const pindahCepat = computed(() => {
  if (!diBeranda.value) return [];

  const grup = menu.value?.grup ?? [];
  const semua = grup.flatMap((g) => g.butir.map((b) => ({ butir: b, grup: g.nama })));

  // Satu butir tidak perlu bilah pindah: tidak ada tempat lain untuk
  // dituju, dan barisnya hanya menggambar tombol menuju halaman yang
  // sedang dibuka.
  if (semua.length < 2) return [];

  /* LABEL YANG BERULANG DIBERI NAMA GRUPNYA.
   *
   * Bilah samping membedakan butir bernama sama lewat judul grup di
   * atasnya — Miners punya "MCU" pada Pendaftaran, pada Riwayat, dan
   * pada Outstanding. Diratakan menjadi satu baris, judul grupnya
   * hilang dan yang tersisa adalah tiga pil bertuliskan "MCU" yang
   * menuju tiga tempat berbeda. Itu bukan navigasi yang padat,
   * melainkan navigasi yang tidak dapat dipakai.
   */
  const hitung = new Map<string, number>();
  for (const { butir } of semua) hitung.set(butir.label, (hitung.get(butir.label) ?? 0) + 1);

  return semua.map(({ butir, grup: nama }) => ({
    ...butir,
    label: (hitung.get(butir.label) ?? 0) > 1 && nama
      ? `${nama} · ${butir.label}`
      : butir.label,
  }));
});

/* ─────────── Akun ─────────── */

const akunTerbuka = ref(false);

/**
 * Inisial: satu huruf dari dua kata pertama namanya.
 *
 * Bukan satu huruf saja. Pada daftar pengguna sungguhan, "Budi" dan
 * "Bambang" menghasilkan lingkaran yang sama persis — dan lingkaran
 * itulah satu-satunya penanda akun siapa yang sedang dibuka.
 */
const inisial = computed(() => (pengguna.value?.nama ?? '?')
  .split(/\s+/).filter(Boolean).slice(0, 2)
  .map((k) => k.charAt(0).toUpperCase()).join('') || '?');

/* ─────────── Pintasan pencarian ─────────── */

const kotakCari = ref<HTMLInputElement | null>(null);

/* ─────────── Perusahaan yang sedang dilihat ─────────── */

const perusahaanTerbuka = ref(false);

const pilihanPerusahaan = computed(() => pengguna.value?.perusahaanPilihan ?? []);

/**
 * Nama yang ditulis di bilah atas.
 *
 * "Semua perusahaan" bukan teks cadangan melainkan keadaan yang sah:
 * administrator yang belum menyempitkan pandangannya memang sedang
 * melihat seluruhnya, dan kotak kosong di tempat itu terbaca sebagai
 * data yang gagal dimuat.
 */
const namaPerusahaan = computed(() => pengguna.value?.perusahaan ?? 'Semua perusahaan');

function gantiPerusahaan(id: number | null) {
  perusahaanTerbuka.value = false;

  // Seluruh halaman dimuat ulang, bukan sebagian: yang berubah adalah
  // lingkup DATA, sehingga tiap angka di layar — termasuk yang di bilah
  // samping dan di dasbor — harus dihitung ulang. Menjaga sebagian
  // keadaan akan menyisakan angka perusahaan sebelumnya di samping
  // angka perusahaan yang baru.
  router.post('/perusahaan-dilihat', { perusahaan: id });
}

/**
 * Panel pemindah modul.
 *
 * Menggantikan kisi 22 ikon telanjang yang dulu duduk di atas menu.
 * Kisi itu padat dan tidak dapat dibaca: ikon tanpa label memaksa orang
 * menghafal posisi, dan yang tidak hafal menekan satu per satu sampai
 * ketemu. Sekarang yang terlihat hanya NAMA modul yang sedang dibuka;
 * daftar lengkapnya — beserta labelnya — muncul saat diminta.
 */
const modulTerbuka = ref(false);

/**
 * Tanggal hari ini, ditulis lengkap dalam bahasa Indonesia.
 *
 * Menggantikan nama penyapa di baris kecil ini. Namanya PINDAH ke chip
 * akun di ujung kanan — dicetak di kedua ujung, nama yang sama muncul
 * dua kali pada satu baris pandang.
 *
 * Dihitung di peramban dengan alasan yang sama seperti sapaannya:
 * server berjalan pada UTC, dan tanggal UTC berganti pukul delapan pagi
 * di lokasi tambang — "Senin" yang tercetak sepanjang Minggu malam
 * terbaca sebagai kalender aplikasi yang salah.
 */
const hariIni = computed(() => new Intl.DateTimeFormat('id-ID', {
  weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
}).format(new Date()));

/**
 * Sapaan menurut jam setempat.
 *
 * Dihitung di peramban, bukan di server. Server berjalan pada UTC,
 * sedangkan yang membaca sapaan ini duduk di lokasi tambang: sapaan
 * "Selamat malam" pada pukul sembilan pagi terbaca sebagai jam aplikasi
 * yang salah, bukan sebagai basa-basi yang keliru.
 */
const sapaan = computed(() => {
  const j = new Date().getHours();

  if (j < 11) return 'Selamat pagi';
  if (j < 15) return 'Selamat siang';
  if (j < 19) return 'Selamat sore';

  return 'Selamat malam';
});

/* Penggal nama depan DIBUANG bersama sapaan bernamanya. Chip akun
   mencetak nama LENGKAP beserta gelarnya — di sana gelar memang bagian
   dari identitas, bukan sisipan yang mengganggu seperti pada sapaan. */

const cari = ref('');

/**
 * Pencarian menuju daftar pekerja, sebab hanya itu yang benar-benar
 * dapat dicari hari ini — nama, NIK, dan jabatan, lewat `q` pada
 * /miners. Kotak cari yang tidak menuju ke mana-mana lebih buruk
 * daripada tidak ada: ia menjanjikan sesuatu lalu diam.
 */
function kirimCari() {
  const q = cari.value.trim();

  router.get('/miners', q ? { q } : {}, { preserveState: false });
}

/* Tinggi bilah atas diterbitkan sebagai `--eq-topbar-h`.

   Halaman yang punya kepala MELEKAT sendiri — daftar periksa inspeksi,
   misalnya — harus melekat tepat di bawah bilah ini, dan tingginya
   tidak tetap: 66px di ponsel, 71px di layar lebar, dan berubah lagi
   bila judul halamannya memerlukan dua baris. Angka yang ditulis
   tangan di halaman itu benar pada satu lebar saja; pada lebar yang
   lain kepalanya tersembunyi sebagian di balik bilah atas, atau
   menyisakan celah tempat baris tabel lewat. */
let ukur: ResizeObserver | null = null;

onMounted(() => {
  try {
    sempit.value = localStorage.getItem('eq-sisi-sempit') === '1';
  } catch { /* mode privat */ }

  if (sempit.value) document.body.classList.add('eq-sempit');

  window.addEventListener('keydown', pintasan);

  const bilah = document.querySelector('.eq-topbar');

  if (bilah && typeof ResizeObserver !== 'undefined') {
    /* `getBoundingClientRect`, bukan `contentRect`: yang dipakai
       sebagai jarak melekat adalah tinggi kotak tepi — bilah atas
       punya garis bawah 1px, dan kepala yang melekat setinggi kotak
       isi berhenti satu piksel terlalu tinggi. */
    ukur = new ResizeObserver(([e]) => {
      document.documentElement.style.setProperty(
        '--eq-topbar-h', `${Math.round(e.target.getBoundingClientRect().height)}px`,
      );
    });
    ukur.observe(bilah);
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', pintasan);
  ukur?.disconnect();
});

function pintasan(e: KeyboardEvent) {
  if (e.key?.toLowerCase() === 'k' && (e.metaKey || e.ctrlKey)) {
    e.preventDefault();
    kotakCari.value?.focus();
    return;
  }

  // Escape menutup apa pun yang sedang terbuka. Tanpa ini, panel yang
  // terbuka hanya dapat ditutup dengan menekan ke luar — dan yang
  // menekan Escape lebih dahulu menyimpulkan panelnya macet.
  if (e.key === 'Escape') {
    akunTerbuka.value = false;
    perusahaanTerbuka.value = false;
  }
}

function lipat() {
  sempit.value = !sempit.value;
  document.body.classList.toggle('eq-sempit', sempit.value);

  try {
    localStorage.setItem('eq-sisi-sempit', sempit.value ? '1' : '0');
  } catch { /* mode privat */ }
}

/**
 * Sakelar tema.
 *
 * Tampilan berubah lebih dulu, pilihannya dikirim ke server sesudahnya —
 * menunggu jawaban server membuat tombolnya terasa macet pada sambungan
 * lapangan yang lambat. Kalau pengirimannya gagal, yang hilang hanya
 * keawetan pilihan antar perangkat, bukan sakelarnya sendiri.
 */
function gantiTema() {
  const akar = document.documentElement;
  const kini = akar.getAttribute('data-tema')
    ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'gelap' : 'terang');
  const baru = kini === 'gelap' ? 'terang' : 'gelap';

  akar.setAttribute('data-tema', baru);
  try { localStorage.setItem('eqTema', baru); } catch { /* mode privat */ }

  router.post('/personalia/tema', { tema: baru }, {
    preserveScroll: true,
    preserveState: true,
    only: [],
  });
}

function keluar() {
  router.post('/logout');
}
</script>

<template>
  <div class="min-h-screen flex">

    <!-- Penanda "sedang memuat". Di tata letak, bukan di tiap halaman:
         yang paling perlu ditandai justru muat ulang sebagian, dan itu
         terjadi di dua belas modul lewat pemilih dan saringan yang
         masing-masing tidak tahu satu sama lain. -->
    <MuatBerjalan />

    <!-- ═══════════ BILAH SAMPING ═══════════ -->
    <div v-show="lacisTerbuka" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-30 lg:hidden"
         @click="lacisTerbuka = false"></div>

    <!-- SETINGGI LAYAR DAN MELEKAT, bukan setinggi halaman.

         Dengan `lg:static` di dalam pembungkus `min-h-screen`, bilah ini
         ikut memanjang mengikuti isi halaman: pada halaman sepanjang
         1300px ia menjadi setinggi 1300px, dan kakinya — tombol bantuan,
         kartu semboyan, tombol lipat — berhenti di titik yang tidak
         pernah terlihat tanpa menggulir sampai dasar halaman. Daftar
         menunya pun tidak pernah bergulir sendiri, sebab ia tidak pernah
         kehabisan ruang.

         Dipaku setinggi layar, kakinya selalu terlihat dan menunya
         bergulir di dalam dirinya sendiri. `sticky`, bukan `fixed`:
         fixed melepasnya dari aliran dan kolom isi di sebelahnya
         kehilangan lebarnya. -->
    <aside id="eqSidebar"
           class="brand-gradient fixed lg:sticky lg:top-0 lg:h-screen inset-y-0 left-0 z-40
                  w-[248px] shrink-0 flex flex-col
                  text-white/70 transition-transform duration-300"
           :class="lacisTerbuka ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

      <a href="/dashboard" class="eq-merek">
        <!-- Varian 128 px, bukan berkas 512 px seberat 217 KB untuk kotak 40 px. -->
        <img src="/brand/eqohsee-mark-128.png" alt="" width="40" height="40">
        <span>
          <strong>E<em>Q</em>OHSEE</strong>
          <small>Safe Today · Sustainable Tomorrow</small>
        </span>
      </a>

      <!-- ── Pemindah modul ──

           Dulu di sini duduk kisi 22 ikon telanjang. Ikon tanpa label
           memaksa orang menghafal posisinya, dan yang belum hafal
           menekan satu per satu sampai ketemu — dua puluh dua kotak
           yang seluruhnya terlihat sama.

           Sekarang yang terlihat hanya modul yang sedang dibuka.
           Daftar lengkapnya muncul saat diminta, dengan LABELNYA, jadi
           tidak ada yang perlu dihafal. -->
      <div class="px-3 pt-3.5">
        <button type="button" class="eq-modul-pilih" :aria-expanded="modulTerbuka"
                :title="menu.label" aria-label="Pindah modul"
                @click="modulTerbuka = !modulTerbuka">
          <!-- IKONNYA IKUT, DAN ITU YANG TERSISA SAAT BILAHNYA DILIPAT.
               Tanpa ikon, kotak ini menjadi kotak kosong berisi satu
               panah — tidak menyebutkan modul apa yang sedang dibuka,
               padahal itulah satu-satunya tugasnya. -->
          <svg v-if="ikonModul" class="eq-modul-ikon" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round"
               stroke-linejoin="round" aria-hidden="true">
            <path :d="ikonModul"/>
          </svg>

          <span class="eq-modul-nama">{{ menu.label }}</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
               :style="modulTerbuka ? 'transform:rotate(180deg)' : ''">
            <path d="m6 9 6 6 6-6"/>
          </svg>
        </button>

        <div v-if="modulTerbuka" class="eq-modul-daftar">
          <component :is="tautan(m.inertia)"
             v-for="m in menu.modul" :key="m.kunci" :href="m.url"
             class="eq-modul-butir" :class="m.aktif ? 'eq-modul-aktif' : ''">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path :d="m.ikon"/>
            </svg>
            <span>{{ m.label }}</span>
            <span v-if="m.lencana" class="eq-modul-titik"></span>
          </component>
        </div>
      </div>

      <!-- BOLEH MENYUSUT, TIDAK BOLEH MEMBESAR.

           Dengan `flex-1`, nav menyerap seluruh sisa ruang kolom —
           sehingga pada modul bermenu pendek seperti Administrasi,
           antara butir terakhir dan kaki bilah terbentang rongga gelap
           setinggi sepertiga layar. Yang mengisi rongga itu tidak ada,
           dan sudut kiri bawah terbaca sepi.

           Sekarang sisanya jatuh ke kaki, tempat kartu semboyan
           memuainya. Menu yang panjang tetap menyusut dan bergulir
           seperti sebelumnya. -->
      <nav class="min-h-0 shrink basis-auto grow-0 overflow-y-auto px-3 py-3">
        <template v-for="(g, i) in menu.grup" :key="i">
          <p v-if="g.nama" class="px-3 mt-3 mb-1 text-[9.5px] font-semibold uppercase
                                  tracking-[0.12em] text-white/55">{{ g.nama }}</p>
          <div class="space-y-0.5">
            <component :is="tautan(b.inertia)"
               v-for="b in g.butir" :key="b.url" :href="b.url"
               class="relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-[12.5px]
                      font-semibold hover:bg-white/5 hover:text-white transition"
               :class="b.aktif ? 'nav-active' : ''">
              <span class="nav-accent absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-5
                           rounded-r-full bg-cam-lime-light opacity-0"></span>
              <svg class="eq-navico" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path :d="b.ikon"/>
              </svg>
              <span>{{ b.label }}</span>
              <span v-if="b.lencana" class="ml-auto text-[10px] font-bold leading-none px-1.5 py-1
                                            rounded-full bg-cam-lime-light text-cam-ink">
                {{ b.lencana > 99 ? '99+' : b.lencana }}
              </span>
            </component>
          </div>
        </template>
      </nav>

      <!-- ── Kaki bilah samping ──

           Chip pengguna NAIK dari sini ke bilah atas, dan tempatnya
           diisi kartu semboyan bergambar. Yang naik hanyalah
           lingkarannya: sapaan di bilah atas sudah menyebut namanya,
           dan nama yang tercetak dua kali pada satu baris pandang
           adalah persis alasan chip itu dulu diturunkan ke sini.

           Kartu "Butuh Bantuan?" yang dulu di sini tetap berupa satu
           tombol. Kartu setinggi 96px yang isinya tidak pernah berubah
           memakan ruang yang dibutuhkan menu, dan menu yang terpotong
           membuat butir terbawahnya tidak pernah ditemukan. -->
      <div class="eq-sisi-kaki">
        <Link href="/bantuan" class="eq-bantuan-btn" title="Butuh bantuan?">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.2-.6L3.5 21l1.7-4.6A8.2 8.2 0 0 1 4 11.5a8.4 8.4 0 0 1 9-8.4 8.4 8.4 0 0 1 8 8.4Z"/>
          </svg>

          <!-- DIBUNGKUS SPAN, bukan dibiarkan sebagai teks telanjang.
               Aturan mode terlipat menyembunyikan `span` di dalam tombol
               ini; teks telanjang tidak dapat disembunyikan CSS mana pun,
               sehingga pada bilah selebar 72px kalimatnya tetap tercetak
               dan membungkus menjadi dua baris di dalam kotak yang tidak
               cukup untuk satu kata pun. -->
          <span>Butuh bantuan?</span>
        </Link>

        <!-- Kartu kaki: semboyan bergambar dengan baris hak cipta di
             dalamnya, satu kotak yang TURUN SAMPAI TEPI PALING BAWAH
             bilah — tanpa sudut membulat dan tanpa jarak di bawahnya.

             `aria-hidden` DUDUK PADA SEMBOYANNYA, bukan pada kartunya.
             Baris hak cipta memuat tombol lipat, dan tombol yang dapat
             difokus di dalam wadah ber-aria-hidden adalah cacat yang
             sungguhan: Tab tetap sampai ke sana, pembaca layar tidak
             pernah menyebutkan apa yang sedang difokus.

             Semboyannya pula yang disembunyikan saat bilahnya dilipat
             dan saat layarnya pendek — kartunya tetap ada, sebab tombol
             lipat itulah satu-satunya jalan keluar dari keadaan
             terlipat. -->
        <div class="eq-sisi-kartu">
          <div class="eq-semboyan" aria-hidden="true">
            <img class="eq-semboyan-gambar" src="/brand/tambang.jpg" alt=""
                 loading="lazy" decoding="async">
            <div class="eq-semboyan-tirai" />
            <p class="eq-semboyan-teks">People<br>Safety<br>Productivity<br>A Better Tomorrow</p>
            <span class="eq-semboyan-garis" />
          </div>

          <div class="eq-sisi-bawah">
            <small>&copy; {{ new Date().getFullYear() }} EQOHSEE</small>
            <button type="button" class="eq-lipat" aria-label="Lipat bilah samping" @click="lipat">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M13 7l-5 5 5 5M18 7l-5 5 5 5"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- ═══════════ ISI ═══════════ -->
    <div class="flex-1 flex flex-col min-w-0">
      <header class="eq-topbar">
        <button class="eq-menu-btn" aria-label="Buka menu" @click="lacisTerbuka = !lacisTerbuka">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>

        <!-- ── Sapaan, bukan judul halaman ──

             Judul halaman TIDAK lagi dicetak di sini. Tiap halaman
             sudah mencetak judulnya sendiri di badan halaman, dan
             keduanya bersebelahan membuat kalimat yang sama muncul dua
             kali dengan jarak dua sentimeter — terlihat pada
             /miners/dasbor: "Miners — Ringkasan" tercetak di kepala
             halaman dan langsung diulang di bawahnya.

             NAMANYA PINDAH KE CHIP AKUN di ujung kanan, tempat ia
             berdampingan dengan jabatannya. Dibiarkan di kedua ujung,
             nama yang sama tercetak dua kali pada satu baris pandang —
             persis alasan ia dulu dikeluarkan dari chip. -->
        <div class="eq-sapa min-w-0">
          <small>{{ hariIni }}</small>
          <!-- &nbsp;: di ponsel sapaannya boleh turun baris, tetapi
               lambaian tangan tidak boleh tertinggal sendirian di baris
               kedua. -->
          <strong>{{ sapaan }}&nbsp;<span aria-hidden="true">&#128075;</span></strong>
        </div>

        <!-- Kotak cari menuju daftar pekerja: nama, NIK, jabatan.
             Hanya itu yang benar-benar dapat dicari hari ini, dan
             menjanjikan lebih dari itu berarti kotak yang diam. -->
        <form class="eq-cari" @submit.prevent="kirimCari">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>
          </svg>
          <input ref="kotakCari" v-model="cari" type="search" aria-label="Cari pekerja"
                 placeholder="Cari pekerja menurut nama, NIK, atau jabatan…">

          <!-- Lencana "Ctrl K" DIBUANG dari kotak ini. Pintasannya tetap
               bekerja — lihat pemasangan pendengar papan tik di atas —
               yang hilang hanya petunjuknya, yang pada kotak selebar ini
               lebih banyak mengambil ruang teks pencarian daripada
               menolong. -->
        </form>

        <div class="eq-topbar-aksi">
          <button type="button" class="eq-bulat eq-tema-btn" title="Tema terang / gelap"
                  aria-label="Ganti tema terang atau gelap" @click="gantiTema">
            <svg class="eq-ikon-terang" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
              <circle cx="12" cy="12" r="4.2"/>
              <path d="M12 2.5v2.2M12 19.3v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/>
            </svg>
            <svg class="eq-ikon-gelap" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M20.5 14.3A8.6 8.6 0 0 1 9.7 3.5a8.6 8.6 0 1 0 10.8 10.8Z"/>
            </svg>
          </button>

          <div class="eq-lonceng">
            <a href="/news" class="eq-bulat" aria-label="Pengumuman">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 3.2a5.3 5.3 0 0 0-5.3 5.3v3.7l-1.9 3.1h14.4l-1.9-3.1V8.5A5.3 5.3 0 0 0 12 3.2Z"/>
                <path d="M9.9 18.4a2.2 2.2 0 0 0 4.2 0"/>
              </svg>
              <span v-if="pengumuman > 0" class="eq-lonceng-titik">
                {{ pengumuman > 9 ? '9+' : pengumuman }}
              </span>
            </a>
          </div>

          <!-- Perusahaan yang sedang dilihat.

               PEMILIH HANYA BAGI YANG BENAR-BENAR PUNYA PILIHAN.
               Administrator menjangkau seluruh perusahaan dan dapat
               menyempitkannya ke salah satu; pengguna biasa terikat
               satu perusahaan dan mendapat label, bukan tombol —
               tombol yang dapat ditekan tetapi tidak mengubah apa pun
               lebih membingungkan daripada tulisan.

               Yang kosong berbunyi "Semua perusahaan", bukan dibiarkan
               kosong: kotak kosong terbaca sebagai data yang hilang. -->
          <div v-if="pengguna && pilihanPerusahaan.length > 1" class="eq-perusahaan-pilih"
               :class="{ 'eq-perusahaan-buka': perusahaanTerbuka }">
            <button type="button" class="eq-perusahaan eq-perusahaan-tombol"
                    :aria-expanded="perusahaanTerbuka" aria-haspopup="listbox"
                    @click="perusahaanTerbuka = !perusahaanTerbuka">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 20V7.5L12 4l8 3.5V20M9 20v-4.5h6V20M8 10.5h.01M12 10.5h.01M16 10.5h.01"/>
              </svg>
              <span>{{ namaPerusahaan }}</span>
              <svg class="eq-perusahaan-panah" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m6 9.5 6 6 6-6"/>
              </svg>
            </button>

            <div v-if="perusahaanTerbuka" class="eq-perusahaan-tirai" @click="perusahaanTerbuka = false" />

            <ul v-if="perusahaanTerbuka" class="eq-perusahaan-daftar" role="listbox">
              <li>
                <button type="button" class="eq-perusahaan-butir"
                        :class="{ 'eq-perusahaan-kini': !pengguna.perusahaanDilihat }"
                        role="option" :aria-selected="!pengguna.perusahaanDilihat"
                        @click="gantiPerusahaan(null)">
                  Semua perusahaan
                </button>
              </li>

              <li v-for="p in pilihanPerusahaan.filter((x) => x.id !== null)" :key="p.id ?? 'semua'">
                <button type="button" class="eq-perusahaan-butir"
                        :class="{ 'eq-perusahaan-kini': pengguna.perusahaanDilihat === p.id }"
                        role="option" :aria-selected="pengguna.perusahaanDilihat === p.id"
                        @click="gantiPerusahaan(p.id)">
                  {{ p.nama }}
                </button>
              </li>
            </ul>
          </div>

          <span v-else-if="pengguna" class="eq-perusahaan" :title="namaPerusahaan">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M4 20V7.5L12 4l8 3.5V20M9 20v-4.5h6V20M8 10.5h.01M12 10.5h.01M16 10.5h.01"/>
            </svg>
            <span>{{ namaPerusahaan }}</span>
          </span>

          <!-- ── Akun ──

               NAMA DAN JABATAN dicetak di samping lingkarannya. Sapaan
               di ujung kiri tidak lagi menyebut nama — ia menyebut
               tanggal — sehingga namanya hanya muncul sekali pada satu
               baris pandang.

               Teksnya disembunyikan pada layar sempit (lihat
               `.eq-akun-nama` di eq-visual.blade.php): jabatan yang
               membungkus menjadi dua baris menaikkan tinggi seluruh
               bilah atas, dan yang tersisa cuma lingkarannya — persis
               bentuk lama. -->
          <div v-if="pengguna" class="eq-akun" :class="{ 'eq-akun-buka': akunTerbuka }">
            <button type="button" class="eq-akun-tombol" :aria-expanded="akunTerbuka"
                    aria-haspopup="menu"
                    :aria-label="`Akun ${pengguna.nama}, ${pengguna.peran}`"
                    @click="akunTerbuka = !akunTerbuka">
              <span class="eq-akun-rupa">
                <img v-if="pengguna.avatar" class="eq-akun-avatar eq-akun-foto"
                     :src="pengguna.avatar" alt="" width="38" height="38">
                <span v-else class="eq-akun-avatar">{{ inisial }}</span>
                <span class="eq-akun-titik" aria-hidden="true" />
              </span>

              <!-- `aria-hidden` sebab nama dan jabatan yang sama sudah
                   dibacakan lewat aria-label tombolnya; dibiarkan
                   terbaca, pembaca layar menyebut namanya dua kali. -->
              <span class="eq-akun-nama" aria-hidden="true">
                <strong>{{ pengguna.nama }}</strong>
                <small>{{ pengguna.peran }}</small>
              </span>

              <svg class="eq-akun-panah" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                   aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
            </button>

            <div v-if="akunTerbuka" class="eq-perusahaan-tirai" @click="akunTerbuka = false" />

            <div v-if="akunTerbuka" class="eq-akun-panel" role="menu">
              <div class="eq-akun-kepala">
                <!-- Fotonya ikut di sini, bukan hanya pada tombol di luar.
                     Sebelumnya kepala menu ini SELALU menggambar inisial,
                     sehingga orang yang sudah memasang foto melihatnya di
                     tombol lalu kehilangannya begitu menu dibuka — tepat
                     di tempat yang paling menegaskan "ini akun siapa". -->
                <img v-if="pengguna.avatar" class="eq-akun-avatar eq-akun-avatar-besar eq-akun-foto"
                     :src="pengguna.avatar" alt="" width="46" height="46">
                <span v-else class="eq-akun-avatar eq-akun-avatar-besar">{{ inisial }}</span>
                <span class="min-w-0">
                  <strong>{{ pengguna.nama }}</strong>
                  <small>{{ pengguna.peran }}</small>
                </span>
              </div>

              <a href="/personalia" class="eq-akun-butir" role="menuitem">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M19 20v-1.6a5 5 0 0 0-5-5h-4a5 5 0 0 0-5 5V20"/>
                  <path d="M12 11.5a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/>
                </svg>
                Data diri
              </a>

              <button type="button" class="eq-akun-butir" role="menuitem" @click="bukaTur">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <circle cx="12" cy="12" r="9"/>
                  <path d="M9.6 9.4a2.5 2.5 0 1 1 3.3 2.4c-.6.2-.9.7-.9 1.3v.4"/>
                  <path d="M12 17h.01"/>
                </svg>
                Pengenalan fitur
              </button>

              <button type="button" class="eq-akun-butir eq-akun-keluar" role="menuitem"
                      @click="akunTerbuka = false; keluar()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M15 17l5-5-5-5M20 12H9M12 19H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6"/>
                </svg>
                Keluar
              </button>
            </div>
          </div>
        </div>
      </header>

      <!-- Kelas tema datang dari modulnya, bukan dari halamannya.
           Modul yang menyatakan `tema` di Menu::all() berganti palet
           seluruhnya — termasuk halaman yang belum ditulis. Dipasang di
           <main>, bukan di pembungkus terluar, supaya bilah samping dan
           kepala halaman tetap satu rupa di seluruh aplikasi: yang
           berganti isinya, bukan kerangkanya. -->
      <main class="flex-1 p-4 lg:p-6" :class="menu.tema ? `tema-${menu.tema}` : null">
        <div v-if="kilat.sukses" class="max-w-[1400px] mx-auto mb-5">
          <div class="rounded-xl px-4 py-3 text-[12.5px] font-semibold flex items-center gap-2.5"
               style="background:var(--eq-aksen-tipis,rgba(14,116,126,.12));color:var(--eq-aksen,#F57C00)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                 stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 shrink-0" aria-hidden="true">
              <path d="m5 12.5 4.5 4.5L19 7.5"/>
            </svg>
            {{ kilat.sukses }}
          </div>
        </div>

        <div class="max-w-[1400px] mx-auto space-y-4">
          <KopHalaman v-if="!kopSendiri" :judul="judul" :subjudul="subjudul ?? null"
                      :label="menu?.label ?? null" :tagline="menu?.semboyan ?? null"
                      :remah="remahKop"
                      :gambar="sampul?.gambar ?? null" :webp="sampul?.webp ?? null"
                      :keterangan="sampul?.keterangan ?? null"
                      :kondisi="sampul?.kondisi ?? null"
                      :angka="kopIsi.angka ?? null" :sisi="kopIsi.sisi ?? []"
                      :kanan="kopIsi.kanan ?? null" :kanan-kecil="kopIsi.kananKecil ?? null"
                      :aksi="kopIsi.aksi ?? null"
                      :ringkas="!kopIsi.angka" />

          <nav v-if="pindahCepat.length" class="eq-pindah" aria-label="Isi modul">
            <component :is="tautan(b.inertia)" v-for="b in pindahCepat" :key="b.url"
                       :href="b.url" class="eq-pindah-pil"
                       :class="{ 'eq-pindah-kini': b.aktif }"
                       :aria-current="b.aktif ? 'page' : undefined">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path :d="b.ikon" />
              </svg>
              <span>{{ b.label }}</span>
              <span v-if="b.lencana" class="eq-pindah-lencana">{{ b.lencana }}</span>
            </component>
          </nav>

          <slot />

          <!-- Kutipan penutup. Di bawah slot, bukan di dalamnya: ia
               menutup halaman, dan yang menutup halaman tidak boleh
               ikut berpindah tempat mengikuti isi tiap halaman. -->
          <KutipanKaki v-if="menu?.kutipan" :teks="menu.kutipan"
                       :kanan="menu?.semboyan ?? null" />
        </div>
      </main>
    </div>

    <!-- Pengenalan situs. Di sini, bukan di tiap halaman: yang menentukan
         munculnya adalah akunnya, bukan halaman mana yang kebetulan
         sedang dibuka — dan satu halaman yang lupa memasangnya berarti
         pengguna baru yang mendarat di sana tidak pernah disambut. -->
    <!-- Sampul halaman ikut diteruskan: rel pengenalan memakainya untuk
         mengisi rongga di bawah daftar langkahnya. Tidak ada muatan
         tambahan — prop `sampul` memang sudah dikirim halaman ini, dan
         pada halaman yang tidak membawanya rel-nya sekadar tanpa foto. -->
    <TurSelamatDatang :terbuka="turTerbuka" :bawaan="turBawaan"
                      :sampul="sampulHalaman"
                      @tutup="turTerbuka = false" />
  </div>
</template>
