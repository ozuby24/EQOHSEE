<script setup lang="ts">
/**
 * Kolom sandi dengan tombol intip.
 *
 * Sandi disembunyikan supaya tidak terbaca orang di belakang punggung.
 * Tetapi yang mengetiknya juga tidak dapat membacanya sendiri — dan di
 * lapangan itu bukan soal kecil: sandi diketik di ponsel, sering dengan
 * sarung tangan, kerap di bawah matahari yang membuat layar nyaris tidak
 * terlihat. Yang salah ketik tidak tahu di mana salahnya; yang ia tahu
 * hanya "sandi salah", lalu mencoba lagi dengan salah yang sama.
 *
 * Tombol intip mengembalikan pilihan itu kepada orangnya: ia yang tahu
 * apakah sedang ada orang di belakangnya, bukan aplikasinya.
 *
 * ── Empat hal yang mudah terlewat, dan semuanya pernah jadi cacat ──
 *
 * 1. `type="button"`. Tombol di dalam <form> tanpa type BAWAANNYA
 *    submit. Tanpa baris itu, menekan "lihat" mengirimkan formulirnya —
 *    pada halaman masuk berarti percobaan masuk dengan sandi setengah
 *    diketik, yang ikut menghabiskan jatah batas laju.
 *
 * 2. `autocapitalize`, `autocorrect`, `spellcheck`. Begitu type berubah
 *    menjadi `text`, papan ketik ponsel memperlakukannya sebagai tulisan
 *    biasa: huruf pertama dibesarkan dan kata yang tidak dikenalnya
 *    diperbaiki. Sandinya berubah tanpa pemiliknya menyadari, dan
 *    gejalanya persis sama dengan salah ketik biasa.
 *
 * 3. `@mousedown.prevent` pada tombolnya. Tanpa itu, menekan tombol
 *    memindahkan fokus keluar dari kolom — sehingga aturan "sembunyikan
 *    lagi saat kolomnya ditinggalkan" di bawah ikut menyala, lalu klik
 *    itu sendiri menyalakannya kembali. Hasilnya sandi yang tidak
 *    pernah bisa disembunyikan lagi. Ditahan di sini sekalian menjaga
 *    kursor tetap di tempat yang sedang diketik.
 *
 * 4. Tombolnya TETAP dapat dicapai Tab. Sempat ditulis `tabindex="-1"`
 *    agar urutan Tab pada formulir sandi langsung menuju tombol kirim —
 *    dan itu berarti yang tidak memakai tetikus tidak dapat mengintip
 *    sandinya sama sekali. Tepat orang yang paling terbantu oleh fitur
 *    ini yang kehilangan aksesnya.
 */
import { computed, ref } from 'vue';

const model = defineModel<string>({ default: '' });

const props = withDefaults(defineProps<{
  id?: string;

  /** Kelas kolomnya, mengikuti halaman yang memakainya. */
  kelas?: string;

  autocomplete?: string;
  placeholder?: string;
  required?: boolean;
  autofocus?: boolean;

  /** Ikut disebut pada label tombol, supaya pembaca layar tahu kolom mana. */
  label?: string;

  /**
   * Kolom rahasia yang BUKAN sandi — kunci API, misalnya.
   *
   * Dibedakan karena sebutannya ikut terdengar pembaca layar, dan
   * "lihat kata sandi" pada kolom kunci API menyesatkan.
   */
  sebutan?: string;
}>(), {
  id: undefined,
  kelas: '',
  autocomplete: 'current-password',
  placeholder: undefined,
  required: false,
  autofocus: false,
  label: '',
  sebutan: 'kata sandi',
});

const bungkus  = ref<HTMLElement | null>(null);
const terlihat = ref(false);

/* Ruang di kanan kolom untuk tombolnya. Ditambahkan di sini, bukan
   dititipkan ke tiap halaman: halaman yang lupa menambahkannya tidak
   menimbulkan galat apa pun — hanya sandi panjang yang hurufnya
   menyelinap ke bawah tombol dan tidak terbaca. */
const kelasKolom = computed(() => `${props.kelas} pr-12`.trim());

const judul = computed(() =>
  (terlihat.value ? 'Sembunyikan ' : 'Lihat ')
  + props.sebutan
  + (props.label ? ` ${props.label}` : ''));

/**
 * Tersembunyi lagi begitu kolomnya benar-benar ditinggalkan.
 *
 * Yang menyalakan intip lalu berpindah ke kolom berikutnya biasanya
 * lupa mematikannya, dan sandinya tinggal terpampang selama sisa
 * pengisian formulir — pada halaman Ganti Kata Sandi, di sebelah dua
 * kolom sandi lain yang juga sedang diisi.
 *
 * Berpindah ke TOMBOLNYA SENDIRI tidak dihitung meninggalkan: tanpa
 * pengecualian ini, mengintip lewat papan ketik mustahil — Tab dari
 * kolom ke tombolnya sudah menyembunyikan lebih dulu.
 */
function saatKeluar(e: FocusEvent): void {
  const tujuan = e.relatedTarget as Node | null;

  if (tujuan && bungkus.value?.contains(tujuan)) return;

  terlihat.value = false;
}
</script>

<template>
  <div ref="bungkus" class="eq-sandi" @focusout="saatKeluar">
    <input
      :id="id"
      v-model="model"
      :type="terlihat ? 'text' : 'password'"
      :class="kelasKolom"
      :autocomplete="autocomplete"
      :placeholder="placeholder"
      :required="required"
      :autofocus="autofocus"
      autocapitalize="off"
      autocorrect="off"
      spellcheck="false"
    >

    <button
      type="button"
      class="eq-sandi-intip"
      :aria-label="judul"
      :title="judul"
      :aria-pressed="terlihat"
      @mousedown.prevent
      @click="terlihat = !terlihat"
    >
      <svg v-if="!terlihat" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M2.2 12S5.8 5.5 12 5.5 21.8 12 21.8 12 18.2 18.5 12 18.5 2.2 12 2.2 12Z"/>
        <circle cx="12" cy="12" r="3.1"/>
      </svg>

      <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M9.9 5.7A8.7 8.7 0 0 1 12 5.5c6.2 0 9.8 6.5 9.8 6.5a17 17 0 0 1-3.2 4.1"/>
        <path d="M6.5 7.6A16.6 16.6 0 0 0 2.2 12S5.8 18.5 12 18.5c1.6 0 3-.4 4.2-1"/>
        <path d="M10 10a3.1 3.1 0 0 0 4.2 4.2"/>
        <path d="m3 3 18 18"/>
      </svg>
    </button>
  </div>
</template>

<style scoped>
.eq-sandi {
  position: relative;
}

/* Kolomnya tetap selebar induknya walau dibungkus. Tanpa ini, kolom
   yang semula `w-full` menyusut mengikuti isi pembungkusnya. */
.eq-sandi > input {
  width: 100%;
}

.eq-sandi-intip {
  position: absolute;
  top: 50%;
  right: .5rem;
  transform: translateY(-50%);
  display: grid;
  place-items: center;
  width: 2.15rem;
  height: 2.15rem;
  border-radius: .6rem;
  color: #78716C;
  background: transparent;
  border: 0;
  cursor: pointer;
  transition: color .15s, background-color .15s;
}

.eq-sandi-intip svg {
  width: 1.15rem;
  height: 1.15rem;
}

.eq-sandi-intip:hover {
  color: #292524;
  background: rgb(0 0 0 / .05);
}

.eq-sandi-intip:focus-visible {
  outline: 2px solid #F57C00;
  outline-offset: 1px;
  color: #292524;
}

:global([data-tema='gelap']) .eq-sandi-intip {
  color: #A8A29E;
}

:global([data-tema='gelap']) .eq-sandi-intip:hover,
:global([data-tema='gelap']) .eq-sandi-intip:focus-visible {
  color: #F5F5F4;
  background: rgb(255 255 255 / .08);
}
</style>
