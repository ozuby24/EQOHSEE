<script setup lang="ts">
import { ref } from 'vue';
import Ikon from '../Ikon.vue';
import { salinTeks } from './api';

/* Satu bagian hasil asisten: judul, isi (slot), tombol Salin, dan slot aksi Terapkan. */
const props = defineProps<{ judul: string; teks?: string }>();

const tersalin = ref(false);
async function salin() {
  if (!props.teks) return;
  if (await salinTeks(props.teks)) {
    tersalin.value = true;
    setTimeout(() => (tersalin.value = false), 1600);
  }
}
</script>

<template>
  <section class="kartu">
    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
      <h3 class="text-[13px] font-semibold text-slate-900">{{ judul }}</h3>
      <div class="flex shrink-0 items-center gap-1.5">
        <slot name="lencana" />
        <button v-if="teks" type="button" class="btn-hantu btn-kecil px-2" :aria-label="`Salin ${judul}`" @click="salin">
          <Ikon :nama="tersalin ? 'centang' : 'salin'" class="h-3.5 w-3.5" /> {{ tersalin ? 'Tersalin' : 'Salin' }}
        </button>
      </div>
    </div>
    <div class="space-y-3 px-4 py-3.5 text-[12.5px] leading-relaxed text-slate-700">
      <slot />
    </div>
    <div v-if="$slots.aksi" class="space-y-2.5 border-t border-slate-100 bg-slate-50/60 px-4 py-3">
      <slot name="aksi" />
    </div>
  </section>
</template>
