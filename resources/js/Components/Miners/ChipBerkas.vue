<script setup lang="ts">
/**
 * Chip berkas: ikon jenis (PDF/gambar/lainnya), nama berkas, dan
 * "Lihat". Tanpa alamat, chipnya bergaris putus "belum diunggah".
 */
import '../../../css/miners.css';
import { computed } from 'vue';

const props = withDefaults(defineProps<{
  href?: string | null;
  nama?: string | null;
  aksi?: string;
}>(), { href: null, nama: null, aksi: 'Lihat' });

const ekstensi = computed(() => {
  const sumber = (props.nama || props.href || '').split('?')[0];
  const m = sumber.match(/\.([a-z0-9]{2,4})$/i);
  return m ? m[1].toLowerCase() : '';
});
const jenis = computed(() => ekstensi.value === 'pdf' ? 'pdf' : ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ekstensi.value) ? 'gbr' : 'lain');
const tampil = computed(() => props.nama || (props.href ? decodeURIComponent(props.href.split('?')[0].split('/').pop() ?? 'Berkas') : 'Belum diunggah'));
</script>

<template>
  <a v-if="href" :href="href" target="_blank" rel="noopener" class="mn-berkas" :class="`mn-berkas-${jenis}`" :title="tampil">
    <span class="mn-berkas-ikon" aria-hidden="true">{{ (ekstensi || 'file').toUpperCase().slice(0, 4) }}</span>
    <span class="mn-berkas-nama">{{ tampil }}</span>
    <span class="mn-berkas-aksi">{{ aksi }}</span>
  </a>
  <span v-else class="mn-berkas mn-berkas-kosong">
    <span class="mn-berkas-ikon" aria-hidden="true">—</span>
    <span class="mn-berkas-nama">{{ nama ?? 'Belum diunggah' }}</span>
  </span>
</template>
