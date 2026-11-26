<script setup lang="ts">
/* Lencana nilai berstatus MERANTI — kategori skor, status, P0/P1/P2, golongan. */
import { computed } from 'vue';
import { kelasLencana } from '../../meranti';

const props = defineProps<{ nilai: unknown }>();
const kelas = computed(() => kelasLencana(props.nilai));
</script>

<template>
  <span v-if="nilai !== null && nilai !== undefined && nilai !== ''" class="mr-lencana" :class="kelas">{{ nilai }}</span>
  <span v-else class="text-stone-400">—</span>
</template>

<style scoped>
.mr-lencana { display: inline-flex; align-items: center; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 800; white-space: nowrap; border: 1px solid transparent; }
.mr-hijau { background: #E8F5EC; color: #1F6B42; border-color: #C9E7D3; }
.mr-tua { background: #E3EFE8; color: #0A3A2A; border-color: #C3DACB; }
.mr-emas { background: #FBF3D9; color: #7A5D0B; border-color: #F0DE9F; }
.mr-merah { background: #FDECEC; color: #991B1B; border-color: #F8C9C9; }
.mr-biru { background: #EAF1FB; color: #1E4E8C; border-color: #C9DAF3; }
.mr-toska { background: #E3F4F4; color: #1D6B6B; border-color: #BFE3E3; }
.mr-cokelat { background: #F7EDE3; color: #7C4A1E; border-color: #EBD3BC; }
.mr-abu { background: #F1F5F9; color: #475569; border-color: #E2E8F0; }
.mr-p0 { background: #E5F4EB; color: #1F6B42; border-color: #4CA771; }
.mr-p1 { background: #FBF3D9; color: #7A5D0B; border-color: #C9A227; }
.mr-p2 { background: #E3F4F4; color: #1D6B6B; border-color: #2D8C8C; }
:root[data-tema="gelap"] .mr-lencana { filter: saturate(.9); background-color: rgba(255, 255, 255, .06); color: #D5E3DA; border-color: rgba(255, 255, 255, .14); }
</style>
