<script setup lang="ts">
/**
 * Satu lembar A4 dokumen terkendali. Kop dicetak ulang pada tiap lembar
 * karena nomor halaman harus ikut berubah — peramban tidak dapat
 * menghitungnya sendiri saat mencetak.
 */
import type { KopDok } from '../../smkp/types';

/** `polos`: tanpa kop — halaman judul laporan. */
defineProps<{ dok: KopDok; halaman: number; dari: number; lanskap?: boolean; polos?: boolean }>();
</script>

<template>
  <section class="smkp-lembar text-[11.5px] leading-relaxed" :class="{ 'smkp-lembar-lanskap': lanskap }">
    <table v-if="!polos" class="smkp-kop w-full border-collapse text-[10px] mb-5">
      <tbody>
        <tr>
          <td class="p-2 w-[17%] text-center align-middle">
            <img v-if="dok.logo" :src="dok.logo" alt="" class="h-11 w-auto max-w-full object-contain mx-auto">
            <span v-else class="font-bold text-[11px] text-navy-900 leading-tight">{{ dok.perusahaan }}</span>
          </td>
          <td class="p-2 text-center align-middle">
            <p class="font-bold uppercase tracking-wide text-slate-500 text-[10px]">{{ dok.jenis }}</p>
            <p class="font-bold uppercase text-navy-900 leading-snug mt-1 text-[11px]">{{ dok.judul }}</p>
          </td>
          <td class="p-2 w-[33%] align-middle">
            <table class="w-full">
              <tbody>
                <tr v-for="r in [['No. Dokumen', dok.nomor], ['Tgl Penerbitan', dok.terbit ?? '—'], ['Tgl Persetujuan', dok.setuju ?? '—'], ['No. Revisi', dok.revisi], ['Halaman', `${halaman} dari ${dari}`]]" :key="r[0]">
                  <td class="text-slate-500 whitespace-nowrap py-px border-0">{{ r[0] }}</td>
                  <td class="text-slate-400 px-1 py-px border-0">:</td>
                  <td class="font-semibold text-navy-900 py-px border-0 tabular-nums">{{ r[1] }}</td>
                </tr>
              </tbody>
            </table>
          </td>
        </tr>
        <tr>
          <td colspan="3" class="px-2 py-1.5">
            <p><span class="inline-block w-24 text-slate-500">Divisi</span>: {{ dok.divisi }}</p>
            <p><span class="inline-block w-24 text-slate-500">Departemen</span>: {{ dok.departemen }}</p>
          </td>
        </tr>
      </tbody>
    </table>
    <slot />
  </section>
</template>
