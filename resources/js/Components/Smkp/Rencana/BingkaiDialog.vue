<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Ikon from '../Ikon.vue';

/*
 * Bingkai dialog Rencana Audit: kepala, isi bergulir, dan kaki tetap.
 * Selama `sibuk` (unggah/membaca berkas) dialog tidak dapat ditutup dengan
 * Escape atau klik latar agar permintaan tidak terputus tanpa sengaja.
 */
const props = withDefaults(defineProps<{ buka: boolean; judul: string; sub?: string; ikon?: string; lebar?: string; sibuk?: boolean }>(), {
  ikon: 'salin', lebar: 'max-w-3xl', sibuk: false,
});
const emit = defineEmits<{ 'update:buka': [nilai: boolean] }>();

const kotak = ref<HTMLElement | null>(null);

function tutup() { if (!props.sibuk) emit('update:buka', false); }
function tombol(e: KeyboardEvent) { if (props.buka && e.key === 'Escape') tutup(); }

watch(() => props.buka, async (b) => {
  document.body.style.overflow = b ? 'hidden' : '';
  if (b) { await nextTick(); kotak.value?.focus(); }
});
onMounted(() => window.addEventListener('keydown', tombol));
onBeforeUnmount(() => { window.removeEventListener('keydown', tombol); document.body.style.overflow = ''; });
</script>

<template>
  <Teleport to="body">
    <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0" leave-active-class="transition duration-100" leave-to-class="opacity-0">
      <div v-if="buka" class="fixed inset-0 z-[75] flex items-end sm:items-center justify-center sm:p-4 bg-navy-950/55 backdrop-blur-sm" @click.self="tutup">
        <div ref="kotak" tabindex="-1" role="dialog" aria-modal="true" :aria-label="judul"
             class="w-full flex flex-col max-h-[92svh] sm:max-h-[88vh] bg-white rounded-t-2xl sm:rounded-kartu shadow-angkat outline-none animate-muncul" :class="lebar">
          <header class="flex items-start gap-3 px-5 py-4 border-b border-slate-200">
            <span class="grid place-items-center w-9 h-9 shrink-0 rounded-xl bg-brand-50 text-brand-600"><Ikon :nama="ikon" class="w-[18px] h-[18px]" /></span>
            <div class="min-w-0 flex-1">
              <h2 class="kartu-judul">{{ judul }}</h2>
              <p v-if="sub" class="kartu-sub">{{ sub }}</p>
            </div>
            <button type="button" class="btn-hantu btn-kecil px-2" :disabled="sibuk" aria-label="Tutup" @click="tutup"><Ikon nama="tutup" class="w-4 h-4" /></button>
          </header>
          <div class="flex-1 min-h-0 overflow-y-auto scrollbar-tipis px-5 py-4">
            <slot />
          </div>
          <footer v-if="$slots.kaki" class="flex flex-wrap items-center justify-end gap-2 px-5 py-3.5 border-t border-slate-200 bg-slate-50/70 rounded-b-kartu">
            <slot name="kaki" />
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
