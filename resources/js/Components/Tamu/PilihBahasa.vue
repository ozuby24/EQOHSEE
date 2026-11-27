<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useBahasa, type Bahasa } from '../../tamu/bahasa';

/**
 * Pil pilihan bahasa halaman tamu: bendera, kode bahasa, dan menu kecil.
 *
 * Menunya ditutup oleh klik di luar dan tombol Escape, dan fokusnya
 * kembali ke tombol pemicunya — pengguna papan ketik tidak tertinggal
 * di menu yang sudah hilang.
 */
const { bahasa, pilih, t } = useBahasa();
const buka = ref(false);
const akar = ref<HTMLElement | null>(null);
const pemicu = ref<HTMLButtonElement | null>(null);

const PILIHAN: Bahasa[] = ['id', 'en'];

function tutupLuar(e: MouseEvent): void {
  if (buka.value && akar.value && !akar.value.contains(e.target as Node)) buka.value = false;
}

function tekan(e: KeyboardEvent): void {
  if (e.key === 'Escape' && buka.value) {
    buka.value = false;
    pemicu.value?.focus();
  }
}

function ganti(b: Bahasa): void {
  pilih(b);
  buka.value = false;
  pemicu.value?.focus();
}

onMounted(() => {
  document.addEventListener('click', tutupLuar);
  document.addEventListener('keydown', tekan);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', tutupLuar);
  document.removeEventListener('keydown', tekan);
});
</script>

<template>
  <div ref="akar" class="ms-bahasa">
    <button ref="pemicu" type="button" class="ms-bahasa-tombol" aria-haspopup="true" :aria-expanded="buka"
            :aria-label="t('bahasa.label', { nama: t(bahasa === 'en' ? 'bahasa.en' : 'bahasa.id') })"
            @click="buka = !buka">
      <span class="ms-bendera" aria-hidden="true">
        <svg v-if="bahasa === 'id'" viewBox="0 0 2 2" preserveAspectRatio="none"><path d="M0 0h2v1H0z" fill="#E2232F" /><path d="M0 1h2v1H0z" fill="#FFFFFF" /></svg>
        <svg v-else viewBox="0 0 60 30" preserveAspectRatio="xMidYMid slice"><path d="M0 0h60v30H0z" fill="#012169" /><path d="m0 0 60 30M60 0 0 30" stroke="#FFFFFF" stroke-width="6" /><path d="m0 0 60 30M60 0 0 30" stroke="#C8102E" stroke-width="2.4" /><path d="M30 0v30M0 15h60" stroke="#FFFFFF" stroke-width="10" /><path d="M30 0v30M0 15h60" stroke="#C8102E" stroke-width="6" /></svg>
      </span>
      {{ bahasa.toUpperCase() }}
      <svg class="ms-bahasa-panah" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
           stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
    </button>

    <div v-if="buka" class="ms-bahasa-menu" role="menu">
      <button v-for="b in PILIHAN" :key="b" type="button" role="menuitemradio" :aria-checked="bahasa === b"
              class="ms-bahasa-butir" :class="{ kini: bahasa === b }" @click="ganti(b)">
        <span class="ms-bendera" aria-hidden="true">
          <svg v-if="b === 'id'" viewBox="0 0 2 2" preserveAspectRatio="none"><path d="M0 0h2v1H0z" fill="#E2232F" /><path d="M0 1h2v1H0z" fill="#FFFFFF" /></svg>
          <svg v-else viewBox="0 0 60 30" preserveAspectRatio="xMidYMid slice"><path d="M0 0h60v30H0z" fill="#012169" /><path d="m0 0 60 30M60 0 0 30" stroke="#FFFFFF" stroke-width="6" /><path d="m0 0 60 30M60 0 0 30" stroke="#C8102E" stroke-width="2.4" /><path d="M30 0v30M0 15h60" stroke="#FFFFFF" stroke-width="10" /><path d="M30 0v30M0 15h60" stroke="#C8102E" stroke-width="6" /></svg>
        </span>
        {{ t(b === 'en' ? 'bahasa.en' : 'bahasa.id') }}
        <svg v-if="bahasa === b" class="ms-bahasa-centang" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
      </button>
    </div>
  </div>
</template>
