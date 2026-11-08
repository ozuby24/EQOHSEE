<script setup lang="ts">
/**
 * "Perlu Approval Saya / Semua" — saklar daftar admin Safe Track
 * (72250f7, 9305535). Bawaannya "Perlu Approval Saya", sama dengan angka
 * lonceng; "Semua" memperlihatkan seluruh yang masih berjalan.
 */
import '../../../css/miners.css';
import { Link } from '@inertiajs/vue3';

defineProps<{
  saya: number;
  semua: number;
  aktif: 'saya' | 'semua';
  hrefSaya: string;
  hrefSemua: string;
}>();
</script>

<template>
  <div class="mn-pilih" role="group" aria-label="Saring antrean">
    <Link :href="hrefSaya" :class="{ 'mn-kini': aktif === 'saya' }" preserve-scroll>
      Perlu Approval Saya <b>{{ saya }}</b>
    </Link>
    <Link :href="hrefSemua" :class="{ 'mn-kini': aktif === 'semua' }" preserve-scroll>
      Semua <b>{{ semua }}</b>
    </Link>
  </div>
</template>
