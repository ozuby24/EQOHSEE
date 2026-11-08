<script setup lang="ts">
import Ikon from './Ikon.vue';

withDefaults(defineProps<{ judul: string; pesan?: string | null; ikon?: string }>(), { ikon: 'folder', pesan: null });
</script>

<template>
  <div class="text-center py-12 px-6">
    <span class="mx-auto grid place-items-center w-14 h-14 rounded-kartu bg-slate-100 text-slate-400">
      <Ikon :nama="ikon" class="w-6 h-6" />
    </span>
    <h3 class="mt-4 text-[15px] font-bold text-navy-900">{{ judul }}</h3>
    <p v-if="pesan" class="mt-1.5 text-[13px] text-slate-500 max-w-md mx-auto leading-relaxed">{{ pesan }}</p>
    <div v-if="$slots.default" class="mt-5 flex justify-center gap-2"><slot /></div>
  </div>
</template>
