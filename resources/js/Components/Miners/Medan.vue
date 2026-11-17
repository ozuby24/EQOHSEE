<script setup lang="ts">
/** Satu medan formulir berlabel, dengan tanda wajib, petunjuk, dan galat. */
import '../../../css/miners.css';
defineProps<{
  label: string;
  untuk?: string;
  wajib?: boolean;
  petunjuk?: string | null;
  galat?: string | null;
  penuh?: boolean;
}>();
</script>

<template>
  <div class="mn-medan" :class="{ 'mn-penuh': penuh }">
    <label class="mn-label" :for="untuk">{{ label }}<span v-if="wajib" class="mn-wajib"> *</span></label>
    <slot />
    <span v-if="petunjuk" class="mn-petunjuk">{{ petunjuk }}</span>
    <span v-if="galat" class="mn-galat">{{ galat }}</span>
  </div>
</template>
