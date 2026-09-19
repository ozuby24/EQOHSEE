<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import VerifikasiTurnstile from '../../Components/VerifikasiTurnstile.vue';

defineOptions({ layout: GuestLayout });

const props = defineProps<{
  /** Kunci situs Turnstile. null berarti verifikasinya tidak dipasang. */
  turnstile?: string | null;

  /** Penanda pintu dari Turnstile::TINDAKAN, agar tokennya terikat ke sini. */
  tindakan?: string | null;
}>();

const form = useForm({
  email: '',

  /* Nama kolomnya ditentukan Cloudflare, dan validasi di server
     mencarinya dengan nama yang sama. */
  'cf-turnstile-response': '',
});

/* Disetel ulang pada SETIAP kiriman yang selesai, bukan hanya yang gagal
   — dan di sinilah halaman ini berbeda dari halaman masuk.
 *
 * Masuk yang berhasil meninggalkan halamannya, sehingga widget yang
 * tokennya sudah terpakai ikut terbuang bersama halamannya. Halaman ini
 * tidak ke mana-mana: sesudah tautannya terkirim, orangnya tetap di sini
 * membaca kabar berhasil, dengan kotak verifikasi yang masih bercentang
 * hijau tetapi tokennya sudah ditukar. Permintaan kedua — salah ketik
 * pada alamatnya, atau surel yang tidak kunjung sampai — akan ditolak
 * oleh kotak yang tampak sudah terisi. */
const putaran = ref(0);

function kirim() {
  form.post('/forgot-password', {
    onFinish: () => { putaran.value += 1; },
  });
}
</script>

<template>
  <Head title="Lupa Kata Sandi" />

  <h2 class="font-serif text-3xl font-semibold mb-1">Lupa kata sandi?</h2>
  <p class="text-sm text-stone-500 mb-6">Masukkan email Anda untuk menerima tautan pengaturan ulang.</p>

  <div v-if="form.recentlySuccessful" class="mb-4 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-700 px-4 py-3 text-sm">Tautan reset sudah dikirim jika email terdaftar.</div>

  <div v-if="Object.keys(form.errors).length" class="mb-4 rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
    <ul class="space-y-0.5"><li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li></ul>
  </div>

  <form class="space-y-4" @submit.prevent="kirim">
    <div>
      <label for="email" class="label">Email</label>
      <input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" class="input">
    </div>

    <VerifikasiTurnstile v-model="form['cf-turnstile-response']"
                         :kunci="props.turnstile ?? null" :tindakan="props.tindakan ?? null"
                         :galat="putaran" />

    <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-[#F57C00] hover:bg-[#DC6E00] text-white py-3 font-bold transition disabled:opacity-50">{{ form.processing ? 'Mengirim…' : 'Kirim tautan' }}</button>
  </form>

  <p class="text-center text-sm text-stone-500 mt-6"><Link href="/login" class="font-bold text-[#D96500] hover:underline">Kembali ke masuk</Link></p>
</template>

<style scoped>
.label { display:block; margin-bottom:.375rem; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#78716c }
.input { width:100%; border:1px solid #e7e5e4; border-radius:.75rem; background:#fff; padding:.75rem 1rem; font-size:.875rem; transition:box-shadow .15s,border-color .15s }
.input:focus { outline:none; border-color:#f57c00; box-shadow:0 0 0 3px rgb(245 124 0 / .15) }
</style>
