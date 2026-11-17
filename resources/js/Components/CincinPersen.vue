<script setup lang="ts">
import { computed } from 'vue';

/**
 * Cincin persentase — satu pengukur bernilai tunggal.
 *
 * Bukan grafik banyak kategori melainkan satu angka, jadi warnanya
 * mengikuti pita capaian dan sisanya abu-abu netral.
 *
 * `null` DIBEDAKAN dari nol. Nol berarti "seluruhnya tidak terpenuhi" —
 * pernyataan yang jauh lebih keras daripada "belum ada yang dinilai".
 * Cincin yang menggambar keduanya sebagai lingkaran kosong menyatakan
 * kegagalan total pada register yang sebenarnya baru dibuka.
 */
const props = withDefaults(defineProps<{
  persen: number | null;
  ukuran?: number;
  tebal?: number;
  /** Baris kecil di bawah angkanya; biarkan kosong bila tidak perlu. */
  ket?: string;
}>(), { ukuran: 132, tebal: 12 });

const R = computed(() => (props.ukuran - props.tebal) / 2);
const KELILING = computed(() => 2 * Math.PI * R.value);

const nilai = computed(() => Math.min(Math.max(props.persen ?? 0, 0), 100));

const terisi = computed(() =>
  props.persen === null ? 0 : (nilai.value / 100) * KELILING.value);

const warna = computed(() => {
  if (props.persen === null) return '#A8A29E';
  if (nilai.value >= 90) return '#16A34A';
  if (nilai.value >= 70) return '#CA9A04';
  if (nilai.value >= 50) return '#EA580C';
  return '#DC2626';
});

const teks = computed(() => (props.persen === null ? '—' : `${props.persen}%`));
</script>

<template>
  <div class="cincin" :style="{ width: ukuran + 'px', height: ukuran + 'px' }"
       role="img" :aria-label="`Pemenuhan ${teks}${ket ? ' — ' + ket : ''}`">
    <svg :viewBox="`0 0 ${ukuran} ${ukuran}`" aria-hidden="true">
      <!-- Jalur belakang: selalu penuh, supaya besar cincinnya tidak
           berubah-ubah mengikuti angkanya. -->
      <circle :cx="ukuran / 2" :cy="ukuran / 2" :r="R"
              fill="none" stroke="#EDECEA" :stroke-width="tebal" />

      <circle :cx="ukuran / 2" :cy="ukuran / 2" :r="R"
              fill="none" :stroke="warna" :stroke-width="tebal" stroke-linecap="round"
              :stroke-dasharray="`${terisi} ${KELILING}`"
              :transform="`rotate(-90 ${ukuran / 2} ${ukuran / 2})`" />
    </svg>

    <div class="cincin-isi">
      <span class="cincin-angka num" :style="{ color: warna }">{{ teks }}</span>
      <span v-if="ket" class="cincin-ket">{{ ket }}</span>
    </div>
  </div>
</template>

<style scoped>
.cincin { position: relative; flex: none; }
.cincin svg { width: 100%; height: 100%; display: block; }
.cincin circle { transition: stroke-dasharray .6s cubic-bezier(.21, .6, .35, 1), stroke .3s; }

.cincin-isi {
  position: absolute; inset: 0;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 2px; text-align: center; padding: 0 12%;
}

.cincin-angka { font-size: 1.55rem; font-weight: 800; letter-spacing: -.03em; line-height: 1; }
.cincin-ket { font-size: 10px; color: #A8A29E; line-height: 1.25; }
</style>
