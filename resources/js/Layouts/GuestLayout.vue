<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { propHalaman } from '../halaman';
import PilihBahasa from '../Components/Tamu/PilihBahasa.vue';
import { useBahasa } from '../tamu/bahasa';
import '../../css/masuk.css';

/**
 * Kerangka halaman tamu: masuk, daftar, lupa sandi, atur ulang sandi,
 * verifikasi email, dua faktor, dan konfirmasi sandi.
 *
 * ── RANCANGAN: KARTU MELAYANG ──
 *
 * Di layar lebar seluruh halaman adalah satu kartu kaca yang melayang di
 * atas latar lembut berpita dan berkontur, dengan tiga bintik jendela ala
 * macOS di pojoknya. Kiri: foto tambang resmi paket merek (unit dump
 * truck, matahari terbit, bukit reklamasi) bertirai Deep Teal dan
 * berkontur di sisi kosongnya. Kanan: panel kaca formulir. Di ponsel
 * kartunya menjadi selebar layar: foto di atas, kartu kaca formulir
 * menumpang di tepi bawahnya.
 *
 * Kaca kiri-bawah (statistik dan pilar) dibatasi sebelah kiri panel foto
 * supaya tidak pernah menutupi unit dump truck yang melintas di kanan-
 * bawah fotonya — posisi fotonya disetel bersama (masuk.css, .ms-foto).
 *
 * ── HANYA YANG BENAR ──
 *
 * Semua angka di panel dihitung server (App\Support\RingkasPlatform):
 * pengguna, pengunjung situs anonim, modul dan sebarannya per pilar,
 * serta aspek OHSE. Angka nol tidak ditampilkan. Tombol masuk
 * dengan Google/Microsoft hanya tergambar bila kredensialnya dipasang
 * (lihat Auth/Login.vue). Lencana Turnstile hanya tampil bila Turnstile
 * memang aktif, lencana koneksi terenkripsi hanya pada HTTPS.
 */
const prop = propHalaman();
const { bahasa, t, angka } = useBahasa();

/**
 * Pesan kilat pada halaman yang belum masuk.
 *
 * Sebelumnya bilah ini HANYA ada di AppLayout — yaitu di balik login.
 * Akibatnya setiap pesan yang ditujukan kepada orang yang belum masuk
 * hilang tanpa jejak, dan yang paling merugikan adalah pesan sesudah
 * verifikasi email berhasil: sesinya ditutup, orangnya dipulangkan ke
 * halaman masuk, dan halaman itu menyambutnya persis seperti kalau
 * kodenya salah.
 */
const kilat = computed<any>(() => prop.kilat ?? {});

type Ringkas = {
  modul: number; aspek: number; pengguna: number; pengunjung: number; tren: number[];
  modulPerPilar: { kunci: string; nama: string; jumlah: number }[];
};
const ringkas = computed<Ringkas | null>(() => prop.ringkasPlatform ?? null);

/* ── Angka yang menghitung naik saat halaman dibuka ──
   Sekali, dalam ±1,2 detik, berhenti tepat di nilai sebenarnya. Bagi
   yang meminta gerakan dikurangi, angkanya langsung tampil. */
const kemajuan = ref(0);
let bingkai = 0;
function hitungNaik(): void {
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) { kemajuan.value = 1; return; }
  const mulai = performance.now();
  const langkah = (kini: number) => {
    const p = Math.min(1, (kini - mulai) / 1200);
    kemajuan.value = 1 - Math.pow(1 - p, 3);
    if (p < 1) bingkai = requestAnimationFrame(langkah);
  };
  bingkai = requestAnimationFrame(langkah);
}
const tampil = (n: number): string => angka(Math.round(n * kemajuan.value));

/**
 * Butir statistik. Angka nol tidak ditampilkan — "0 pengunjung" di pintu
 * masuk terbaca sebagai situs yang mati, bukan sebagai fakta. Dalam
 * praktiknya keduanya tidak pernah nol: pengunjung dicatat sebelum
 * halamannya digambar (CatatPengunjung), dan setiap pemasangan punya admin.
 */
const butirStat = computed(() => {
  const r = ringkas.value;
  if (!r) return [];
  return [
    { kunci: 'pengguna', nilai: r.pengguna, label: t('stat.pengguna') },
    { kunci: 'pengunjung', nilai: r.pengunjung, label: t('stat.pengunjung') },
    { kunci: 'modul', nilai: r.modul, label: t('stat.modul') },
    { kunci: 'aspek', nilai: r.aspek, label: t('stat.aspek') },
  ].filter((b) => b.nilai > 0);
});

/** Pengunjung hari ini — batang terakhir grafik mini. */
const hariIni = computed(() => {
  const tren = ringkas.value?.tren ?? [];
  return tren.length ? tren[tren.length - 1] : 0;
});

/**
 * Pita warna di bawah angka modul: sebaran modul per pilar utamanya,
 * selebar banyaknya modul. Warnanya satu keluarga dengan panel foto —
 * hijau zaitun, teal, pasir, langit, kuning tembaga — bukan warna pilar
 * lama di registri yang berulang tiga warna untuk delapan pilar.
 */
const WARNA_PILAR: Record<string, string> = {
  engineering: '#7FD1B9', environment: '#3FB58F', safety: '#C6E38A', occhealth: '#E9C46A',
  quality: '#8EC9E8', energy: '#F4A259', hygiene: '#C9B6F2', konservasi: '#A3B18A',
};
const modulPilar = computed(() => (ringkas.value?.modulPerPilar ?? [])
  .map((m) => ({ ...m, warna: WARNA_PILAR[m.kunci] ?? '#DDE7E2' })));

/* Grafik mini pengunjung tujuh hari: batang setinggi relatif terhadap
   hari tertinggi; hari tanpa pengunjung tetap tampak sebagai garis dasar. */
const batangTren = computed(() => {
  const tren = ringkas.value?.tren ?? [];
  const puncak = Math.max(1, ...tren);
  return tren.map((n, i) => ({ x: i * 9, tinggi: Math.max(2, Math.round((n / puncak) * 18)) }));
});

const sekarang = ref(new Date());
let detak: number | undefined;
/* Diperbarui tiap 15 detik: menitnya tidak pernah tertinggal lebih dari
   seperempat menit, tanpa membangunkan halaman tiap detik. Tata letak
   ini tetap terpasang saat berpindah antarhalaman tamu, jadi pengatur
   waktunya dilepas hanya saat keluar dari halaman tamu. */
onMounted(() => {
  detak = window.setInterval(() => { sekarang.value = new Date(); }, 15000);
  hitungNaik();
});
onBeforeUnmount(() => {
  if (detak !== undefined) window.clearInterval(detak);
  cancelAnimationFrame(bingkai);
});

const lokal = computed(() => (bahasa.value === 'en' ? 'en-GB' : 'id-ID'));
const jam = computed(() => new Intl.DateTimeFormat(lokal.value, { hour: '2-digit', minute: '2-digit' }).format(sekarang.value));
const tanggal = computed(() => new Intl.DateTimeFormat(lokal.value, { weekday: 'short', day: 'numeric', month: 'short' }).format(sekarang.value));

/* Lencana keamanan hanya untuk yang benar-benar berlaku di halaman ini. */
const terenkripsi = ref(false);
onMounted(() => { terenkripsi.value = window.location.protocol === 'https:'; });
const adaTurnstile = computed(() => Boolean(prop.turnstile));

/**
 * Garis kontur topografi untuk sisi kosong panel foto — medan ketinggian
 * yang sama dengan motif lembar formulir (masuk.css), dipecah per garis
 * supaya tiap garis dapat "digambar" bergiliran saat halaman dibuka.
 * `indeks` menandai garis tiap kelipatan kelima yang lebih tebal.
 */
const KONTUR: { d: string; indeks: boolean }[] = [
  { indeks: false, d: 'M0 778Q25 760 42 755Q58 750 75 751Q92 752 108 759Q124 767 139 779Q153 792 165 808Q176 825 185 846Q193 867 195 883Q197 900 195 912Q193 925 187 938Q180 950 171 958Q161 967 147 973Q133 979 117 982Q100 985 83 984Q67 984 48 979Q29 975 15 968L0 961' },
  { indeks: false, d: 'M0 719Q50 700 75 702Q100 703 124 714Q148 725 170 745Q192 765 214 799Q236 833 248 867Q260 900 261 917Q263 933 262 946Q260 958 250 979L239 1000' },
  { indeks: false, d: 'M0 676Q58 659 83 661Q108 663 133 674Q158 686 182 705Q205 725 232 759Q258 792 281 834Q303 875 312 908Q321 942 319 971L316 1000' },
  { indeks: false, d: 'M0 638Q50 624 79 625Q108 627 137 638Q166 650 191 670Q217 690 252 732Q288 775 317 821Q345 867 358 900Q370 933 372 967L373 1000' },
  { indeks: false, d: 'M0 570Q67 559 96 562Q125 565 154 576Q183 588 212 609Q242 631 296 690Q349 750 387 800Q424 850 440 888Q456 925 461 962L465 1000' },
  { indeks: false, d: 'M0 538Q75 529 108 534Q142 538 171 551Q200 563 229 585Q258 607 332 687Q406 767 434 804Q463 842 479 879Q496 917 500 938Q504 958 506 979L507 1000' },
  { indeks: false, d: 'M0 507Q75 500 112 505Q150 510 183 524Q217 538 246 561Q275 583 359 671Q444 758 471 792Q498 825 509 846Q521 867 528 888Q536 908 540 929Q544 950 546 975L547 1000' },
  { indeks: false, d: 'M0 476Q83 472 121 478Q158 483 192 496Q225 509 254 530Q283 551 384 651Q485 750 510 779Q535 808 547 829Q559 850 566 871Q574 892 580 917Q585 942 587 971L589 1000' },
  { indeks: false, d: 'M0 413Q117 419 157 426Q198 433 228 445Q258 457 288 478Q317 498 379 557Q442 617 508 669Q575 722 596 744Q617 767 630 788Q643 808 653 833Q664 858 671 888Q678 917 683 958L689 1000' },
  { indeks: false, d: 'M0 380Q183 399 208 405Q233 411 254 420Q275 428 304 448Q333 468 400 529Q467 591 546 645Q625 700 650 725Q674 750 692 779Q709 808 720 838Q731 867 749 933L766 1000' },
  { indeks: false, d: 'M0 345Q58 356 142 366Q225 375 250 382Q275 389 295 399Q314 408 336 425Q358 441 400 481Q442 521 467 541Q492 561 571 606Q650 652 678 672Q706 692 731 717Q756 742 794 791Q833 840 850 852Q867 865 879 868Q892 871 904 870Q917 869 958 852L1000 835' },
  { indeks: false, d: 'M0 306Q67 325 154 334Q242 342 275 351Q308 359 338 377Q367 394 417 442Q467 490 492 509Q517 528 546 544Q575 560 646 593Q716 625 779 662Q842 700 858 707Q875 714 896 718Q917 723 958 719L1000 715' },
  { indeks: false, d: 'M0 76Q17 36 29 18L42 0' },
  { indeks: false, d: 'M0 180Q15 208 27 221Q40 233 54 242Q69 250 89 256Q108 261 150 263Q192 264 258 255Q325 245 346 249Q367 253 388 270Q408 286 458 347Q508 408 528 425Q548 442 568 454Q589 467 623 482Q658 496 767 534Q875 571 908 578Q942 585 971 586L1000 587' },
  { indeks: false, d: 'M129 0Q92 24 77 41Q63 58 56 75Q48 92 46 112Q44 133 48 150Q53 167 64 181Q75 195 92 204Q108 213 129 216Q150 219 171 217Q192 215 213 207Q233 200 252 188Q270 175 282 158Q293 142 297 125Q300 108 296 88Q291 67 279 47Q267 27 250 13L233 0' },
  { indeks: false, d: 'M481 0Q448 108 446 129Q444 150 445 167Q446 183 452 208Q459 233 469 258Q479 283 493 308Q508 333 524 354Q541 375 573 400Q606 425 649 444Q692 463 792 495Q892 526 921 532Q950 537 975 538L1000 539' },
  { indeks: false, d: 'M533 0Q509 108 509 150Q508 192 512 212Q516 233 524 254Q531 275 540 293Q550 311 562 327Q575 344 605 368Q635 392 680 412Q725 432 808 457Q892 483 921 488Q950 493 975 495L1000 496' },
  { indeks: false, d: 'M572 0Q553 100 553 142Q553 183 563 221Q573 258 592 288Q610 317 639 339Q667 362 712 383Q758 403 829 424Q900 445 950 450L1000 456' },
  { indeks: false, d: 'M641 0Q626 75 626 112Q626 150 635 183Q644 217 660 242Q677 267 703 288Q729 308 769 326Q808 344 862 358Q917 372 958 375L1000 378' },
  { indeks: false, d: 'M675 0Q661 67 661 100Q660 133 668 162Q676 192 692 217Q709 242 733 261Q758 281 796 298Q833 314 879 325Q925 336 962 337L1000 338' },
  { indeks: false, d: 'M711 0Q696 67 697 96Q697 125 705 150Q713 175 727 196Q742 217 767 236Q792 254 825 268Q858 282 896 289Q933 297 967 296L1000 296' },
  { indeks: false, d: 'M752 0Q736 58 737 83Q737 108 744 129Q750 150 765 171Q780 192 802 208Q825 223 854 234Q883 245 912 249Q942 253 971 249L1000 246' },
  { indeks: true, d: 'M0 603Q58 590 88 593Q117 595 146 606Q175 618 202 638Q228 658 269 704Q310 750 347 804Q385 858 400 896Q415 933 419 967L422 1000' },
  { indeks: true, d: 'M0 445Q100 445 138 451Q175 457 208 471Q242 484 271 505Q300 525 358 582Q417 639 475 691Q533 742 554 767Q576 792 588 812Q600 833 609 858Q619 883 624 912Q630 942 632 971L635 1000' },
  { indeks: true, d: 'M0 258Q42 282 67 289Q92 296 121 299Q150 302 217 304Q283 305 308 310Q333 316 346 322Q358 328 373 339Q388 350 447 408Q506 467 540 489Q575 510 725 571Q875 631 904 637Q933 643 967 643L1000 643' },
  { indeks: true, d: 'M608 0Q590 92 590 129Q590 167 599 200Q608 233 626 262Q645 292 672 314Q700 336 742 355Q783 373 846 391Q908 408 954 413L1000 417' },
  { indeks: true, d: 'M807 0Q789 42 787 62Q785 83 791 104Q797 125 810 142Q822 158 842 171Q862 183 885 190Q908 196 933 195Q958 195 979 187L1000 178' },
];

/** Tujuh pilar merek EQOHSEE ditambah Konservasi — disorot bergiliran.
    Tujuh nama pilar merek tetap berbahasa Inggris di kedua bahasa; hanya
    Konservasi (Minerba) yang diterjemahkan. */
const PILAR = computed(() => ['Energy', 'Quality', 'OHS', 'Hygiene', 'Safety', 'Environment', 'Engineering', t('pilar.konservasi')]);
</script>

<template>
  <div class="ms min-h-screen min-h-[100dvh]">
    <div class="ms-bingkai grid grid-rows-[auto_1fr] lg:grid-rows-1 lg:grid-cols-[minmax(0,1.16fr)_minmax(0,.84fr)]">
      <!-- Tiga bintik jendela ala macOS — aksesori, bukan tombol. -->
      <span class="ms-jendela" aria-hidden="true"><i></i><i></i><i></i></span>

      <!--
        Ponsel: panelnya menjadi foto setinggi sepertiga layar dengan kartu
        formulir yang menumpang di tepi bawahnya — formulirnya di jangkauan
        ibu jari, bukan di bawah satu layar penuh kalimat pembuka.
      -->
      <section class="pendar-rekaman ms-panel text-white px-5 pt-6 pb-28 sm:px-10 sm:pt-9 sm:pb-12 lg:px-14 lg:pt-16 lg:pb-11 flex flex-col justify-between gap-8 min-h-[340px] sm:min-h-[420px]">
        <!-- 2080w diperbesar dengan model super-resolusi dari 1040w aslinya:
             di laptop 150% dan layar retina panel ini butuh ±1.500–2.000
             piksel perangkat, dan 1040 px yang direntang peramban kabur. -->
        <img src="/brand/masuk-tambang.webp" alt="" aria-hidden="true"
             srcset="/brand/masuk-tambang.webp 1040w, /brand/masuk-tambang-2x.webp 2080w"
             sizes="(min-width: 1024px) min(1000px, 56vw), 100vw"
             width="1040" height="1160" decoding="async" fetchpriority="high"
             class="ms-foto absolute inset-0 w-full h-full object-cover">
        <div class="ms-tirai absolute inset-0" aria-hidden="true"></div>
        <!-- Kontur topografi di sisi kosong panel (bidang gelap di kiri-atas
             foto). Garisnya tergambar bergiliran saat halaman dibuka, lalu
             memudar ke kanan sebelum mengenai foto truk — tidak pernah
             bersaing dengan gambarnya (masuk.css: .ms-panel-kontur). Tanpa
             label teks: posisinya mengikuti skala gambar, bukan tata letak
             tulisan, dan label yang menabrak judul terbaca sebagai tulisan
             nyasar. -->
        <svg class="ms-panel-kontur absolute inset-0 w-full h-full" viewBox="0 0 1000 1000"
             preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
          <path v-for="(k, i) in KONTUR" :key="i" :d="k.d" pathLength="1"
                :class="k.indeks ? 'ms-kontur-indeks' : 'ms-kontur-garis'"
                :style="{ animationDelay: `${i * 70}ms` }" />
        </svg>

        <div class="ms-panel-atas relative z-10 flex items-start justify-between gap-3">
          <Link href="/" class="ms-merek ms-kaca-gelap" :aria-label="t('merek.ke_depan')">
            <img src="/brand/eqohsee-icon-dark.svg" alt="" width="54" height="54" class="ms-merek-lambang">
            <span class="ms-merek-teks">
              <img src="/brand/eqohsee-wordmark-dark.svg" alt="" width="172" height="24" class="ms-merek-nama">
              <span class="ms-merek-sub">Integrated Mining OHSE Platform</span>
            </span>
          </Link>
          <!-- Ponsel & tablet — dan layar lebar yang pendek (masuk.css):
               pilihan bahasa di pojok foto. -->
          <PilihBahasa class="ms-bahasa-foto lg:hidden" />
        </div>

        <div class="relative z-10 max-w-[660px]">
          <span class="ms-garis" aria-hidden="true"></span>
          <!-- Tanda "&" dari Inter: ampersand Archivo berbentuk "Et" yang
               tidak terbaca sebagai "dan" pada ukuran judul. -->
          <h1 class="ms-h1">Integrated Mining OHSE <span class="ms-amp">&amp;</span> Compliance <em>Platform</em></h1>
          <p class="ms-hero-sub hidden sm:block">{{ t('hero.sub') }}</p>
        </div>

        <!-- Kaca kiri-bawah: ringkasan platform dan pilar merek. -->
        <div class="ms-kaca-kiri relative z-10 hidden sm:grid">
          <div v-if="ringkas" class="ms-statistik ms-kaca-gelap" role="group" :aria-label="t('stat.ringkas')">
            <div class="ms-statistik-atas">
              <span class="ms-widget-label"><i class="ms-titik" aria-hidden="true"></i>{{ t('stat.sistem_aktif') }}</span>
              <span class="ms-statistik-jam"><b>{{ jam }}</b><span class="ms-statistik-tgl"> · {{ tanggal }}</span></span>
            </div>
            <div class="ms-statistik-isi">
              <div v-for="s in butirStat" :key="s.kunci" class="ms-stat">
                <b class="ms-stat-angka">{{ tampil(s.nilai) }}</b>
                <span class="ms-stat-ket">{{ s.label }}</span>
                <!-- Grafik mini tujuh hari; batang hari ini dan tanda "+n"
                     di sebelahnya sewarna, jadi keduanya terbaca satu. -->
                <span v-if="s.kunci === 'pengunjung'" class="ms-stat-grafik">
                  <svg class="ms-stat-tren" viewBox="0 0 61 20" role="img"
                       :aria-label="t('stat.tren', { daftar: (ringkas?.tren ?? []).join(', ') })">
                    <rect v-for="(b, i) in batangTren" :key="i" :x="b.x" :y="20 - b.tinggi" width="6" :height="b.tinggi" rx="1.5"
                          :class="i === batangTren.length - 1 ? 'kini' : ''" />
                  </svg>
                  <span v-if="hariIni > 0" class="ms-stat-naik" :title="`+${angka(hariIni)} ${t('stat.hari_ini')}`">
                    +{{ angka(hariIni) }}<span class="sr-only"> {{ t('stat.hari_ini') }}</span>
                  </span>
                </span>
                <span v-else-if="s.kunci === 'modul' && modulPilar.length" class="ms-stat-pita" role="img"
                      :aria-label="t('stat.modul_pilar', { daftar: modulPilar.map((m) => `${m.nama} ${m.jumlah}`).join(', ') })">
                  <i v-for="m in modulPilar" :key="m.kunci" :title="`${m.nama} · ${m.jumlah}`"
                     :style="{ flexGrow: m.jumlah, backgroundColor: m.warna }"></i>
                </span>
              </div>
            </div>
          </div>

          <!-- Pilar merek dalam kapsul kaca. Sorotannya berjalan bergantian
               (masuk.css), bukan menetap pada satu kata — sorotan tetap
               terbaca sebagai animasi yang macet. -->
          <div class="ms-pilar ms-kaca-gelap">
            <div class="ms-huruf grid" role="img" :aria-label="t('pilar.label', { daftar: PILAR.join(', ') })">
              <b v-for="(p, i) in PILAR" :key="i" aria-hidden="true">{{ p }}</b>
            </div>
          </div>
        </div>
      </section>

      <section class="ms-lembar relative z-10 flex items-start sm:items-center justify-center px-4 pb-10 sm:px-10 sm:py-12 lg:px-10 xl:px-14 lg:pt-16 lg:pb-8">
        <!-- Layar lebar: pilihan bahasa di pojok panel formulir. -->
        <PilihBahasa class="ms-bahasa-panel hidden lg:block" />

        <div class="ms-kartu relative z-10 w-full max-w-[460px] lg:max-w-[420px]">
          <!-- role=status, bukan alert: kabarnya baik, dan alert merebut
               pembacaan di tengah orang membaca judul halamannya. -->
          <div v-if="kilat.sukses" role="status"
               class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3
                      flex items-start gap-2.5 text-emerald-800">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                 stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mt-0.5 shrink-0"
                 aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
            <p class="text-[13px] font-semibold leading-relaxed">{{ kilat.sukses }}</p>
          </div>

          <div v-if="kilat.galat" role="alert"
               class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700">
            <p class="text-[13px] font-semibold leading-relaxed">{{ kilat.galat }}</p>
          </div>

          <slot />

          <!-- Lencana keamanan: hanya yang benar-benar berlaku di halaman ini. -->
          <p v-if="terenkripsi || adaTurnstile" class="ms-aman">
            <span v-if="terenkripsi">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="10.5" width="14" height="10" rx="2.4" /><path d="M8.5 10.5V7.8a3.5 3.5 0 0 1 7 0v2.7" /></svg>
              {{ t('aman.koneksi') }}
            </span>
            <span v-if="adaTurnstile">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 5 5.8v5.5c0 4.4 2.9 8.2 7 9.5 4.1-1.3 7-5.1 7-9.5V5.8Z" /><path d="m9 12.2 2.1 2.1 4-4.3" /></svg>
              {{ t('aman.turnstile') }}
            </span>
          </p>

          <p class="ms-kaki">
            <Link href="/">{{ t('kaki.depan') }}</Link>
            <a :href="bahasa === 'en' ? '/privacy-policy' : '/kebijakan-privasi'">{{ t('kaki.privasi') }}</a>
          </p>
        </div>
      </section>
    </div>
  </div>
</template>
