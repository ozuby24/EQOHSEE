<script setup lang="ts">
/**
 * Kartu Mine Permit / SIMPER satu orang — `print_card_permit` (depan) dan
 * `print_cover_permit` (belakang) Safe Track, dicetak lewat peramban pada
 * kertas CR80 (54 × 86 mm), satu sisi per halaman.
 *
 * Kartu tanpa foto TIDAK dicetak (Safe Track menolaknya): yang tercetak
 * hanya pesan untuk mengunggah foto lebih dulu.
 */
import { onBeforeUnmount, onMounted } from 'vue';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KartuCr80 from '../../Components/Miners/KartuCr80.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{ k: Record<string, any>; kembali?: string }>();

/* Kertas CR80 hanya selama halaman ini terbuka (lihat KartuLembar). */
let gaya: HTMLStyleElement | null = null;
onMounted(() => {
  gaya = document.createElement('style');
  gaya.textContent = '@media print { @page { size: 54mm 86mm; margin: 0; } }';
  document.head.appendChild(gaya);
});
onBeforeUnmount(() => gaya?.remove());
</script>

<template>
  <PrintShell :title="`Kartu ${props.k.nomor ?? ''}`" :kembali="props.kembali">
    <div v-if="!props.k.foto" class="kartu-pesan">
      Kartu {{ props.k.nama }} belum dapat dicetak: foto Mine Permit belum diunggah. Unggah fotonya di detail Mine Permit.
    </div>
    <div v-else class="kartu-satu">
      <div class="kartu-halaman"><KartuCr80 :k="props.k" sisi="depan" /></div>
      <div class="kartu-halaman"><KartuCr80 :k="props.k" sisi="belakang" /></div>
    </div>
  </PrintShell>
</template>

<style scoped>
.kartu-satu { display: flex; flex-wrap: wrap; gap: 10mm; justify-content: center; padding: 10mm 0; }
.kartu-halaman { box-shadow: 0 1px 6px rgba(0,0,0,.18); border-radius: 3mm; }
.kartu-pesan { max-width: 520px; margin: 40px auto; padding: 16px; border: 1px solid #e5b4b4; background: #fff5f5; border-radius: 10px; font-size: 14px; }
@media print {
  .kartu-satu { display: block; padding: 0; }
  .kartu-halaman { box-shadow: none; break-after: page; page-break-after: always; }
}
</style>
