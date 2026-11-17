<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Ikon from '../Ikon.vue';
import type { AsistenBersama } from '../../../smkp/types';

/*
 * Laci samping Asisten AI: kepala, penafian, kerangka muat, galat, dan isi
 * hasil (slot). Saat asisten belum aktif laci menjelaskan sebabnya dan
 * tidak ada permintaan yang dikirim.
 */
const props = withDefaults(defineProps<{
  buka: boolean;
  judul: string;
  sub?: string;
  memuat?: boolean;
  galat?: string | null;
  adaHasil?: boolean;
  /** Perkiraan lama menunggu yang ditampilkan saat memuat — tugas berlampiran lebih lama. */
  lama?: string;
}>(), { lama: '15–60 detik' });
const emit = defineEmits<{ 'update:buka': [nilai: boolean]; ulang: [] }>();

const page = usePage<any>();
const asisten = computed<AsistenBersama>(() => page.props.asisten ?? { aktif: false, model: '' });

function tutup() { emit('update:buka', false); }
function tombol(e: KeyboardEvent) { if (props.buka && e.key === 'Escape') tutup(); }
onMounted(() => window.addEventListener('keydown', tombol));
onBeforeUnmount(() => window.removeEventListener('keydown', tombol));
</script>

<template>
  <Teleport to="body">
    <Transition enter-active-class="transition-opacity duration-150" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-100" leave-to-class="opacity-0">
      <div v-if="buka" class="fixed inset-0 z-[70] bg-slate-900/30" aria-hidden="true" @click="tutup" />
    </Transition>
    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-x-full" leave-active-class="transition duration-150 ease-in" leave-to-class="translate-x-full">
      <aside v-if="buka" class="smkp-ui fixed inset-y-0 right-0 z-[71] flex w-full sm:max-w-[480px] flex-col border-l border-slate-200 bg-white/95 backdrop-blur shadow-2xl"
             role="dialog" aria-modal="true" :aria-label="judul">
        <header class="flex items-start gap-3 border-b border-slate-200 px-5 py-4">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-700"><Ikon nama="kilau" class="h-5 w-5" /></span>
          <div class="min-w-0 flex-1">
            <h2 class="kartu-judul">{{ judul }}</h2>
            <p v-if="sub" class="kartu-sub truncate">{{ sub }}</p>
          </div>
          <button type="button" class="btn-hantu btn-kecil px-2" aria-label="Tutup" @click="tutup"><Ikon nama="tutup" class="h-4 w-4" /></button>
        </header>

        <p class="flex items-center gap-2 border-b border-slate-100 bg-slate-50/80 px-5 py-2 text-[11.5px] font-semibold text-slate-500">
          <Ikon nama="info" class="h-3.5 w-3.5 shrink-0" /> Saran AI — keputusan tetap pada auditor.
        </p>

        <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5 scrollbar-tipis" aria-live="polite">
          <!-- Belum aktif -->
          <div v-if="!asisten.aktif" class="kartu p-5">
            <div class="flex gap-3">
              <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500"><Ikon nama="kunci" class="h-4 w-4" /></span>
              <div>
                <h3 class="text-[13.5px] font-semibold text-slate-900">Asisten AI belum diaktifkan</h3>
                <p class="mt-1 text-[12.5px] leading-relaxed text-slate-500">
                  Administrator perlu memasang kunci API Claude, ChatGPT, atau Gemini dan menyalakan AI di menu
                  Pusat Kendali → Integrasi AI.
                </p>
              </div>
            </div>
          </div>

          <!-- Memuat -->
          <div v-else-if="memuat" class="space-y-4" aria-busy="true">
            <p class="bantu mt-0 flex items-center gap-2"><Ikon nama="muat" class="h-3.5 w-3.5 animate-spin" /> Asisten sedang menelaah — biasanya {{ lama }}.</p>
            <div v-for="n in 3" :key="n" class="kartu animate-pulse space-y-2.5 p-5">
              <div class="h-3.5 w-1/3 rounded bg-slate-200" />
              <div class="h-3 w-full rounded bg-slate-100" />
              <div class="h-3 w-5/6 rounded bg-slate-100" />
              <div class="h-3 w-2/3 rounded bg-slate-100" />
            </div>
          </div>

          <!-- Galat -->
          <div v-else-if="galat" class="kartu p-5">
            <div class="flex gap-3">
              <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600"><Ikon nama="peringatan" class="h-4 w-4" /></span>
              <div class="min-w-0">
                <h3 class="text-[13.5px] font-semibold text-slate-900">Saran belum dapat dibuat</h3>
                <p class="mt-1 text-[12.5px] leading-relaxed text-slate-600">{{ galat }}</p>
                <button type="button" class="btn-garis btn-kecil mt-3" @click="emit('ulang')"><Ikon nama="muat" class="h-3.5 w-3.5" /> Coba lagi</button>
              </div>
            </div>
          </div>

          <slot v-else />
        </div>

        <footer v-if="asisten.aktif" class="flex items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
          <span class="truncate text-[11px] text-slate-400">Model {{ asisten.model }} · tidak ada yang tersimpan sebelum Anda menekan Simpan</span>
          <button v-if="adaHasil && !memuat" type="button" class="btn-hantu btn-kecil shrink-0" @click="emit('ulang')"><Ikon nama="muat" class="h-3.5 w-3.5" /> Minta ulang</button>
        </footer>
      </aside>
    </Transition>
  </Teleport>
</template>
