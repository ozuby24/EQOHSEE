<script setup lang="ts">
/**
 * Kotak verifikasi Cloudflare Turnstile.
 *
 * Menggambar dirinya sendiri hanya bila kunci situsnya dikirim server.
 * Tanpa kunci, komponen ini tidak menggambar apa pun dan tidak memuat
 * satu skrip pun — halaman masuk bekerja persis seperti sebelum fitur
 * ini ada.
 *
 * ── Tokennya sekali pakai, dan itu menentukan seluruh rancangan ini ──
 *
 * Cloudflare menolak token yang sudah pernah ditukar. Sandi yang salah
 * sekali saja karena itu sudah menghabiskan tokennya, dan percobaan
 * kedua akan ditolak dengan alasan "verifikasi keamanan gagal" —
 * padahal yang salah sandinya, dan kotak verifikasinya masih tampak
 * bercentang hijau di layar.
 *
 * Maka tiap kali halaman memulangkan galat, widget-nya disetel ulang.
 * `galat` sengaja berupa penghitung, bukan boolean: dua kali salah sandi
 * berturut-turut menghasilkan nilai boolean yang SAMA, sehingga
 * pengawasnya tidak menyala pada percobaan kedua — dan orangnya
 * terjebak pada galat yang tidak dapat ia perbaiki dengan cara apa pun
 * selain memuat ulang halaman.
 *
 * ── Skripnya dimuat sekali per halaman ──
 *
 * Dimuat ulang tiap kali komponen ini dipasang, Turnstile mendaftarkan
 * dirinya berkali-kali dan menggambar widget kembar.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(defineProps<{
  /** Kunci situs dari server. null berarti fitur ini mati. */
  kunci?: string | null;

  /** Dinaikkan halaman tiap kali kiriman ditolak, agar widget disetel ulang. */
  galat?: number;

  /**
   * Penanda pintu, dikirim server dari Turnstile::TINDAKAN.
   *
   * Datang dari server dan bukan ditulis di sini supaya kedua sisinya
   * tidak dapat berbeda. Ditulis di sini, ia akan berbeda pada suatu
   * hari — dan bedanya tidak menimbulkan galat apa pun, hanya seluruh
   * kiriman dari halaman ini ditolak dengan alasan yang tidak
   * menyebutnya.
   */
  tindakan?: string | null;

  tema?: 'auto' | 'light' | 'dark';
}>(), { kunci: null, galat: 0, tindakan: null, tema: 'auto' });

const model = defineModel<string>({ default: '' });

const kotak = ref<HTMLElement | null>(null);
const id    = ref<string | null>(null);

/* Skripnya tidak sampai ke peramban ini.
   Perlu dibedakan dari "belum selesai dimuat": yang pertama tidak akan
   pernah berubah sendiri, dan orangnya harus diberi tahu. */
const terhalang = ref(false);

/* Alamat skripnya ditulis di satu tempat saja. Berbeda dari yang
   diizinkan CSP di server, widget-nya diblokir tanpa pesan apa pun. */
const SKRIP = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

declare global {
  interface Window {
    turnstile?: {
      render: (el: HTMLElement, opsi: Record<string, unknown>) => string;
      reset: (id?: string) => void;
      remove: (id?: string) => void;
    };
  }
}

function muatSkrip(): Promise<void> {
  if (window.turnstile) return Promise.resolve();

  const ada = document.querySelector<HTMLScriptElement>(`script[src="${SKRIP}"]`);
  if (ada) return new Promise((selesai) => ada.addEventListener('load', () => selesai()));

  return new Promise((selesai, tolak) => {
    const s = document.createElement('script');
    s.src = SKRIP;
    s.async = true;
    s.defer = true;
    s.onload  = () => selesai();
    s.onerror = () => tolak(new Error('turnstile gagal dimuat'));
    document.head.appendChild(s);
  });
}

async function gambar(): Promise<void> {
  if (!props.kunci || !kotak.value) return;

  try {
    await muatSkrip();
  } catch {
    /* Skripnya tidak sampai: pemblokir iklan, penyaring jaringan
       perusahaan, atau site tambang yang memang memblokir Cloudflare.
     *
     * Ini HARUS terlihat. Tanpa skripnya tidak ada token, tanpa token
     * server menolak, dan yang dibaca orangnya hanya "verifikasi
     * keamanan gagal" pada formulir yang sudah ia isi dengan benar —
     * pesan yang tidak menyebutkan satu pun hal yang dapat ia perbaiki.
     *
     * Tidak ada jalan pintas yang aman di sisi server: permintaan tanpa
     * token dari peramban yang terhalang tidak dapat dibedakan dari
     * permintaan tanpa token yang dikirim skrip penebak sandi. Maka yang
     * bisa diberikan hanyalah keterangan yang cukup untuk bertindak. */
    terhalang.value = true;
    return;
  }

  if (!window.turnstile || !kotak.value) return;

  /* Dicor ke HTMLElement dengan sengaja.
     vue-tsc menyusun bentuk elemennya sendiri untuk ref templat, dan
     bentuk itu tidak pernah sama persis dengan HTMLElement milik
     lib.dom — sekalipun keduanya elemen yang sama pada saat berjalan. */
  id.value = window.turnstile.render(kotak.value as HTMLElement, {
    sitekey: props.kunci,
    action: props.tindakan ?? undefined,

    /* 'flexible', bukan 'normal'.
     *
     * Bawaannya menggambar kotak selebar 300px tetap, dan pada formulir
     * yang seluruh isiannya selebar penuh, kotak itu berdiri sendirian
     * lebih pendek daripada baris di atas dan di bawahnya — satu-satunya
     * unsur yang tidak sejajar. 'flexible' membuatnya mengikuti lebar
     * wadahnya, dengan 300px sebagai batas terkecil. */
    size: 'flexible',

    theme: props.tema,
    language: 'id',
    callback: (t: string) => { model.value = t; },

    /* Token kedaluwarsa sesudah lima menit. Halaman masuk yang dibuka
       lalu ditinggal — rapat, panggilan radio — kembali dengan token
       yang sudah mati, dan kiriman berikutnya ditolak tanpa sebab yang
       terlihat. Disetel ulang sendiri sebelum itu terjadi. */
    'expired-callback': () => { model.value = ''; window.turnstile?.reset(id.value ?? undefined); },
    'error-callback':   () => { model.value = ''; },
  });
}

watch(() => props.galat, () => {
  if (!id.value) return;

  model.value = '';
  window.turnstile?.reset(id.value);
});

onMounted(gambar);

/* Dilepas saat halaman berpindah. Inertia tidak memuat ulang dokumennya,
   sehingga widget yang tidak dilepas meninggalkan pengamat yang masih
   menunjuk elemen yang sudah dibuang. */
onBeforeUnmount(() => {
  if (id.value) window.turnstile?.remove(id.value);
});
</script>

<template>
  <div v-if="kunci" class="eq-turnstile">
    <div v-show="!terhalang" ref="kotak" />

    <p v-if="terhalang" class="eq-turnstile-halang">
      Verifikasi keamanan tidak dapat dimuat. Biasanya ini karena pemblokir iklan
      atau penyaring jaringan yang menutup <code>challenges.cloudflare.com</code>.
      Matikan pemblokirnya untuk situs ini, atau hubungi administrator jaringan Anda.
    </p>
  </div>
</template>

<style scoped>
.eq-turnstile {
  /* Selebar penuh, sejajar dengan isian di atas dan di bawahnya. Lebar
     sesungguhnya diatur Cloudflare lewat size: 'flexible'; yang disetel
     di sini wadahnya, supaya iframe-nya punya lebar yang bisa diikuti. */
  width: 100%;

  /* Tinggi widget-nya dipesan sejak awal supaya tombol di bawahnya tidak
     melompat ketika kotaknya selesai digambar — lompatan yang paling
     sering berakhir sebagai klik yang meleset. */
  min-height: 65px;
}

/* Sudut yang sama dengan isian di sekitarnya. Kotak verifikasi yang
   sudutnya sendiri terbaca sebagai tempelan, bukan bagian formulirnya. */
.eq-turnstile :deep(iframe) {
  border-radius: .75rem;
}

.eq-turnstile-halang {
  margin: 0;
  border: 1px solid #FDE68A;
  border-left: 3px solid #F59E0B;
  border-radius: .75rem;
  background: #FFFBEB;
  padding: .6rem .8rem;
  font-size: 12px;
  line-height: 1.55;
  color: #92400E;
}

.eq-turnstile-halang code {
  font-size: 11.5px;
  background: rgb(0 0 0 / .05);
  border-radius: .25rem;
  padding: 0 .2rem;
}

:global([data-tema='gelap']) .eq-turnstile-halang {
  background: #241B0C;
  border-color: #6B4A10;
  color: #FDBA74;
}
</style>
