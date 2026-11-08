<script setup lang="ts">
import { computed } from 'vue';
import { IKON } from '../../smkp/lib/ikon';

const props = withDefaults(defineProps<{ nama: string; tebal?: number }>(), { tebal: 1.75 });
const isi = computed(() => IKON[props.nama] ?? IKON.info);
</script>

<template>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="tebal"
       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" v-html="isi" />
</template>
