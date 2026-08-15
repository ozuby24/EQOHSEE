<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
defineOptions({ layout: GuestLayout });
const form = useForm({ email: '' });
function kirim() { form.post('/forgot-password'); }
</script>

<template>
  <Head title="Lupa Kata Sandi" />
  <h2 class="font-serif text-3xl font-semibold mb-1">Lupa kata sandi?</h2>
  <p class="text-sm text-stone-500 mb-6">Masukkan email Anda untuk menerima tautan pengaturan ulang.</p>
  <div v-if="form.recentlySuccessful" class="mb-4 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-700 px-4 py-3 text-sm">Tautan reset sudah dikirim jika email terdaftar.</div>
  <div v-if="form.errors.email" class="mb-4 text-sm text-red-600">{{ form.errors.email }}</div>
  <form class="space-y-4" @submit.prevent="kirim"><div><label class="label">Email</label><input v-model="form.email" type="email" required autofocus class="input"></div><button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-[#F57C00] text-white py-3 font-bold disabled:opacity-50">Kirim tautan</button></form>
  <p class="text-center text-sm text-stone-500 mt-6"><Link href="/login" class="font-bold text-[#D96500] hover:underline">Kembali ke masuk</Link></p>
</template>

<style scoped>.label{display:block;margin-bottom:.375rem;font-size:11.5px;font-weight:700;text-transform:uppercase;color:#78716c}.input{width:100%;border:1px solid #e7e5e4;border-radius:.75rem;background:#fff;padding:.75rem 1rem;font-size:.875rem}</style>
