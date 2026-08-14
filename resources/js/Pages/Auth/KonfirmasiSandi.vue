<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
defineOptions({ layout: GuestLayout });
const form = useForm({ password: '' });
function konfirmasi() { form.post('/confirm-password', { onFinish: () => form.reset('password') }); }
</script>

<template>
  <Head title="Konfirmasi Kata Sandi" />
  <h2 class="font-serif text-3xl font-semibold mb-1">Konfirmasi kata sandi</h2>
  <p class="text-sm text-stone-500 mb-6">Ini area aman. Konfirmasi kata sandi sebelum melanjutkan.</p>
  <p v-if="form.errors.password" class="mb-4 text-sm text-red-600">{{ form.errors.password }}</p>
  <form class="space-y-4" @submit.prevent="konfirmasi"><div><label class="label">Kata sandi</label><input v-model="form.password" type="password" required autofocus autocomplete="current-password" class="input"></div><button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-[#F57C00] text-white py-3 font-bold disabled:opacity-50">Konfirmasi</button></form>
</template>

<style scoped>.label{display:block;margin-bottom:.375rem;font-size:11.5px;font-weight:700;text-transform:uppercase;color:#78716c}.input{width:100%;border:1px solid #e7e5e4;border-radius:.75rem;background:#fff;padding:.75rem 1rem;font-size:.875rem}</style>
