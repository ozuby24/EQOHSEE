<script setup lang="ts">
/** Satu baris daftar definisi: label redup, nilai tegas; kosong tampil "—". */
withDefaults(defineProps<{
  label: string;
  nilai?: string | number | null;
  penuh?: boolean;
}>(), { nilai: null, penuh: false });
</script>

<template>
  <div class="mn-dl-butir" :class="{ 'mn-penuh': penuh }">
    <dt>{{ label }}</dt>
    <dd><slot>{{ nilai === null || nilai === undefined || nilai === '' ? '—' : nilai }}</slot></dd>
  </div>
</template>
