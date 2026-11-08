<script setup lang="ts">
/**
 * "Daftar … | n data" — tabel data Safe Track: judul berketerangan
 * jumlah, baris alat (rentang tanggal, ekspor, tombol tambah), kepala
 * tebal tanpa latar, garis tipis antarbaris, dan nomor urut di kiri.
 *
 * Sel tertentu digambar halaman pemakainya lewat slot `sel-<kunci>`;
 * yang tidak punya slot menampilkan nilainya apa adanya.
 */
import '../../../css/miners.css';
import Kartu from './Kartu.vue';
import Halaman from './Halaman.vue';
import type { KolomTabel, TautanHalaman } from './zona';

withDefaults(defineProps<{
  judul: string;
  jumlah?: number | null;
  kolom: KolomTabel[];
  baris: Record<string, any>[];
  nomorAwal?: number;
  kosong?: string;
  tautan?: TautanHalaman[] | null;
  kunciBaris?: string;
}>(), { jumlah: null, nomorAwal: 1, kosong: 'Tidak ada data yang cocok dengan saringan ini.', tautan: null, kunciBaris: 'id' });
</script>

<template>
  <Kartu :judul="judul" :keterangan="jumlah === null ? null : `${jumlah.toLocaleString('id-ID')} data`">
    <template v-if="$slots.kanan" #kanan><slot name="kanan" /></template>

    <div v-if="$slots.alat" class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <slot name="alat" />
    </div>

    <div class="mn-gulir">
      <table class="mn-tabel">
        <thead>
          <tr>
            <th style="width:42px">No</th>
            <th v-for="k in kolom" :key="k.kunci" :class="k.kelas">{{ k.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(b, i) in baris" :key="b[kunciBaris] ?? i">
            <td>{{ nomorAwal + i }}</td>
            <td v-for="k in kolom" :key="k.kunci" :class="k.kelas">
              <slot :name="`sel-${k.kunci}`" :baris="b" :nilai="b[k.kunci]">
                {{ b[k.kunci] ?? '—' }}
              </slot>
            </td>
          </tr>
          <tr v-if="!baris.length">
            <td :colspan="kolom.length + 1" class="mn-kosong">{{ kosong }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Halaman v-if="tautan && tautan.length > 3" :tautan="tautan" />
    <slot name="kaki" />
  </Kartu>
</template>
