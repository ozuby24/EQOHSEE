<script setup lang="ts">
/**
 * Jendela formulir — modal Bootstrap Safe Track: judul biru tua, badan,
 * kaki dengan "Close" dan tombol simpan. Ditutup dengan Esc atau klik
 * latarnya.
 */
import { onBeforeUnmount, watch } from 'vue';
import '../../../css/miners.css';

const props = withDefaults(defineProps<{
  terbuka: boolean;
  judul: string;
  lebar?: boolean;
}>(), { lebar: false });

const emit = defineEmits<{ tutup: [] }>();

function tombol(e: KeyboardEvent) {
  if (e.key === 'Escape') emit('tutup');
}

watch(() => props.terbuka, (t) => {
  if (t) window.addEventListener('keydown', tombol);
  else window.removeEventListener('keydown', tombol);
});

onBeforeUnmount(() => window.removeEventListener('keydown', tombol));
</script>

<template>
  <div v-if="terbuka" class="mn-modal-latar" @click.self="emit('tutup')">
    <div class="mn-modal" :class="{ 'mn-modal-lebar': lebar }" role="dialog" aria-modal="true" :aria-label="judul">
      <div class="mn-modal-kepala">
        <h2>{{ judul }}</h2>
        <button type="button" class="mn-modal-tutup" aria-label="Tutup" @click="emit('tutup')">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/></svg>
        </button>
      </div>
      <div class="mn-modal-badan"><slot /></div>
      <div v-if="$slots.kaki" class="mn-modal-kaki"><slot name="kaki" /></div>
    </div>
  </div>
</template>
