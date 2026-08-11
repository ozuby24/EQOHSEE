<script setup lang="ts">
/**
 * Lencana kategori tingkat kematangan.
 *
 * Sepadan dengan resources/views/tpkkp/_badge.blade.php. Warnanya datang
 * dari server, bukan dipetakan ulang di sini — peta label→warna yang
 * ditulis di klien sudah sekali terbukti melenceng dari acuan tanpa
 * menimbulkan galat sama sekali, dan yang terlihat hanyalah lencana yang
 * menyebut tingkat yang salah.
 *
 * Kategori null berarti belum dinilai: abu-abu, bukan warna tingkat
 * terendah. Memakai warna 'Dasar' untuk yang belum dinilai membuat item
 * yang belum disentuh tampak sudah dinilai buruk.
 */
defineProps<{
  kategori: string | null;
  warna?: string | null;
}>();
</script>

<template>
  <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full text-white whitespace-nowrap"
        :style="{ background: kategori && warna ? warna : '#a8a29e' }">
    {{ kategori ?? 'Belum dinilai' }}
  </span>
</template>
