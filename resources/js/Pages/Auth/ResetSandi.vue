<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
defineOptions({ layout: GuestLayout });
const props = defineProps<{ token: string; email: string }>();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
function reset() { form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') }); }
</script>

<template>
  <Head title="Atur Ulang Kata Sandi" />
  <h2 class="font-serif text-3xl font-semibold mb-1">Atur ulang kata sandi</h2>
  <p class="text-sm text-stone-500 mb-6">Buat kata sandi baru untuk akun Anda.</p>
  <div v-if="Object.keys(form.errors).length" class="mb-4 rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]"><ul><li v-for="(pesan,k) in form.errors" :key="k">• {{ pesan }}</li></ul></div>
  <form class="space-y-4" @submit.prevent="reset"><div><label class="label">Email</label><input v-model="form.email" type="email" required class="input"></div><div><label class="label">Kata sandi baru</label><input v-model="form.password" type="password" required autocomplete="new-password" class="input"></div><div><label class="label">Ulangi kata sandi</label><input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="input"></div><button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-[#F57C00] text-white py-3 font-bold disabled:opacity-50">Atur ulang</button></form>
</template>

<style scoped>.label{display:block;margin-bottom:.375rem;font-size:11.5px;font-weight:700;text-transform:uppercase;color:#78716c}.input{width:100%;border:1px solid #e7e5e4;border-radius:.75rem;background:#fff;padding:.75rem 1rem;font-size:.875rem}</style>
