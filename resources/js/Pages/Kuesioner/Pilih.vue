<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
  token: string;
  company: { name: string };
  kategori: Record<string, { label: string; indicator: number }>;
}>();

const ikon: Record<string, string> = {
  pekerja: '⛑',
  pimpinan: '♟',
};
</script>

<template>
  <Head title="Pilih Kuesioner" />

  <div class="text-center mb-7">
    <h1 class="stat text-cam-ink">Kuesioner Persepsi Keselamatan</h1>
    <p class="text-[13px] text-stone-500 mt-2">{{ company.name }}</p>
  </div>

  <p class="text-[12.5px] text-stone-500 text-center mb-5">Pilih kuesioner sesuai posisi Anda:</p>

  <div class="grid gap-3 sm:grid-cols-2">
    <Link
      v-for="(item, key) in kategori"
      :key="key"
      :href="`/q/${props.token}/${key}`"
      class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 text-center hover:border-cam-lime/50 card-hover transition"
    >
      <div class="w-12 h-12 mx-auto rounded-2xl lime-gradient grid place-items-center text-white text-[20px] shadow-glow">
        {{ ikon[key] ?? '✓' }}
      </div>
      <div class="text-[15px] font-bold text-cam-ink mt-3.5">{{ item.label }}</div>
      <div class="text-[11.5px] text-stone-400 mt-1">Indikator {{ item.indicator }}</div>
    </Link>
  </div>
</template>
