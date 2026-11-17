<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Ikon from '../Ikon.vue';
import type { AsistenBersama } from '../../../smkp/types';

/* Pemicu laci asisten. Tetap dapat ditekan saat nonaktif — lacinya menjelaskan sebabnya. */
withDefaults(defineProps<{ label?: string }>(), { label: 'Asisten AI' });
defineEmits<{ click: [] }>();

const page = usePage<any>();
const asisten = computed<AsistenBersama>(() => page.props.asisten ?? { aktif: false, model: '' });
</script>

<template>
  <button type="button" class="btn-garis btn-kecil" :class="{ 'opacity-60': !asisten.aktif }"
          :title="asisten.aktif ? 'Minta saran Asisten AI' : 'Asisten AI belum diaktifkan administrator'" @click="$emit('click')">
    <Ikon nama="kilau" class="h-3.5 w-3.5" /> {{ label }}
  </button>
</template>
