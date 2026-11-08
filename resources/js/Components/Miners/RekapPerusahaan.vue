<script setup lang="ts">
/**
 * "… per Perusahaan | sebaran masa berlaku" — rekap.blade.php Safe Track:
 * satu baris per perusahaan, kolom zona berwarna, dan bilah sebaran.
 */
import '../../../css/miners.css';
import BarSebaran from './BarSebaran.vue';
import Kartu from './Kartu.vue';
import type { BarisRekap } from './zona';

withDefaults(defineProps<{
  judul: string;
  baris: BarisRekap[];
  pakaiAktif?: boolean;
}>(), { pakaiAktif: false });
</script>

<template>
  <Kartu :judul="judul" keterangan="sebaran masa berlaku">
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
            <th class="mn-angka mn-th-expired">Expired</th>
            <th class="mn-angka mn-th-kritis">≤ 30 HR</th>
            <th class="mn-angka mn-th-waspada">31-60 HR</th>
            <th class="mn-angka mn-th-aman">&gt; 60 HR</th>
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
            <td class="mn-angka">{{ b.expired }}</td>
            <td class="mn-angka">{{ b.kritis }}</td>
            <td class="mn-angka">{{ b.waspada }}</td>
            <td class="mn-angka">{{ b.aman }}</td>
            <td class="mn-angka">{{ b.kosong }}</td>
            <td style="min-width:180px"><BarSebaran :n="b" /></td>
          </tr>
          <tr v-if="!baris.length">
            <td :colspan="pakaiAktif ? 10 : 8" class="mn-kosong">Belum ada data.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </Kartu>
</template>
