<script setup lang="ts">
/**
 * Kepala halaman Miners — remah roti kecil di atas judul tegas, tombol
 * aksi halaman di kanan (pola kepala halaman SMKP). Istilah dan isi
 * remahnya tetap Safe Track ("Home / Data MCU / Manpower").
 *
 * Menggantikan kop bersampul EQOHSEE pada halaman Miners: halaman yang
 * memakainya mengirim `kop: false` dari controllernya. Bilah samping dan
 * bilah atas EQOHSEE tetap — yang berganti hanya kepala halamannya.
 */
import { Head, Link } from '@inertiajs/vue3';
import '../../../css/miners.css';

defineProps<{
  judul: string;
  /** Remah roti: [label, alamat|null]; yang terakhir adalah halaman ini. */
  remah?: [string, string | null][];
}>();
</script>

<template>
  <Head :title="judul" />

  <div class="mn-kepala">
    <div class="min-w-0">
      <nav v-if="remah?.length" class="mn-remah" aria-label="Remah roti">
        <template v-for="([label, href], i) in remah" :key="i">
          <i v-if="i > 0" aria-hidden="true">/</i>
          <Link v-if="href" :href="href">{{ label }}</Link>
          <span v-else :class="{ 'mn-remah-kini': i === remah.length - 1 }">{{ label }}</span>
        </template>
      </nav>
      <h1>{{ judul }}</h1>
    </div>

    <div v-if="$slots.aksi" class="mn-kepala-aksi">
      <slot name="aksi" />
    </div>
  </div>
</template>
