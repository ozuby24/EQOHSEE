<script setup lang="ts">
/**
 * Daftar definisi dua (atau tiga) kolom untuk halaman rincian —
 * pengganti tampilan "input abu-abu disabled" Safe Track. Isinya
 * <Butir label="…">nilai</Butir>.
 */
import '../../../css/miners.css';
withDefaults(defineProps<{ kolom?: 2 | 3 }>(), { kolom: 2 });
</script>

<template>
  <dl class="mn-dl" :class="{ 'mn-dl-3': kolom === 3 }"><slot /></dl>
</template>
