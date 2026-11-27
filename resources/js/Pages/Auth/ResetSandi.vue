<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import InputSandi from '../../Components/InputSandi.vue';
import { useBahasa } from '../../tamu/bahasa';
defineOptions({ layout: GuestLayout });

const { t } = useBahasa();
const props = defineProps<{ token: string; email: string; sandiMinimal: number }>();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
function reset() { form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') }); }
</script>

<template>
  <Head :title="t('atur.judul_tab')" />
  <h2 class="auth-judul">{{ t('atur.judul') }}</h2>
  <p class="auth-ket mb-7">{{ t('atur.sub') }}</p>
  <div v-if="Object.keys(form.errors).length" class="mb-4 rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]"><ul><li v-for="(pesan,k) in form.errors" :key="k">• {{ pesan }}</li></ul></div>
  <form class="space-y-4" @submit.prevent="reset"><div><label class="label">{{ t('masuk.email') }}</label><input v-model="form.email" type="email" required class="input"></div><div><label class="label">{{ t('atur.sandi_baru') }}</label><InputSandi v-model="form.password" required autocomplete="new-password" kelas="input" label="baru" /><p class="petunjuk">{{ t('daftar.petunjuk_sandi', { n: props.sandiMinimal }) }}</p></div><div><label class="label">{{ t('daftar.ulangi_sandi') }}</label><InputSandi v-model="form.password_confirmation" required autocomplete="new-password" kelas="input" label="ulangan" /></div><button type="submit" :disabled="form.processing" class="auth-tombol">{{ t('atur.tombol') }}</button></form>
</template>

<style scoped>.label{display:block;margin-bottom:.375rem;font-size:11.5px;font-weight:700;text-transform:uppercase;color:#78716c}.input{width:100%;border:1px solid #e7e5e4;border-radius:.75rem;background:#fff;padding:.75rem 1rem;font-size:.875rem}.petunjuk{margin:.4rem 0 0;font-size:11.5px;line-height:1.5;color:#78716c}</style>
