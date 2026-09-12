<script setup lang="ts">
/**
 * Daftar menyilang orang — antrean, pengajuan lanjutan, rujukan.
 *
 * Menjawab "apa yang menunggu keputusan pagi ini". Bagi yang bukan
 * peninjau daftarnya kosong, dan itu jawaban yang BENAR — bukan
 * kerusakan. Layar kosong tanpa keterangan terbaca sebagai yang
 * ketiga, jadi keterangannya ditulis.
 */
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { propHalaman } from '../../halaman';
import Lencana from './Lencana.vue';

const props = propHalaman();

const baris = computed<any[]>(() => (props.baris ?? []) as any[]);
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1200px] mx-auto space-y-5">

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold w-10">No</th>
              <th class="px-4 py-2 font-semibold">Nama</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Nomor</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Tanggal</th>
              <th class="px-4 py-2 font-semibold">Keterangan</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(b, i) in baris" :key="b.id" class="border-b border-stone-100 hover:bg-stone-50/60">
              <td class="px-4 py-2.5 num text-stone-400">{{ i + 1 }}</td>
              <td class="px-4 py-2.5">
                <Link v-if="b.pekerja_id" :href="`/miners/${b.pekerja_id}`"
                      class="font-semibold text-cam-lime-deep hover:underline">{{ b.nama || '—' }}</Link>
                <span v-else class="font-semibold text-cam-ink">{{ b.nama || '—' }}</span>
              </td>
              <td class="px-4 py-2.5 num text-stone-500">{{ b.nomor || '—' }}</td>
              <td class="px-4 py-2.5 num">{{ b.tanggal || '—' }}</td>
              <td class="px-4 py-2.5 text-stone-600">{{ b.ket || '—' }}</td>
            </tr>

            <tr v-if="!baris.length">
              <td colspan="5" class="px-4 py-10 text-center text-stone-400">
                Tidak ada yang menunggu. Kalau Anda bukan peninjau, daftar ini memang kosong.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
