<script setup lang="ts">
/**
 * Pengumuman yang terbuka di tempat, lengkap dengan sampul dan lampiran.
 *
 * ── Kenapa pop-out, padahal halaman penuhnya sudah ada ──
 *
 * Pengumuman dibaca DI SELA pekerjaan lain. Yang membukanya dari dasbor
 * sedang melihat progres kursusnya; yang membukanya dari daftar sedang
 * menelusuri pengumuman lain. Berpindah halaman penuh untuk membaca
 * empat paragraf berarti kehilangan tempatnya, dan tombol kembali
 * peramban mengembalikannya ke puncak daftar, bukan ke baris yang tadi
 * dibaca.
 *
 * Halaman penuhnya TIDAK dihapus dan tautannya tetap ada di kaki
 * pop-out ini. Alamat pengumuman kerap disalin ke grup pesan, dan
 * alamat yang mati karena diganti pop-out adalah kerugian yang tidak
 * terlihat sampai orang mengeluh tautannya rusak.
 *
 * ── Isinya sudah ada sebelum dibuka ──
 *
 * Seluruh muatan datang bersama halamannya sebagai prop. Tidak ada
 * pengambilan apa pun saat dibuka. Pop-out yang masih harus mengambil
 * isinya lewat jaringan gagal terbuka justru di sambungan site yang
 * lambat — cacat yang sama persis pernah menimpa pop-out pengenalan dan
 * berubah menjadi kotak "gagal dimuat" yang muncul lagi setiap kali
 * halamannya disegarkan.
 *
 * ── Isinya teks polos, dan itu disengaja ──
 *
 * `whitespace-pre-line`, bukan v-html. Apa pun yang diketik penulis
 * tergambar apa adanya, sehingga tidak ada jalan menyelipkan markah ke
 * halaman orang lain lewat pengumuman — dan pengumuman adalah tempat
 * paling nyaman untuk itu: ditulis satu orang, tergambar di layar
 * semua orang.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import type { Pengumuman } from '../types';
import Putaran from './Putaran.vue';

const props = defineProps<{ item: Pengumuman | null }>();
const emit = defineEmits<{ tutup: [] }>();

const terbuka = computed(() => props.item !== null);

const kotak    = ref<HTMLElement | null>(null);
const badan    = ref<HTMLElement | null>(null);
const menandai = ref(false);

/**
 * Masih ada isi di bawah lipatan?
 *
 * Diukur, bukan diduga. Tanpa penanda ini pengumuman panjang terpotong
 * tepat di tengah kartu lampiran, dan potongan di tengah elemen terbaca
 * sebagai gambar yang rusak — bukan sebagai isyarat bahwa halamannya
 * masih bisa digulir.
 *
 * Diperbarui saat digulir DAN saat dibuka. Yang hanya diperbarui saat
 * digulir tidak pernah menyala pada pengumuman yang belum disentuh —
 * yaitu tepat keadaan yang membutuhkannya.
 */
const adaLagi = ref(false);

function ukurGulir() {
  const el = badan.value;
  if (!el) { adaLagi.value = false; return; }

  /* Ambang 4 piksel, bukan nol. Pembulatan pecahan piksel pada layar
     berkerapatan ganda menyisakan selisih di bawah satu piksel pada
     keadaan yang sudah mentok bawah, dan perbandingan tepat membuat
     tandanya tidak pernah padam. */
  adaLagi.value = el.scrollHeight - el.scrollTop - el.clientHeight > 4;
}

/* Penanda LOKAL di atas penanda dari server.
   Tanpa ini, menekan "Saya sudah membaca" tidak mengubah apa pun sampai
   halamannya dimuat ulang — tombolnya tetap mengundang ditekan, dan yang
   menekannya mengira tekanannya tidak masuk. */
const baruDibaca = ref(false);

const sudah = computed(() => baruDibaca.value || props.item?.sudahDibaca === true);

const jumlah = computed(() => {
  const n = props.item?.jumlahDibaca ?? 0;

  /* Angkanya ikut naik seketika ketika orang ini yang menambahnya.
     Angka yang diam sesudah tombolnya ditekan membuat tombol itu tampak
     tidak berpengaruh apa pun. */
  return baruDibaca.value ? n + 1 : n;
});

function tutup() { emit('tutup'); }

function tandaiBaca() {
  if (!props.item || sudah.value || menandai.value) return;

  menandai.value = true;

  router.post(props.item.urlBaca, {}, {
    preserveScroll: true,
    preserveState: true,

    /* `only: []` — jawabannya tidak perlu membawa satu prop pun.
       Tanpa ini, menandai satu pengumuman menyusun ulang SELURUH dasbor
       beserta enam grafiknya, dan pop-out yang sedang terbuka digambar
       ulang di tengah orang membacanya. */
    only: [],

    onSuccess: () => { baruDibaca.value = true; },
    onFinish:  () => { menandai.value = false; },
  });
}

/* Esc ditangkap di window, bukan pada elemennya.
   Yang dipasang pada div hanya menyala bila fokus kebetulan ada di
   dalamnya — dan sesudah pop-out dibuka dengan klik tetikus, fokus
   biasanya masih tertinggal pada tombol yang membukanya. */
function tekan(e: KeyboardEvent) {
  if (e.key === 'Escape') { e.preventDefault(); tutup(); }
}

watch(terbuka, async (buka) => {
  baruDibaca.value = false;

  if (buka) {
    window.addEventListener('keydown', tekan);

    /* Halaman di belakang dikunci gulirannya. Tanpa ini, menggulir di
       atas latar gelap menggeser daftar di belakangnya, dan yang
       menutup pop-out mendapati dirinya di tempat lain. */
    document.body.style.overflow = 'hidden';

    await nextTick();
    kotak.value?.focus();
    ukurGulir();
  } else {
    window.removeEventListener('keydown', tekan);
    document.body.style.overflow = '';
  }
});

/* Dibereskan juga saat komponennya dilepas. Berpindah halaman selagi
   pop-out terbuka tidak menjalankan cabang `else` di atas, dan halaman
   berikutnya akan mewarisi body yang terkunci gulirannya — kerusakan
   yang tampak sebagai "halaman tidak bisa di-scroll" tanpa satu pun
   petunjuk asalnya. */
onBeforeUnmount(() => {
  window.removeEventListener('keydown', tekan);
  document.body.style.overflow = '';
});
</script>

<template>
  <Transition name="eq-pop">
    <div v-if="item" class="eq-pop-latar" role="dialog" aria-modal="true"
         :aria-label="item.judul" @click.self="tutup">

      <div ref="kotak" class="eq-pop" tabindex="-1">

        <!-- Sampul, bila ada. Pengumuman tanpa gambar TIDAK mendapat
             kotak abu-abu pengganti: ruang kosong berlabel "tanpa
             gambar" lebih mengganggu daripada tidak ada gambar. -->
        <figure v-if="item.sampul" class="eq-pop-sampul">
          <img :src="item.sampul" :alt="`Sampul: ${item.judul}`" loading="lazy">
        </figure>

        <button type="button" class="eq-pop-silang" aria-label="Tutup pengumuman" @click="tutup">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
               stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>

        <div ref="badan" class="eq-pop-isi" :class="{ 'is-lanjut': adaLagi }" @scroll="ukurGulir">
          <span class="eq-pop-pil">Pengumuman</span>

          <h2 class="eq-pop-judul">{{ item.judul }}</h2>

          <p class="eq-pop-meta">
            <span>{{ item.tanggal }}</span>
            <span v-if="item.jumlahDibaca" aria-hidden="true">·</span>
            <span v-if="item.jumlahDibaca">Dibaca {{ jumlah }} orang</span>
          </p>

          <!-- Ringkasan sengaja TIDAK digambar di sini.
               Tugasnya menggantikan isi di daftar, bukan mendahuluinya.
               Di pop-out isi lengkapnya ada tepat di bawahnya, dan
               penulis yang menyalin kalimat pertamanya sebagai ringkasan
               — yang memang paling wajar dilakukan — membuat pengumuman
               membuka dirinya dengan mengulang dirinya sendiri. -->
          <div v-if="item.isi" class="eq-pop-badan">{{ item.isi }}</div>
          <p v-else class="eq-pop-badan eq-pop-hampa">Pengumuman ini belum berisi keterangan.</p>

          <a v-if="item.lampiran" :href="item.lampiran.url" class="eq-pop-lampiran">
            <span class="eq-pop-lampiran-ikon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 3v5h5"/><path d="M15 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/>
              </svg>
            </span>
            <span class="eq-pop-lampiran-teks">
              <strong>{{ item.lampiran.nama }}</strong>
              <small>Unduh lampiran</small>
            </span>
          </a>
        </div>

        <div class="eq-pop-kaki">
          <button v-if="!sudah" type="button" class="eq-btn-utama eq-pop-aksi"
                  :disabled="menandai" @click="tandaiBaca">
            <Putaran v-if="menandai" :ukuran="13" />
            {{ menandai ? 'Mencatat…' : 'Saya sudah membaca' }}
          </button>

          <span v-else class="eq-pop-sudah">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
            Sudah Anda baca
          </span>

          <Link :href="item.url" class="eq-pop-penuh">Buka halaman penuh →</Link>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.eq-pop-latar {
  position: fixed;
  inset: 0;
  z-index: 70;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgb(12 17 23 / .62);
  backdrop-filter: blur(3px);
}

/*
  Tinggi kotak dibatasi, dan BADANNYA yang bergulir — bukan halamannya.

  `grid-template-rows: minmax(0, 1fr)` di sini bukan kerapian: baris
  grid berukuran `auto` TIDAK PERNAH mengerut di bawah isinya, sehingga
  `max-height` pada kotaknya terabaikan dan pengumuman panjang tumbuh
  melewati tepi bawah layar. Cacat itu persis yang pernah membuat
  pop-out pengenalan tersangkut di butir ketiga.
*/
.eq-pop {
  position: relative;
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  width: min(100%, 640px);
  max-height: min(88dvh, 860px);
  border-radius: 20px;
  background: #fff;
  box-shadow: 0 24px 60px -18px rgb(12 17 23 / .55);
  overflow: hidden;
}

.eq-pop:focus { outline: none; }

.eq-pop-sampul {
  grid-row: 1;
  margin: 0;
  height: 210px;
  background: #E7E5E4;
}

.eq-pop-sampul img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* Tanpa sampul, baris pertama tetap ada tetapi kosong — dan tombol
   silang yang melayang di atas putih perlu latarnya sendiri agar tetap
   terbaca. Keduanya diselesaikan satu aturan: tombolnya selalu berlatar
   gelap tembus pandang, di atas foto maupun di atas putih. */
.eq-pop-silang {
  position: absolute;
  top: .7rem;
  right: .7rem;
  z-index: 2;
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 99px;
  border: 0;
  color: #fff;
  background: rgb(12 17 23 / .55);
  backdrop-filter: blur(4px);
  cursor: pointer;
  transition: background .15s ease;
}

.eq-pop-silang:hover { background: rgb(12 17 23 / .78); }
.eq-pop-silang svg { width: 1rem; height: 1rem; }

.eq-pop-isi {
  grid-row: 2;

  /* min-height: 0 wajib meski barisnya sudah minmax(0,1fr): elemen
     yang bergulir harus BOLEH lebih pendek dari isinya, dan nilai
     bawaan `auto` melarangnya. */
  min-height: 0;
  overflow-y: auto;
  padding: 1.35rem 1.5rem .4rem;
}

.eq-pop-pil {
  display: inline-block;
  padding: .2rem .55rem;
  border-radius: 99px;
  font-size: 9.5px;
  font-weight: 800;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--eq-aksen, #D96500);
  background: color-mix(in srgb, var(--eq-aksen, #F57C00) 13%, transparent);
}

.eq-pop-judul {
  margin: .6rem 0 0;
  font-size: 21px;
  font-weight: 800;
  line-height: 1.28;
  color: #1B2024;
}

.eq-pop-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .4rem;
  margin: .5rem 0 0;
  font-size: 11.5px;
  font-weight: 600;
  color: #A8A29E;
}

.eq-pop-badan {
  margin-top: 1rem;
  font-size: 13.5px;
  line-height: 1.78;
  color: #44403C;
  white-space: pre-line;
}

.eq-pop-hampa { color: #A8A29E; font-style: italic; }

.eq-pop-lampiran {
  display: flex;
  align-items: center;
  gap: .7rem;
  margin: 1.15rem 0 .4rem;
  padding: .7rem .85rem;
  border: 1px solid #E7E5E4;
  border-radius: 14px;
  text-decoration: none;
  transition: border-color .15s ease, background .15s ease;
}

.eq-pop-lampiran:hover {
  border-color: color-mix(in srgb, var(--eq-aksen, #F57C00) 45%, transparent);
  background: color-mix(in srgb, var(--eq-aksen, #F57C00) 6%, transparent);
}

.eq-pop-lampiran-ikon {
  display: grid;
  place-items: center;
  flex: none;
  width: 2.1rem;
  height: 2.1rem;
  border-radius: 10px;
  color: var(--eq-aksen, #D96500);
  background: color-mix(in srgb, var(--eq-aksen, #F57C00) 13%, transparent);
}

.eq-pop-lampiran-ikon svg { width: 1.05rem; height: 1.05rem; }

.eq-pop-lampiran-teks { display: grid; min-width: 0; }
.eq-pop-lampiran-teks strong {
  font-size: 12.5px; font-weight: 700; color: #1B2024;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.eq-pop-lampiran-teks small { font-size: 11px; color: #A8A29E; }

/* Lapisan pudar tepat di atas kaki, hanya ketika memang masih ada
   isi di bawahnya. pointer-events:none supaya ia tidak menghalangi
   klik pada kartu lampiran yang kebetulan berada di bawahnya. */
.eq-pop-isi.is-lanjut::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  height: 2.6rem;
  margin-top: -2.6rem;
  background: linear-gradient(to bottom, rgb(255 255 255 / 0), #fff 88%);
  pointer-events: none;
}

.eq-pop-isi { position: relative; }
.eq-pop-isi.is-lanjut::after { position: sticky; bottom: 0; display: block; }

.eq-pop-kaki {
  grid-row: 3;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: .6rem;
  padding: .9rem 1.5rem 1.1rem;
  border-top: 1px solid #F5F5F4;
  background: #FCFCFB;
}

.eq-pop-aksi { padding: 9px 16px; font-size: 12px; }

.eq-pop-sudah {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: 12px;
  font-weight: 700;
  color: #0F9D52;
}

.eq-pop-sudah svg { width: .9rem; height: .9rem; }

.eq-pop-penuh {
  font-size: 12px;
  font-weight: 700;
  color: #A8A29E;
  text-decoration: none;
}

.eq-pop-penuh:hover { color: #1B2024; }

/* ── mode gelap ── */
:global([data-tema='gelap']) .eq-pop { background: #111C27; }
:global([data-tema='gelap']) .eq-pop-judul { color: #F5F5F4; }
:global([data-tema='gelap']) .eq-pop-badan { color: #D6D3D1; }
:global([data-tema='gelap']) .eq-pop-lampiran { border-color: #2A3A4B; }
:global([data-tema='gelap']) .eq-pop-lampiran-teks strong { color: #F5F5F4; }
:global([data-tema='gelap']) .eq-pop-kaki { background: #0D1621; border-top-color: #22303F; }
:global([data-tema='gelap']) .eq-pop-isi.is-lanjut::after {
  background: linear-gradient(to bottom, rgb(17 28 39 / 0), #111C27 88%);
}

/* ── gerak ── */
.eq-pop-enter-active, .eq-pop-leave-active { transition: opacity .16s ease; }
.eq-pop-enter-from, .eq-pop-leave-to { opacity: 0; }
.eq-pop-enter-active .eq-pop { transition: transform .18s cubic-bezier(.2, .8, .3, 1); }
.eq-pop-enter-from .eq-pop { transform: translateY(.9rem) scale(.985); }

@media (prefers-reduced-motion: reduce) {
  .eq-pop-enter-active, .eq-pop-leave-active,
  .eq-pop-enter-active .eq-pop { transition: none; }
  .eq-pop-enter-from .eq-pop { transform: none; }
}

/* Di layar sempit pop-out menempel ke bawah dan memakai hampir seluruh
   tinggi: kotak melayang di tengah layar ponsel menyisakan pita kosong
   di atas dan di bawah, sementara isinya sendiri terhimpit. */
@media (max-width: 520px) {
  .eq-pop-latar { padding: 0; place-items: end stretch; }
  .eq-pop {
    width: 100%;
    max-height: 92dvh;
    border-radius: 20px 20px 0 0;
  }
  .eq-pop-sampul { height: 168px; }
  .eq-pop-isi { padding: 1.1rem 1.15rem .4rem; }
  .eq-pop-kaki { padding: .85rem 1.15rem 1rem; }
}
</style>
