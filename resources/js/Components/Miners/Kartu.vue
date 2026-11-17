<script setup lang="ts">
/**
 * Kartu berjudul. Judul dan keterangannya tetap kata Safe Track
 * ("Daftar Manpower" · "30 data"); keterangan kini baris kecil di bawah
 * judul, bukan "Judul | keterangan" sebaris ala NiceAdmin.
 */
import '../../../css/miners.css';

defineProps<{
  judul?: string;
  keterangan?: string | null;
}>();
</script>

<template>
  <section class="mn-kartu">
    <header v-if="judul || $slots.kanan" class="mn-kartu-kepala">
      <h2 v-if="judul" class="mn-kartu-judul">
        {{ judul }}<small v-if="keterangan">{{ keterangan }}</small>
      </h2>
      <div v-if="$slots.kanan" class="flex flex-wrap items-center gap-2">
        <slot name="kanan" />
      </div>
    </header>

    <slot />
  </section>
</template>
