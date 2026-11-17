<script setup lang="ts">
/**
 * Lencana keadaan absensi satu hari.
 *
 * SELALU BERLABEL, tidak pernah warna saja. Lima keadaan dibedakan
 * lima warna, dan pembaca yang tidak dapat membedakan merah dari
 * jingga — sekitar satu dari dua belas laki-laki — hanya melihat tiga.
 * Pada layar yang dipakai memutuskan siapa yang belum keluar site,
 * itu bukan ketidaknyamanan.
 *
 * Warnanya ditulis sebagai KELAS, bukan gaya sebaris, supaya aturan
 * mode gelap dapat menimpanya. Ditulis sebaris, lencana hijau muda
 * tetap hijau muda di atas latar #0D1417 dan tulisannya hilang.
 */
const props = defineProps<{
  keadaan?: string | null;
  label?: string | null;
}>();
</script>

<template>
  <span v-if="props.keadaan"
        class="ab-lencana"
        :class="'ab-' + props.keadaan">
    <span class="ab-titik" />
    {{ props.label ?? props.keadaan }}
  </span>
  <span v-else class="text-stone-400">—</span>
</template>

<style>
.ab-lencana {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  white-space: nowrap;
  border-radius: 9999px;
  padding: 0.125rem 0.5rem;
  font-size: 11px;
  font-weight: 500;
}

.ab-titik { width: 6px; height: 6px; border-radius: 9999px; background: currentColor; }

.ab-hadir        { background: #D1FAE5; color: #065F46; }
.ab-terlambat    { background: #FEF3C7; color: #78350F; }
.ab-belum_pulang { background: #DBEAFE; color: #1E3A5F; }
.ab-absen        { background: #FEE2E2; color: #7F1D1D; }
.ab-luar_roster  { background: #EDE9FE; color: #4C1D95; }

:root[data-tema="gelap"] .ab-hadir        { background: #143A2C; color: #8FE3BE; }
:root[data-tema="gelap"] .ab-terlambat    { background: #4A3810; color: #F6D488; }
:root[data-tema="gelap"] .ab-belum_pulang { background: #1E3F5E; color: #A8CDF0; }
:root[data-tema="gelap"] .ab-absen        { background: #4E1D1D; color: #F5A9A9; }
:root[data-tema="gelap"] .ab-luar_roster  { background: #34255E; color: #C8B6F5; }
</style>
