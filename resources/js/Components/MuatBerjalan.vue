<script setup lang="ts">
/**
 * Penanda "sedang memuat" untuk seluruh perpindahan dan muat ulang.
 *
 * ── Kenapa tidak cukup bilah bawaan Inertia ──
 *
 * Inertia sudah menggambar bilah tipis di puncak halaman, dan pada
 * perpindahan halaman penuh itu memang terlihat. Yang tidak terlihat
 * adalah MUAT ULANG SEBAGIAN — dan justru itu yang paling banyak
 * dipakai situs ini: pemilih perusahaan di bilah atas, saringan tanggal
 * di dua belas modul, kotak cari, pemilih tahun. Semuanya memanggil
 * router dengan `preserveState`, sehingga halamannya tidak berkedip
 * sama sekali: angka lama tetap terpampang, lalu berganti begitu saja
 * beberapa detik kemudian.
 *
 * Yang dilihat pemakainya adalah situs yang tidak bereaksi. Maka ia
 * menekannya lagi. Di site tambang dengan sambungan yang lambat, itu
 * berarti empat permintaan untuk satu pertanyaan — dan yang terakhir
 * menang, belum tentu yang dikehendakinya.
 *
 * ── Tiga hal yang membuatnya tidak mengganggu ──
 *
 * 1. TERTUNDA. Tidak digambar sebelum 280 md. Permintaan yang selesai
 *    lebih cepat dari itu tidak pernah memunculkan apa pun; bilah yang
 *    berkedip pada tiap klik lebih berisik daripada tidak ada bilah.
 *
 * 2. BERTAHAN SEBENTAR. Begitu tergambar, ia bertahan sekurangnya
 *    320 md. Tanpa itu, permintaan yang selesai pada 300 md membuat
 *    bilahnya muncul dan hilang dalam 20 md — kilatan yang terbaca
 *    sebagai kerusakan, bukan sebagai kabar.
 *
 * 3. TIDAK PERNAH TERSANGKUT. `finish` menyala untuk SEMUA akhir,
 *    termasuk yang dibatalkan dan yang disela permintaan berikutnya.
 *    Yang hanya mendengarkan `success` meninggalkan bilah berjalan
 *    selamanya pada permintaan yang gagal — penanda memuat yang
 *    berbohong lebih buruk daripada tidak ada.
 *
 * ── Unggahan berkas dapat persentase sungguhan ──
 *
 * Untuk perpindahan biasa tidak ada persentase yang jujur: yang
 * ditunggu adalah server berpikir, dan tidak ada yang tahu berapa
 * lama. Bilahnya karena itu bergerak tanpa akhir yang dijanjikan.
 *
 * Unggahan berkas lain: Inertia memberi `progress.percentage`
 * sungguhan dari XHR-nya. Foto laporan bahaya dari site berukuran
 * beberapa megabita di sambungan yang lambat — di situlah angka
 * sungguhan paling berharga, dan di situ pula ia tersedia.
 */
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

/** Jeda sebelum digambar. Di bawah ini, tidak ada yang muncul. */
const JEDA = 280;

/** Bertahan sekurangnya ini begitu tergambar. */
const TAHAN = 320;

const tampil  = ref(false);
const persen  = ref<number | null>(null);
const selesai = ref(false);

let pewaktuTampil: ReturnType<typeof setTimeout> | null = null;
let pewaktuTahan: ReturnType<typeof setTimeout> | null = null;
let pewaktuBersih: ReturnType<typeof setTimeout> | null = null;
let tampilSejak = 0;

const lepas: Array<() => void> = [];

function batal(p: ReturnType<typeof setTimeout> | null) {
  if (p) clearTimeout(p);
  return null;
}

function mulai(): void {
  /* Permintaan baru saat yang lama masih berjalan: pewaktu penyembunyian
     dibatalkan, bilahnya diteruskan. Menyembunyikan lalu menggambar lagi
     membuat dua permintaan beruntun terlihat seperti satu yang gagal. */
  pewaktuTahan  = batal(pewaktuTahan);
  pewaktuBersih = batal(pewaktuBersih);
  selesai.value = false;
  persen.value  = null;

  if (tampil.value || pewaktuTampil) return;

  pewaktuTampil = setTimeout(() => {
    pewaktuTampil = null;
    tampil.value  = true;
    tampilSejak   = Date.now();
  }, JEDA);
}

function kemajuan(e: any): void {
  const p = e?.detail?.progress?.percentage;

  /* Hanya unggahan berkas yang punya angka jujur. Selain itu biarkan
     null supaya bilahnya tetap bergerak tanpa akhir yang dijanjikan. */
  if (typeof p === 'number' && p > 0 && p < 100) persen.value = Math.round(p);
}

function usai(): void {
  /* Belum sempat tergambar — batalkan saja, jangan digambar sekarang
     justru pada saat pekerjaannya sudah selesai. */
  if (pewaktuTampil) {
    pewaktuTampil = batal(pewaktuTampil);
    tampil.value  = false;
    return;
  }

  if (!tampil.value) return;

  const sisa = Math.max(0, TAHAN - (Date.now() - tampilSejak));

  /* Ditutup dengan penuh dulu, baru menghilang. Bilah yang lenyap di
     tengah jalan terbaca sebagai permintaan yang putus. */
  selesai.value = true;

  pewaktuTahan = setTimeout(() => {
    pewaktuTahan  = null;
    tampil.value  = false;
    persen.value  = null;

    pewaktuBersih = setTimeout(() => {
      pewaktuBersih = null;
      selesai.value = false;
    }, 200);
  }, sisa + 160);
}

onMounted(() => {
  lepas.push(router.on('start', mulai));
  lepas.push(router.on('progress', kemajuan));

  /* `finish`, bukan `success`: ia menyala juga untuk permintaan yang
     gagal, dibatalkan, atau disela — dan itulah justru saat bilahnya
     paling mungkin tertinggal berjalan selamanya. */
  lepas.push(router.on('finish', usai));
});

onBeforeUnmount(() => {
  lepas.forEach((f) => f());
  pewaktuTampil = batal(pewaktuTampil);
  pewaktuTahan  = batal(pewaktuTahan);
  pewaktuBersih = batal(pewaktuBersih);
});
</script>

<template>
  <!-- Bilah puncak. aria-hidden: yang perlu dibacakan pembaca layar
       adalah pesannya di bawah, bukan grafiknya. -->
  <div v-if="tampil || selesai" class="eq-muat-bilah" aria-hidden="true">
    <div class="eq-muat-laju"
         :class="{ 'is-tentu': persen !== null, 'is-usai': selesai }"
         :style="persen !== null ? { width: persen + '%' } : undefined" />
  </div>

  <!-- Keping kabar. role=status supaya dibacakan tanpa merebut fokus. -->
  <Transition name="eq-muat-keping">
    <div v-if="tampil" class="eq-muat-keping" role="status" aria-live="polite">
      <span class="eq-muat-putar" aria-hidden="true" />
      <span class="eq-muat-teks">
        {{ persen !== null ? `Mengunggah ${persen}%` : 'Memuat…' }}
      </span>
    </div>
  </Transition>
</template>

<style scoped>
/* ── bilah puncak ── */
.eq-muat-bilah {
  position: fixed;
  inset: 0 0 auto;
  z-index: 90;
  height: 3px;
  background: rgb(245 124 0 / .14);
  pointer-events: none;
}

.eq-muat-laju {
  height: 100%;
  width: 45%;
  border-radius: 0 99px 99px 0;
  background: linear-gradient(90deg, #F57C00, #FF9800 55%, #FFC46B);
  box-shadow: 0 0 10px rgb(255 152 0 / .75);
  animation: eq-muat-geser 1.15s cubic-bezier(.4, 0, .2, 1) infinite;
}

/* Ada persentase sungguhan: berhenti bergoyang dan tumbuh apa adanya. */
.eq-muat-laju.is-tentu {
  animation: none;
  transition: width .18s ease-out;
}

/* Ditutup penuh sebelum menghilang. */
.eq-muat-laju.is-usai {
  animation: none;
  width: 100% !important;
  transition: width .22s ease-out, opacity .2s ease-in .18s;
  opacity: 0;
}

@keyframes eq-muat-geser {
  0%   { transform: translateX(-100%); }
  100% { transform: translateX(240%); }
}

/* ── keping kabar ── */
/* DI POJOK KANAN BAWAH, bukan di tengah atas.
 *
 * Di tengah atas ia duduk persis di atas kotak cari bilah atas —
 * menutupi kendali yang sedang dipakai orangnya, pada saat yang justru
 * paling mungkin ia hendak mengetik lagi. Penanda yang menghalangi
 * pekerjaan yang sedang ditunggunya adalah penanda yang salah tempat.
 *
 * Pojok kanan bawah tidak pernah ditempati kendali apa pun di situs
 * ini, terlihat tanpa perlu dicari, dan `pointer-events: none`
 * memastikan ia tidak menghalangi klik pada apa pun di bawahnya. */
.eq-muat-keping {
  position: fixed;
  z-index: 90;
  right: 1.1rem;
  bottom: 1.1rem;
  display: flex;
  align-items: center;
  gap: .5rem;
  padding: .42rem .8rem .42rem .62rem;
  border-radius: 99px;
  border: 1px solid rgb(245 124 0 / .28);
  background: rgb(255 255 255 / .94);
  backdrop-filter: blur(6px);
  box-shadow: 0 6px 20px rgb(28 25 23 / .16);
  pointer-events: none;
}

.eq-muat-putar {
  flex: none;
  width: .85rem;
  height: .85rem;
  border-radius: 99px;
  border: 2px solid rgb(245 124 0 / .25);
  border-top-color: #F57C00;
  animation: eq-muat-putar .62s linear infinite;
}

@keyframes eq-muat-putar {
  to { transform: rotate(360deg); }
}

.eq-muat-teks {
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: .01em;
  color: #57534E;
  font-variant-numeric: tabular-nums;
}

.eq-muat-keping-enter-active { transition: opacity .18s ease-out, transform .18s ease-out; }
.eq-muat-keping-leave-active { transition: opacity .14s ease-in,  transform .14s ease-in; }

.eq-muat-keping-enter-from,
.eq-muat-keping-leave-to {
  opacity: 0;
  transform: translateY(.5rem) scale(.96);
}

/* ── mode gelap ── */
:global([data-tema='gelap']) .eq-muat-bilah { background: rgb(255 152 0 / .16); }

:global([data-tema='gelap']) .eq-muat-keping {
  background: rgb(20 32 47 / .94);
  border-color: rgb(255 152 0 / .3);
  box-shadow: 0 6px 20px rgb(0 0 0 / .45);
}

:global([data-tema='gelap']) .eq-muat-teks { color: #D6D3D1; }

/* ── yang meminta gerakan seminimal mungkin ──

   Bilahnya TIDAK disembunyikan, hanya berhenti bergerak. Menghapusnya
   sama sekali berarti orang yang paling terganggu gerakan justru
   kehilangan satu-satunya kabar bahwa situsnya sedang bekerja. */
@media (prefers-reduced-motion: reduce) {
  .eq-muat-laju,
  .eq-muat-putar {
    animation: none;
  }

  .eq-muat-laju { width: 100%; opacity: .75; }

  .eq-muat-keping-enter-active,
  .eq-muat-keping-leave-active { transition: opacity .1s linear; }

  .eq-muat-keping-enter-from,
  .eq-muat-keping-leave-to { transform: none; }
}
</style>
