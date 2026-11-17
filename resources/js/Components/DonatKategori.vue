<script setup lang="ts">
/**
 * Donat sebaran berkategori — beberapa potongan berwarna.
 *
 * Berbeda dari PjpDonat, yang menggambar SATU nilai terhadap sisanya
 * memakai pita capaian. Yang di sini sebaran: tiap potongan sebuah
 * kategori, warnanya datang dari server, dan urutannya ditetapkan di
 * sana pula — Rendah, Sedang, Tinggi selalu berurutan itu, sehingga
 * potongan merah berarti hal yang sama pada tiap perusahaan dan tiap
 * bulan. Diurut menurut besarnya di sini, warnanya akan berpindah
 * tempat setiap kali datanya berubah.
 *
 * ── Angka di tengah adalah TOTAL, bukan potongan terbesar ──
 *
 * Donat yang menuliskan persentase potongan terbesar di tengahnya
 * mengundang angka itu dibaca sebagai keseluruhan. Yang ditulis di sini
 * jumlah seluruhnya, dan rincian tiap potongan ada di daftarnya.
 */
import { computed } from 'vue';

const props = defineProps<{
  potong: Array<{ label: string; nilai: number; pct: number; warna: string }>;
  satuan?: string;
}>();

const R = 46;
const TEBAL = 15;
const KELILING = 2 * Math.PI * R;
/* Sela antar potongan. Tanpanya dua potongan bersebelahan yang
   warnanya mirip terbaca sebagai satu potongan besar. */
const SELA = 2.5;

const total = computed(() => props.potong.reduce((n, p) => n + p.nilai, 0));

/** Potongan beserta panjang busur dan pergeserannya, sudah berurut. */
const busur = computed(() => {
  const t = total.value;
  let lewat = 0;

  return props.potong.map((p) => {
    const penuh = t ? (p.nilai / t) * KELILING : 0;
    const geser = -lewat;
    lewat += penuh;

    return {
      ...p,
      /* Potongan bernilai nol tidak digambar sama sekali. Digambar
         sepanjang sela, ia muncul sebagai titik berwarna yang tidak
         mewakili apa pun. */
      panjang: p.nilai > 0 ? Math.max(penuh - SELA, 0.5) : 0,
      geser,
    };
  });
});
</script>

<template>
  <div class="flex flex-col items-center gap-4 sm:flex-row sm:gap-5">
    <svg viewBox="0 0 120 120" class="h-28 w-28 shrink-0" role="img"
         :aria-label="`Sebaran ${total} ${satuan ?? 'data'}`">
      <circle cx="60" cy="60" :r="R" fill="none" stroke="#F1F0EF" :stroke-width="TEBAL" />
      <g transform="rotate(-90 60 60)">
        <circle v-for="(p, i) in busur" :key="i"
                v-show="p.panjang > 0"
                cx="60" cy="60" :r="R" fill="none"
                :stroke="p.warna" :stroke-width="TEBAL" stroke-linecap="butt"
                :stroke-dasharray="`${p.panjang} ${KELILING}`"
                :stroke-dashoffset="p.geser">
          <title>{{ p.label }}: {{ p.nilai }} ({{ p.pct }}%)</title>
        </circle>
      </g>
      <text x="60" y="58" text-anchor="middle" class="fill-stone-800 font-bold"
            style="font-size:21px">{{ total }}</text>
      <text x="60" y="73" text-anchor="middle" class="fill-stone-400"
            style="font-size:8.5px">{{ satuan ?? 'total' }}</text>
    </svg>

    <ul class="w-full space-y-1.5 min-w-0">
      <li v-for="(p, i) in potong" :key="i" class="flex items-center gap-2 text-[12px]">
        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ background: p.warna }"></span>
        <span class="text-stone-600 truncate">{{ p.label }}</span>
        <span class="ml-auto shrink-0 num font-bold text-cam-ink">{{ p.nilai }}</span>
        <span class="shrink-0 num text-[11px] text-stone-400 w-11 text-right">{{ p.pct }}%</span>
      </li>
      <li v-if="!potong.length" class="text-[12px] text-stone-300">Belum ada data.</li>
    </ul>
  </div>
</template>
