<script setup lang="ts">
/**
 * Formulir berita.
 */
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanFormBerita } from '../../types';

const props = defineProps<HalamanFormBerita>();

const form = useForm({ ...props.awal });

function simpan() {
  if (props.tersimpan) form.put(props.tautan.simpan);
  else form.post(props.tautan.simpan);
}

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition';
const label = 'block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-2xl mx-auto">
    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-5"
          @submit.prevent="simpan">

      <div v-if="Object.keys(form.errors).length"
           class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        <ul class="space-y-0.5">
          <li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li>
        </ul>
      </div>

      <div>
        <label :class="label">Judul</label>
        <input v-model="form.title" :class="isian">
      </div>
      <div>
        <label :class="label">Tanggal terbit</label>
        <input v-model="form.published_at" type="date" :class="isian">
      </div>
      <div>
        <label :class="label">Isi</label>
        <textarea v-model="form.content" rows="10" :class="[isian, 'leading-relaxed']"></textarea>
      </div>

      <div class="flex items-center gap-3 pt-1">
        <button type="submit" :disabled="form.processing"
                class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px]
                       font-bold hover:brightness-105 transition disabled:opacity-40">
          {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
        </button>
        <a :href="tautan.batal" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Batal</a>
      </div>
    </form>
  </div>
</template>
