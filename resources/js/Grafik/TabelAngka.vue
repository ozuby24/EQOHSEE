<script setup lang="ts">
/**
 * Tabel angka di balik sebuah grafik — isi slot `#tabel` KartuGrafik.
 *
 * Grafik menjawab bentuk; tabel menjawab angka persisnya, dan menjadi
 * pengganti grafik di lembar cetak (KartuGrafik menukarnya saat
 * dicetak). Tombol "Lihat angkanya" yang membuka ruang kosong adalah
 * janji yang tidak ditepati — karena itu setiap grafik di dasbor
 * membawa tabelnya.
 *
 * Angka ditulis penuh dengan pemisah ribuan Indonesia, tidak diringkas
 * ("12,4 rb" milik sumbu, bukan tabel). Kosong ditulis "—", bukan 0:
 * hari yang tidak diukur bukan hari bernilai nol.
 */
withDefaults(defineProps<{
  kolom: string[];
  baris: (string | number | null | undefined)[][];
  /** Warna kunci identitas per baris — kotak kecil di kolom pertama. */
  kunci?: (string | null | undefined)[] | null;
  /** Desimal terbanyak untuk angka pecahan. */
  desimal?: number;
}>(), { kunci: null, desimal: 2 });

const tampil = (v: string | number | null | undefined, desimal: number) => {
  if (v === null || v === undefined || (typeof v === 'number' && Number.isNaN(v))) return '—';
  if (typeof v === 'number') {
    return v.toLocaleString('id-ID', { maximumFractionDigits: desimal });
  }

  return v;
};
</script>

<template>
  <table>
    <thead>
      <tr><th v-for="k in kolom" :key="k" scope="col">{{ k }}</th></tr>
    </thead>
    <tbody>
      <tr v-for="(b, i) in baris" :key="i">
        <td v-for="(v, j) in b" :key="j" :class="typeof v === 'number' ? 'num' : undefined">
          <i v-if="j === 0 && kunci && kunci[i]" class="grafik-kunci" :style="{ background: kunci[i] ?? undefined }"
             aria-hidden="true"></i>{{ tampil(v, desimal) }}
        </td>
      </tr>
      <tr v-if="!baris.length">
        <td :colspan="kolom.length">Belum ada data.</td>
      </tr>
    </tbody>
  </table>
</template>
