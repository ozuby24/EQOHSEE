<script setup lang="ts">
import { computed } from 'vue';

/**
 * Tren pemenuhan bulanan — batang, bukan tabel angka.
 *
 * Pertanyaan yang dibawa ke tren adalah "membaik atau tidak", dan itu
 * pertanyaan tentang BENTUK, bukan tentang angka. Dua belas baris angka
 * menuntut pembacanya menyusun bentuknya sendiri di kepala; batang
 * memberikannya langsung.
 *
 * Bulan yang belum direkap TIDAK digambar sebagai batang setinggi nol.
 * Nol berarti "tidak ada satu pun yang comply"; belum direkap berarti
 * belum ada yang mengisi. Keduanya digambar berbeda — yang kedua
 * sebagai jejak kosong bergaris putus.
 */
const props = defineProps<{
  tren: Array<{ bulan: string; comply: number; notComply: number; persen: number | null; evaluasi: string | null }>;
}>();

const TINGGI = 132;

const warna = (p: number | null) =>
  p === null ? '#D6D3D1' : p >= 90 ? '#16A34A' : p >= 70 ? '#CA9A04' : p >= 50 ? '#EA580C' : '#DC2626';

const batang = computed(() => props.tren.map((t) => ({
  ...t,
  tinggi: t.persen === null ? 0 : Math.max((t.persen / 100) * TINGGI, 2),
  warna: warna(t.persen),
  pendek: t.bulan.slice(0, 3),
})));

/* Garis bantu pada 70% dan 90% — dua ambang yang dipakai seluruh
   aplikasi untuk membedakan "taat" dari "belum". Tanpa keduanya,
   batang setinggi 72% dan 88% terbaca sama saja. */
const AMBANG = [
  { nilai: 90, label: '90%' },
  { nilai: 70, label: '70%' },
];
</script>

<template>
  <div class="tren">
    <div class="tren-kanvas" :style="{ height: TINGGI + 'px' }">
      <div v-for="a in AMBANG" :key="a.nilai" class="tren-ambang"
           :style="{ bottom: (a.nilai / 100) * TINGGI + 'px' }">
        <span>{{ a.label }}</span>
      </div>

      <div class="tren-batang-baris">
        <div v-for="t in batang" :key="t.bulan" class="tren-kolom">
          <span v-if="t.persen !== null" class="tren-angka num" :style="{ color: t.warna }">
            {{ t.persen }}%
          </span>

          <div v-if="t.persen !== null" class="tren-batang"
               :style="{ height: t.tinggi + 'px', background: t.warna }"
               :title="`${t.bulan}: ${t.comply} comply, ${t.notComply} not comply`"></div>

          <div v-else class="tren-kosong" :title="`${t.bulan}: belum direkap`"></div>
        </div>
      </div>
    </div>

    <div class="tren-label">
      <span v-for="t in batang" :key="t.bulan" :class="{ sunyi: t.persen === null }">{{ t.pendek }}</span>
    </div>

    <!-- Evaluasi tertulisnya tetap ada, di bawah bentuknya — bukan
         menggantikannya. Yang bertanya "kenapa turun" dijawab kalimat,
         bukan batang. -->
    <dl v-if="batang.some((t) => t.evaluasi)" class="tren-catatan">
      <template v-for="t in batang" :key="t.bulan">
        <template v-if="t.evaluasi">
          <dt>{{ t.bulan }}</dt>
          <dd>{{ t.evaluasi }}</dd>
        </template>
      </template>
    </dl>
  </div>
</template>

<style scoped>
.tren-kanvas { position: relative; }

.tren-ambang {
  position: absolute; left: 0; right: 0;
  border-top: 1px dashed #E7E5E4;
  pointer-events: none;
}
.tren-ambang span {
  position: absolute; right: 0; top: -7px;
  background: #fff; padding-left: 5px;
  font-size: 9px; font-weight: 700; color: #C0BCB8;
}

.tren-batang-baris {
  position: absolute; inset: 0;
  display: flex; align-items: flex-end; gap: 6px;
}

.tren-kolom {
  flex: 1 1 0; min-width: 0;
  display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
  height: 100%;
}

.tren-angka { font-size: 9.5px; font-weight: 800; margin-bottom: 3px; }

.tren-batang {
  width: 100%;
  border-radius: 5px 5px 2px 2px;
  transition: height .5s cubic-bezier(.21, .6, .35, 1);
}

/* Belum direkap: jejak bergaris putus setinggi sedikit saja — hadir,
   tetapi jelas bukan batang bernilai nol. */
.tren-kosong {
  width: 100%; height: 10px;
  border: 1px dashed #D6D3D1; border-radius: 4px; background: #FAFAF9;
}

.tren-label {
  display: flex; gap: 6px; margin-top: 6px;
}
.tren-label span {
  flex: 1 1 0; min-width: 0; text-align: center;
  font-size: 9.5px; font-weight: 700; color: #78716C;
}
.tren-label span.sunyi { color: #D6D3D1; font-weight: 500; }

.tren-catatan {
  display: grid; grid-template-columns: auto 1fr; gap: .2rem .7rem;
  margin-top: .9rem; padding-top: .7rem; border-top: 1px solid #F5F5F4;
  font-size: 11px;
}
.tren-catatan dt { font-weight: 700; color: #57534E; white-space: nowrap; }
.tren-catatan dd { color: #78716C; margin: 0; }
</style>
