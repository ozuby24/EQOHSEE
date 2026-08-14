<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

defineOptions({ layout: GuestLayout });

const form = useForm({ email: '', password: '', remember: false });

function masuk() {
  form.post('/login', { onFinish: () => form.reset('password') });
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
      <input id="password" v-model="form.password" type="password" autocomplete="current-password" required class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm transition">
      <p v-if="form.errors.password" class="text-xs text-red-600 mt-1">{{ form.errors.password }}</p>
    </div>
    <div class="flex items-center justify-between text-sm">
      <label class="flex items-center gap-2 text-stone-600 cursor-pointer"><input v-model="form.remember" type="checkbox" class="accent-[#F57C00]"> Ingat saya</label>
      <Link href="/forgot-password" class="text-stone-500 hover:text-stone-900 underline underline-offset-4">Lupa sandi?</Link>
    </div>
    <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-[#F57C00] hover:bg-[#DC6E00] text-white py-3 font-bold transition disabled:opacity-50">{{ form.processing ? 'Memproses…' : 'Masuk' }}</button>
  </form>

  <p class="text-center text-sm text-stone-500 mt-6">Belum punya akun? <Link href="/register" class="font-bold text-[#D96500] hover:underline">Daftar di sini</Link></p>
</template>
