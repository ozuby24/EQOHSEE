<script setup lang="ts">
/**
 * Kontrak saya.
 *
 * RANTAI PERPANJANGANNYA DITAMPILKAN UTUH, bukan hanya yang berlaku.
 * Yang menentukan hak seseorang atas PKWT bukan kontrak yang sedang
 * dipegangnya, melainkan berapa lama seluruh rangkaiannya sudah
 * berjalan — dan orang yang hanya melihat kontrak terakhirnya tidak
 * pernah tahu bahwa rangkaiannya sudah melewati lima tahun.
 *
 * KOMPENSASI YANG DITAMPILKAN HANYA YANG SUDAH DIBAYAR. Yang belum
 * adalah taksiran, dan taksiran uang yang ditunjukkan kepada orang yang
 * akan menerimanya akan dibaca sebagai janji.
 */
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { propHalaman } from '../../../halaman';

const props = propHalaman();
const baris = computed<any[]>(() => (props.baris ?? []) as any[]);

function rupiah(n: number | null | undefined): string {
  if (n === null || n === undefined) return '—';
  return 'Rp ' + Math.round(Number(n)).toLocaleString('id-ID');
}
</script>

<template>
  <Head :title="props.judul as string" />

  <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
    <header class="px-4 pt-4 pb-3">
      <h2 class="text-[13px] font-bold">
        Riwayat kontrak <span class="text-stone-400 font-normal">| {{ baris.length }} baris</span>
      </h2>
      <p class="text-[11.5px] text-stone-500 mt-1">
        Termasuk yang sudah berakhir. Yang menentukan hak atas PKWT adalah panjang seluruh
        rangkaiannya, bukan kontrak yang sedang berlaku.
      </p>
    </header>

    <div class="overflow-x-auto">
      <table class="w-full text-[12px]">
        <thead class="bg-stone-50/70 text-[11px] text-stone-500">
          <tr>
            <th class="text-left font-medium px-4 py-2.5">Nomor</th>
            <th class="text-left font-medium px-3 py-2.5">Jenis</th>
            <th class="text-left font-medium px-3 py-2.5">Rentang</th>
            <th class="text-right font-medium px-3 py-2.5">Masa</th>
            <th class="text-right font-medium px-3 py-2.5">Kompensasi</th>
            <th class="text-left font-medium px-4 py-2.5">Status</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="b in baris" :key="b.nomor" class="border-t border-stone-100">
            <td class="px-4 py-2.5">
              <div class="num">{{ b.nomor }}</div>
              <div v-if="b.urutan > 1" class="text-[10.5px] text-stone-500">
                perpanjangan ke-{{ b.urutan - 1 }}
              </div>
            </td>

            <td class="px-3 py-2.5">{{ b.jenis }}</td>

            <td class="px-3 py-2.5 num text-[11.5px]">
              {{ b.mulai }} <span class="text-stone-400">–</span> {{ b.selesai ?? 'tanpa batas' }}
            </td>

            <td class="px-3 py-2.5 text-right num">
              {{ b.bulan ? b.bulan.toFixed(1).replace('.', ',') + ' bln' : '—' }}
            </td>

            <td class="px-3 py-2.5 text-right">
              <div v-if="b.kompensasi !== null" class="num">{{ rupiah(b.kompensasi) }}</div>
              <div v-else class="text-[11px] text-stone-400">—</div>
              <div v-if="b.dibayar" class="text-[10.5px] text-emerald-700">dibayar {{ b.dibayar }}</div>
            </td>

            <td class="px-4 py-2.5 text-[11.5px]">{{ b.status }}</td>
          </tr>

          <tr v-if="!baris.length">
            <td colspan="6" class="px-4 py-10 text-center text-[12px] text-stone-400">
              Belum ada kontrak yang tercatat atas nama Anda.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
