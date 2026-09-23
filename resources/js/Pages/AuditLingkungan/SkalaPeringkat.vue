<script setup lang="ts">
/**
 * Skala peringkat: di mana skor akhir jatuh di antara kelima pita
 * peringkat instrumennya.
 *
 * Satu garis mendatar, bukan jarum melingkar: jarak antar-ambang dapat
 * dibandingkan langsung — terlihat bahwa HITAM menempati enam puluh
 * persen skala, dan bahwa jarak dari BIRU ke HIJAU sama dengan dari
 * HIJAU ke EMAS. Nama pita selalu tercetak; warna tidak pernah dibaca
 * sendirian.
 */
import { computed } from 'vue';

const props = defineProps<{
  skor: number;
  peringkat: Array<{ nama: string; kriteria: string; min: number; warna: string }>;
}>();

const pita = computed(() => {
  const urut = [...props.peringkat].sort((a, b) => a.min - b.min);
  return urut.map((p, i) => ({ ...p, sampai: urut[i + 1]?.min ?? 100 }));
});

const posisi = computed(() => Math.min(100, Math.max(0, props.skor)));

/* Label angka digeser ke dalam di kedua ujung, supaya skor 98 tidak
   terpotong tepi kartu; garis penandanya tetap tepat di posisinya. */
const geser = computed(() => (posisi.value > 92 ? '-88%' : posisi.value < 8 ? '-12%' : '-50%'));
const angka = (n: number) => n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/* HITAM pada permukaan gelap tidak terlihat: di sana digambar abu-abu
   lewat variabel, tetap bernama HITAM di bawahnya. */
const warna = (p: { nama: string; warna: string }) => (p.nama === 'HITAM' ? 'var(--akl-hitam)' : p.warna);
</script>

<template>
  <div class="akl-skala" role="img"
       :aria-label="`Skor ${angka(skor)} pada skala peringkat: ${pita.map((p) => `${p.nama} ${p.min}–${p.sampai}`).join(', ')}`">
    <div class="akl-skala-penanda" :style="{ left: `${posisi}%` }">
      <b class="num" :style="{ transform: `translateX(${geser})` }">{{ angka(skor) }}</b>
      <i></i>
    </div>

    <div class="akl-skala-pita">
      <span v-for="p in pita" :key="p.nama" :title="`${p.nama} — ${p.kriteria} (${p.min}–${p.sampai})`"
            :class="{ redup: !(skor >= p.min && (skor < p.sampai || p.sampai === 100)) }"
            :style="{ width: `${p.sampai - p.min}%`, background: warna(p) }"></span>
    </div>

    <div class="akl-skala-label" aria-hidden="true">
      <span v-for="p in pita" :key="p.nama" :style="{ width: `${p.sampai - p.min}%` }">
        <b>{{ p.nama }}</b>
        <span class="num">{{ p.min }}<template v-if="p.sampai - p.min >= 20">–{{ p.sampai }}</template></span>
      </span>
    </div>
  </div>
</template>
