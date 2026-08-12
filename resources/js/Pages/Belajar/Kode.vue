<script setup lang="ts">
/**
 * Gerbang kode akses kursus.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { HalamanKodeKursus } from '../../types';

const props = defineProps<HalamanKodeKursus>();

const form = useForm({ access_code: '' });

function buka() {
  form.transform((d) => ({ access_code: d.access_code.toUpperCase() }))
      .post(props.tautan.buka);
}
</script>

<template>
  <Head :title="kursus.judul" />

  <div class="max-w-md mx-auto">
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-8 text-center">
      <div class="w-14 h-14 mx-auto rounded-2xl bg-cam-lime-soft text-cam-lime-deep grid place-items-center">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
      </div>

      <h2 class="text-[18px] font-bold text-cam-ink mt-4">Kursus Terkunci</h2>
      <p class="text-[13px] text-stone-500 mt-1.5 leading-relaxed">
        <span class="font-semibold text-cam-ink">{{ kursus.judul }}</span><br>
        Masukkan kode akses yang diberikan trainer Anda.
      </p>

      <div v-if="form.errors.access_code"
           class="mt-4 rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        {{ form.errors.access_code }}
      </div>

      <form class="mt-5 space-y-3" @submit.prevent="buka">
        <input v-model="form.access_code" autofocus autocomplete="off" placeholder="ABC123" maxlength="20"
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-3.5 text-center
                      text-[20px] font-bold tracking-[0.35em] uppercase num transition">
        <button type="submit" :disabled="form.processing || !form.access_code.trim()"
                class="lime-gradient shadow-glow w-full rounded-xl text-white py-3 text-[13.5px]
                       font-bold hover:brightness-105 transition disabled:opacity-40">
          {{ form.processing ? 'Membuka…' : 'Buka Kursus' }}
        </button>
      </form>

      <Link :href="tautan.daftar"
            class="inline-block mt-5 text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">
        ← Kembali ke daftar kursus
      </Link>
    </div>
  </div>
</template>
