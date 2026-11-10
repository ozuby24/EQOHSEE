<script setup lang="ts">
/** Sel orang pada tabel: foto/inisial bersudut, nama tebal, nomor redup di bawahnya. */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { inisial } from './ikon';

const props = withDefaults(defineProps<{
  nama?: string | null;
  sub?: string | null;
  foto?: string | null;
  href?: string | null;
}>(), { nama: null, sub: null, foto: null, href: null });

const huruf = computed(() => inisial(props.nama));
</script>

<template>
  <span class="mn-orang">
    <span class="mn-avatar" aria-hidden="true"><img v-if="foto" :src="foto" alt=""><template v-else>{{ huruf }}</template></span>
    <span class="min-w-0">
      <Link v-if="href" :href="href" class="mn-orang-nama mn-tautan">{{ nama ?? '—' }}</Link>
      <span v-else class="mn-orang-nama">{{ nama ?? '—' }}</span>
      <span v-if="sub" class="mn-orang-sub">{{ sub }}</span>
    </span>
  </span>
</template>
