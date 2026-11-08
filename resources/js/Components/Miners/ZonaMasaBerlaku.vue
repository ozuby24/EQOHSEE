<script setup lang="ts">
/**
 * Panel "Masa Berlaku … | klik kartu untuk menyaring" — filter.blade.php
 * Safe Track: enam kartu zona yang sekaligus tombol saring, ringkasan
 * manpower di kanan, dan baris saring di bawahnya (slot bawaan).
 */
import { ZONA, URUTAN_ZONA, type KunciZona } from './zona';
import '../../../css/miners.css';

const props = withDefaults(defineProps<{
  judul: string;
  ringkas: Partial<Record<KunciZona | 'total', number>>;
  aktif?: string | null;
  manpower?: { total: number; aktif: number; nonaktif: number } | null;
  satuan?: string;
}>(), { aktif: null, manpower: null, satuan: 'manpower' });

const emit = defineEmits<{ pilih: [zona: string | null] }>();

const angka = (n: number | undefined) => (n ?? 0).toLocaleString('id-ID');

/* Mengklik kartu yang sedang aktif melepas saringnya — seperti tautan
   zona Safe Track yang menghapus ?zona= bila diklik dua kali. */
function pilih(z: KunciZona | null) {
  emit('pilih', z === null || props.aktif === z ? null : z);
}
</script>

<template>
  <section class="mn-kartu">
    <header class="mn-kartu-kepala">
      <h2 class="mn-kartu-judul">{{ judul }}<small> | klik kartu untuk menyaring</small></h2>
      <div v-if="manpower" class="mn-manpower">
        <span><b>{{ angka(manpower.total) }}</b> {{ satuan }}</span>
        <span class="mn-ok"><b>{{ angka(manpower.aktif) }}</b> aktif</span>
        <span class="mn-redup"><b>{{ angka(manpower.nonaktif) }}</b> tidak aktif</span>
      </div>
    </header>

    <div class="mn-zona-grid">
      <button type="button" class="mn-zona mn-zona-total" :class="{ 'mn-aktif': !aktif }" @click="pilih(null)">
        <span class="mn-zona-num">{{ angka(ringkas.total) }}</span>
        <span class="mn-zona-lbl">Semua data</span>
      </button>
      <button v-for="z in URUTAN_ZONA" :key="z" type="button" class="mn-zona" :class="[`mn-zona-${z}`, { 'mn-aktif': aktif === z }]"
              :aria-pressed="aktif === z" @click="pilih(z)">
        <span class="mn-zona-num">{{ angka(ringkas[z]) }}</span>
        <span class="mn-zona-lbl">{{ ZONA[z].pendek }}</span>
        <span class="mn-zona-sub">{{ ZONA[z].label }}</span>
      </button>
    </div>

    <slot />
  </section>
</template>
