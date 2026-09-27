<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import IkonLapangan from './IkonLapangan.vue';
import { jaringan } from '../lapangan/kotakKeluar';

/**
 * Bilah keadaan sinyal dan kiriman tertunda.
 *
 * Diam bila semuanya beres — bilah yang selalu ada berhenti dibaca.
 * Muncul hanya ketika ada yang perlu diketahui: tidak ada sinyal, ada
 * kiriman yang sedang menunggu atau sedang dikirim, atau ada kiriman
 * yang ditolak server.
 */
const p = withDefaults(defineProps<{ gelap?: boolean; formulir?: boolean }>(), { gelap: false, formulir: false });

const isi = computed(() => {
  const n = jaringan.menunggu;
  if (!jaringan.daring) {
    if (p.formulir) return { nada: 'luring', teks: 'Luring — kiriman disimpan di perangkat dan terkirim saat sinyal kembali.' };
    return { nada: 'luring', teks: n ? `Luring · ${n} ${n === 1 ? 'kiriman' : 'kiriman'} menunggu sinkron` : 'Luring · menampilkan data terakhir yang tersimpan' };
  }
  if (jaringan.mengirim && n) return { nada: 'kirim', teks: `Mengirim ${n} kiriman tertunda…` };
  if (n) return { nada: 'kirim', teks: `${n} kiriman menunggu dikirim ulang` };
  if (jaringan.gagal) return { nada: 'gagal', teks: `${jaringan.gagal} kiriman ditolak server — buka Tugas untuk melihat alasannya` };
  return null;
});
</script>

<template>
  <component :is="isi?.nada === 'gagal' ? Link : 'div'" v-if="isi" :href="isi?.nada === 'gagal' ? '/lapangan/tugas' : undefined"
             class="lp-luring lp-muncul" :class="[gelap && isi.nada === 'luring' ? 'gelap' : '', isi.nada === 'kirim' ? 'kirim' : '', isi.nada === 'gagal' ? 'gagal' : '']"
             role="status" aria-live="polite">
    <IkonLapangan :nama="isi.nada === 'luring' ? 'luring' : isi.nada === 'kirim' ? 'kirimUlang' : 'bahaya'" />
    <span>{{ isi.teks }}</span>
  </component>
</template>
