<script setup lang="ts">
import { statusKelas, statusLabel } from '../../energi';

defineProps<{ status: unknown; label?: string | null }>();
</script>

<template>
  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10.5px] font-bold whitespace-nowrap" :class="statusKelas(status)">
    {{ label ?? statusLabel(status) }}
  </span>
</template>
