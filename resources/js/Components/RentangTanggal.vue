<script setup lang="ts">
/**
 * Penyaring rentang tanggal, dipakai seluruh halaman Energy.
 *
 * Satu komponen supaya tanggal yang dipilih tidak hilang saat berpindah
 * halaman dan bentuknya tidak berbeda-beda.
 */
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{ dari: string; sampai: string; rute: string; gelap?: boolean }>();

const form = useForm({ dari: props.dari, sampai: props.sampai });

function terapkan() {
  form.get(props.rute, { preserveState: true, preserveScroll: true, replace: true });
}

const isian = props.gelap
  ? 'glass-panel text-white border-white/20 [color-scheme:dark]'
  : 'border border-stone-200';
</script>

<template>
  <form class="flex flex-wrap items-end gap-2.5" @submit.prevent="terapkan">
    <div>
      <label class="block text-[10px] font-bold uppercase tracking-wide mb-1"
             :class="gelap ? 'text-white/55' : 'text-stone-500'">Dari</label>
      <input v-model="form.dari" type="date" class="ring-focus rounded-xl px-3 py-2 text-[12.5px] transition" :class="isian">
    </div>
    <div>
      <label class="block text-[10px] font-bold uppercase tracking-wide mb-1"
             :class="gelap ? 'text-white/55' : 'text-stone-500'">Sampai</label>
      <input v-model="form.sampai" type="date" class="ring-focus rounded-xl px-3 py-2 text-[12.5px] transition" :class="isian">
    </div>
    <button type="submit" class="rounded-xl px-4 py-2 text-[12.5px] font-bold transition hover:brightness-110"
            :class="gelap ? 'bg-white text-cam-ink' : 'bg-cam-ink text-white'">Terapkan</button>
  </form>
</template>
