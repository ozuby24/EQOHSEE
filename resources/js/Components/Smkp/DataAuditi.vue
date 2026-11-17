<script setup lang="ts">
import type { RingkasAudit } from '../../smkp/types';

defineProps<{ audit: RingkasAudit; ketua?: boolean }>();
</script>

<template>
  <table class="w-full text-[11.5px]">
    <tbody class="divide-y divide-slate-100">
      <tr v-for="r in [
            ['Nama perusahaan auditi', audit.perusahaan.nama],
            ['Jenis perizinan', audit.perusahaan.izin || '—'],
            ['Jenis komoditas', audit.perusahaan.komoditas || '—'],
            ['Alamat perusahaan', audit.perusahaan.alamat || '—'],
            ['Periode audit', audit.periode],
            ...(ketua ? [['Ketua tim audit', audit.ketua || '—']] : []),
          ]" :key="r[0]">
        <td class="py-1.5 pr-3 text-slate-500 w-52 align-top">{{ r[0] }}</td>
        <td class="py-1.5 text-navy-900 whitespace-pre-line">{{ r[1] }}</td>
      </tr>
    </tbody>
  </table>
</template>
