<script setup lang="ts">
/**
 * "Daftar …" — tabel data Safe Track: judul dengan jumlah data, baris
 * alat (rentang tanggal, ekspor, tombol tambah), nomor urut di kiri.
 *
 * Kepala berlatar redup berhuruf kecil, baris lega ber-hover. Tabel yang
 * tidak muat ditumpuk menjadi kartu oleh tabelPas.ts milik EQOHSEE.
 *
 * Sel tertentu digambar halaman pemakainya lewat slot `sel-<kunci>`;
 * yang tidak punya slot menampilkan nilainya apa adanya.
 */
import '../../../css/miners.css';
import Kartu from './Kartu.vue';
import Halaman from './Halaman.vue';
import KeadaanKosong from './KeadaanKosong.vue';
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

    <div v-if="$slots.alat" class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <slot name="alat" />
    </div>

    <div class="mn-gulir">
      <table class="mn-tabel">
        <thead>
          <tr>
            <th class="mn-kolom-sempit">No</th>
            <th v-for="k in kolom" :key="k.kunci" :class="k.kelas">{{ k.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(b, i) in baris" :key="b[kunciBaris] ?? i">
            <td class="mn-no">{{ nomorAwal + i }}</td>
            <td v-for="k in kolom" :key="k.kunci" :class="k.kelas">
              <slot :name="`sel-${k.kunci}`" :baris="b" :nilai="b[k.kunci]">
                {{ b[k.kunci] ?? '—' }}
              </slot>
            </td>
          </tr>
          <tr v-if="!baris.length">
            <td :colspan="kolom.length + 1" class="mn-kosong"><KeadaanKosong :teks="kosong" /></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="tautan && tautan.length > 3" class="mn-paginasi">
      <Halaman :tautan="tautan" />
    </div>
    <slot name="kaki" />
  </Kartu>
</template>
