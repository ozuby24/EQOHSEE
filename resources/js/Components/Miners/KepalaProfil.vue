<script setup lang="ts">
/**
 * Kepala profil halaman rincian (MCU, Induksi, Mine Permit, perpanjangan,
 * SIMPER, pekerja): foto atau inisial, nama/nomor dokumen, baris
 * keterangan (No ID · jabatan · perusahaan), pil status + masa berlaku
 * (slot `lencana`), dan tombol aksi utama di kanan (slot `aksi`).
 */
import '../../../css/miners.css';
import { computed } from 'vue';
import { inisial } from './ikon';

const props = withDefaults(defineProps<{
  judul: string;
  /** Pasangan [label, nilai]; yang kosong dilewati. */
  keterangan?: [string, string | number | null | undefined][];
  foto?: string | null;
  /** Teks untuk inisial bila tanpa foto (bawaan: judul). */
  namaInisial?: string | null;
  eyebrow?: string | null;
}>(), { keterangan: () => [], foto: null, namaInisial: null, eyebrow: null });

const huruf = computed(() => inisial(props.namaInisial ?? props.judul));
const isi = computed(() => props.keterangan.filter(([, v]) => v !== null && v !== undefined && v !== ''));
</script>

<template>
  <section class="mn-profil">
    <span class="mn-profil-foto">
      <slot name="foto"><img v-if="foto" :src="foto" alt="Foto"><template v-else>{{ huruf }}</template></slot>
    </span>
    <div class="mn-profil-isi">
      <span v-if="eyebrow" class="mn-eyebrow">{{ eyebrow }}</span>
      <h2 class="mn-profil-nama">{{ judul }}</h2>
      <div v-if="isi.length" class="mn-profil-sub">
        <span v-for="[l, v] in isi" :key="l">{{ l }} <b>{{ v }}</b></span>
      </div>
      <div v-if="$slots.lencana" class="mn-profil-lencana"><slot name="lencana" /></div>
    </div>
    <div v-if="$slots.aksi" class="mn-profil-aksi"><slot name="aksi" /></div>
  </section>
</template>
