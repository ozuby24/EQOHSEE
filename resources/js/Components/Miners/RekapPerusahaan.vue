<script setup lang="ts">
/**
 * "… per Perusahaan" — rekap.blade.php Safe Track: satu baris per
 * perusahaan, kolom zona, bilah sebaran, dan baris total di kaki.
 * Kepala kolom zona memakai titik warna + teks (bukan teks berwarna),
 * dan angka nol diredupkan supaya angka yang perlu ditindak menonjol.
 */
import '../../../css/miners.css';
import { computed } from 'vue';
import BarSebaran from './BarSebaran.vue';
import Kartu from './Kartu.vue';
import { ZONA, type BarisRekap, type KunciZona } from './zona';

const props = withDefaults(defineProps<{
  judul: string;
  baris: BarisRekap[];
  pakaiAktif?: boolean;
}>(), { pakaiAktif: false });

const ZONA_KOLOM: [KunciZona, string][] = [['expired', 'Expired'], ['kritis', '≤ 30 HR'], ['waspada', '31-60 HR'], ['aman', '> 60 HR']];

const total = computed(() => {
  const t = { perusahaan: 'Total', total: 0, aktif: 0, nonaktif: 0, expired: 0, kritis: 0, waspada: 0, aman: 0, kosong: 0 };
  for (const b of props.baris) {
    t.total += b.total; t.aktif += b.aktif ?? 0; t.nonaktif += b.nonaktif ?? 0;
    t.expired += b.expired; t.kritis += b.kritis; t.waspada += b.waspada; t.aman += b.aman; t.kosong += b.kosong;
  }
  return t;
});
</script>

<template>
  <Kartu :judul="judul" keterangan="Sebaran masa berlaku">
    <template #kanan>
      <div class="mn-legenda" aria-hidden="true">
        <span v-for="z in (['expired', 'kritis', 'waspada', 'aman', 'kosong'] as KunciZona[])" :key="z"><i :class="`mn-bg-${z}`" />{{ ZONA[z].pendek }}</span>
      </div>
    </template>

    <div class="mn-gulir">
      <table class="mn-tabel mn-rekap">
        <thead>
          <tr>
            <th>Perusahaan</th>
            <th class="mn-angka">Total</th>
            <template v-if="pakaiAktif">
              <th class="mn-angka">Aktif</th>
              <th class="mn-angka">Non-aktif</th>
            </template>
            <th v-for="[z, l] in ZONA_KOLOM" :key="z" class="mn-angka"><span class="mn-th-zona"><i :class="`mn-bg-${z}`" aria-hidden="true" />{{ l }}</span></th>
            <th class="mn-angka">Belum diisi</th>
            <th>Sebaran</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in baris" :key="b.perusahaan">
            <td class="mn-rekap-nama">{{ b.perusahaan }}</td>
            <td class="mn-angka"><b>{{ b.total }}</b></td>
            <template v-if="pakaiAktif">
              <td class="mn-angka mn-td-aktif">{{ b.aktif ?? 0 }}</td>
              <td class="mn-angka mn-td-nonaktif">{{ b.nonaktif ?? 0 }}</td>
            </template>
            <td v-for="[z] in ZONA_KOLOM" :key="z" class="mn-angka" :class="{ 'mn-nol': !b[z] }">{{ b[z] }}</td>
            <td class="mn-angka" :class="{ 'mn-nol': !b.kosong }">{{ b.kosong }}</td>
            <td class="mn-sebaran"><BarSebaran :n="b" /></td>
          </tr>
          <tr v-if="!baris.length">
            <td :colspan="pakaiAktif ? 10 : 8" class="mn-kosong">Belum ada data.</td>
          </tr>
        </tbody>
        <tfoot v-if="baris.length > 1">
          <tr>
            <td>Total</td>
            <td class="mn-angka">{{ total.total }}</td>
            <template v-if="pakaiAktif">
              <td class="mn-angka">{{ total.aktif }}</td>
              <td class="mn-angka">{{ total.nonaktif }}</td>
            </template>
            <td v-for="[z] in ZONA_KOLOM" :key="z" class="mn-angka">{{ total[z] }}</td>
            <td class="mn-angka">{{ total.kosong }}</td>
            <td class="mn-sebaran"><BarSebaran :n="total" /></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </Kartu>
</template>
