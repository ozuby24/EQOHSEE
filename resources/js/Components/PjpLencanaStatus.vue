<script setup lang="ts">
/**
 * Lencana status PJP.
 *
 * Warnanya sepadan dengan segmen PjpBilahStatus supaya satu status
 * dikenali dari warna yang sama di mana pun ia muncul. "Tidak aktif"
 * sengaja abu-abu, bukan merah: PJP yang memang sudah tidak bekerja lagi
 * bukan keadaan yang perlu ditindaklanjuti, dan mewarnainya merah
 * membuat daftar tindak lanjut penuh oleh baris yang tidak menuntut apa pun.
 */
defineProps<{ status: string; label?: string | null }>();

const WARNA: Record<string, string> = {
  aktif:               'bg-emerald-100 text-emerald-700',
  perlu_tindak_lanjut: 'bg-amber-100 text-amber-700',
  tidak_aktif:         'bg-stone-200 text-stone-600',
};
</script>

<template>
  <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold whitespace-nowrap"
        :class="WARNA[status] ?? 'bg-stone-100 text-stone-600'">
    {{ label ?? status }}
  </span>
</template>
