<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import InputSandi from '../../Components/InputSandi.vue';
import VerifikasiTurnstile from '../../Components/VerifikasiTurnstile.vue';

defineOptions({ layout: GuestLayout });

/* Kunci situs dari server. null berarti verifikasinya tidak dipasang,
   dan halaman ini menggambar dirinya seperti sebelum fitur itu ada. */
const kunciTurnstile = computed(
  () => (usePage().props as Record<string, unknown>).turnstile as string | null ?? null,
);

const form = useForm({
  email: '',
  password: '',
  remember: false,

  /* Namanya ditentukan Cloudflare, bukan kami — widget-nya mengisi kolom
     dengan nama persis ini, dan aturan validasi di server mencarinya
     dengan nama yang sama. */
  'cf-turnstile-response': '',
});

/* Penghitung, bukan boolean.
   Dua kali salah sandi berturut-turut menghasilkan boolean yang sama,
   sehingga widget-nya tidak disetel ulang pada percobaan kedua — dan
   tokennya sudah habis sejak percobaan pertama. */
const gagalKe = ref(0);

function masuk() {
  form.post('/login', {
    onError: () => { gagalKe.value += 1; },
    onFinish: () => form.reset('password'),
  });
}
</script>

<template>
  <Head title="Masuk" />

  <h2 class="font-serif text-3xl font-semibold mb-1">Selamat datang kembali</h2>
  <p class="text-sm text-stone-500 mb-6">Masuk untuk melanjutkan.</p>

  <div v-if="Object.keys(form.errors).length" class="mb-4 rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
    <ul class="space-y-0.5"><li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li></ul>
  </div>

  <form class="space-y-4" @submit.prevent="masuk">
    <div>
      <label for="email" class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Email</label>
      <input id="email" v-model="form.email" type="email" autocomplete="username" autofocus required class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm transition">
      <p v-if="form.errors.email" class="text-xs text-red-600 mt-1">{{ form.errors.email }}</p>
    </div>
    <div>
      <label for="password" class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Kata sandi</label>
      <InputSandi id="password" v-model="form.password" autocomplete="current-password" required
                  kelas="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm transition" />
      <p v-if="form.errors.password" class="text-xs text-red-600 mt-1">{{ form.errors.password }}</p>
    </div>
    <VerifikasiTurnstile v-model="form['cf-turnstile-response']"
                        :kunci="kunciTurnstile" :galat="gagalKe" />

    <div class="flex items-center justify-between text-sm">
      <label class="flex items-center gap-2 text-stone-600 cursor-pointer"><input v-model="form.remember" type="checkbox" class="accent-[#F57C00]"> Ingat saya</label>
      <Link href="/forgot-password" class="text-stone-500 hover:text-stone-900 underline underline-offset-4">Lupa sandi?</Link>
    </div>
    <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-[#F57C00] hover:bg-[#DC6E00] text-white py-3 font-bold transition disabled:opacity-50">{{ form.processing ? 'Memproses…' : 'Masuk' }}</button>
  </form>

  <p class="text-center text-sm text-stone-500 mt-6">Belum punya akun? <Link href="/register" class="font-bold text-[#D96500] hover:underline">Daftar di sini</Link></p>
</template>
