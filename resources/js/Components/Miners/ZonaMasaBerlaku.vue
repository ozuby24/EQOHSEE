<script setup lang="ts">
/**
 * Panel "Masa Berlaku …" — filter.blade.php Safe Track: kartu zona yang
 * sekaligus tombol saring, ringkasan manpower di kanan, dan baris saring
 * di bawahnya (slot bawaan).
 *
 * Tiap kartu memuat ikon zona + angka + label + persen terhadap total
 * dengan bilah tipis, supaya zona terbaca tanpa bergantung pada warna.
 */
import { ZONA, URUTAN_ZONA, type KunciZona } from './zona';
import { IKON, IKON_ZONA } from './ikon';
import Ikon from './Ikon.vue';
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
const persen = (n: number | undefined) => {
  const t = props.ringkas.total ?? 0;
  return t ? Math.round(((n ?? 0) / t) * 100) : 0;
};
/* Lebar bilah lewat kelas w-[..] tidak mungkin (nilainya dinamis); yang
   tersisa hanya gaya sebaris untuk lebar saja — warnanya tetap di CSS. */
const lebar = (n: number | undefined) => ({ width: `${persen(n)}%` });

/* Mengklik kartu yang sedang aktif melepas saringnya — seperti tautan
   zona Safe Track yang menghapus ?zona= bila diklik dua kali. */
function pilih(z: KunciZona | null) {
  emit('pilih', z === null || props.aktif === z ? null : z);
}
</script>

<template>
  <section class="mn-kartu">
    <header class="mn-kartu-kepala">
      <h2 class="mn-kartu-judul">{{ judul }}<small>Klik kartu untuk menyaring</small></h2>
      <div v-if="manpower" class="mn-manpower">
        <span><b>{{ angka(manpower.total) }}</b> {{ satuan }}</span>
        <span class="mn-ok"><i aria-hidden="true" /><b>{{ angka(manpower.aktif) }}</b> aktif</span>
        <span><i aria-hidden="true" /><b>{{ angka(manpower.nonaktif) }}</b> tidak aktif</span>
      </div>
    </header>

    <div class="mn-zona-grid">
      <button type="button" class="mn-zona mn-zona-total" :class="{ 'mn-aktif': !aktif }" :aria-pressed="!aktif" @click="pilih(null)">
        <span class="mn-zona-atas">
          <span class="mn-zona-ikon"><Ikon :d="IKON.orang" /></span>
          <span class="mn-zona-persen">100%</span>
        </span>
        <span class="mn-zona-num">{{ angka(ringkas.total) }}</span>
        <span class="mn-zona-lbl">Semua data</span>
        <span class="mn-zona-sub">Tanpa saringan zona</span>
      </button>
      <button v-for="z in URUTAN_ZONA" :key="z" type="button" class="mn-zona" :class="[`mn-zona-${z}`, { 'mn-aktif': aktif === z }]"
              :aria-pressed="aktif === z" @click="pilih(z)">
        <span class="mn-zona-atas">
          <span class="mn-zona-ikon"><Ikon :d="IKON_ZONA[z]" /></span>
          <span class="mn-zona-persen">{{ persen(ringkas[z]) }}%</span>
        </span>
        <span class="mn-zona-num">{{ angka(ringkas[z]) }}</span>
        <span class="mn-zona-lbl">{{ ZONA[z].pendek }}</span>
        <span class="mn-zona-sub">{{ ZONA[z].label }}</span>
        <span class="mn-zona-bilah" aria-hidden="true"><i :style="lebar(ringkas[z])" /></span>
      </button>
    </div>

    <slot />
  </section>
</template>
