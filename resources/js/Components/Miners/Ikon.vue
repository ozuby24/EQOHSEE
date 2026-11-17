<script setup lang="ts">
/** Satu ikon 16×16 dari ikon.ts; selalu dekoratif — teksnya ada di sebelahnya. */
defineProps<{ d: string }>();
</script>

<template>
  <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path :d="d" fill-rule="evenodd" /></svg>
</template>
