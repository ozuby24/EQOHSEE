<script setup lang="ts">
/**
 * Cincin kemajuan — satu persentase, digambar sebagai busur.
 *
 * DIPAKAI UNTUK ANGKA YANG PUNYA BATAS ALAMI, bukan untuk besaran
 * bebas. Persentase kepatuhan punya nol dan seratus; tonase tidak, dan
 * menggambarnya sebagai cincin berarti mengarang batas atas yang tidak
 * ada.
 *
 * Dibanding jalur mendatar, cincin menempatkan angkanya di tengah
 * bentuknya sendiri: satu benda untuk dibaca, bukan angka di satu sisi
 * dan bentuk di sisi lain. Itu yang membuatnya terbaca sekilas dari
 * jarak layar rapat.
 *
 * KOSONG TIDAK SAMA DENGAN NOL. Nilai null menggambar cincin abu tanpa
 * busur dan menulis "belum ada data" — sebab cincin nol persen terbaca
 * sebagai kepatuhan nol, tuduhan yang jauh lebih berat daripada
 * "belum diukur".
 */
import { computed } from 'vue';

const props = withDefaults(defineProps<{
  nilai: number | null;
  /** Batas atas busurnya. Seratus untuk persentase. */
  maks?: number;
  satuan?: string;
  /** Ambang yang dianggap memadai — digambar sebagai takik pada jalur. */
  target?: number | null;
  warna?: string;
  ukuran?: number;
  label?: string | null;
}>(), { maks: 100, satuan: '%', target: null, warna: '#C47000', ukuran: 116, label: null });

const R = 42;
const KELILING = 2 * Math.PI * R;

const rasio = computed(() =>
  props.nilai === null ? 0 : Math.min(1, Math.max(0, props.nilai / props.maks)));

const panjang = computed(() => KELILING * rasio.value);

/* Warna mengikuti jarak ke targetnya, bukan ditetapkan pemanggilnya.
   Dua kartu berdampingan dengan target berbeda harus tetap dapat
   dibandingkan sekilas: hijau berarti sampai, merah berarti jauh. */
const warnaBusur = computed(() => {
  if (props.nilai === null) return '#D6D3D1';
  if (props.target === null) return props.warna;

  if (props.nilai >= props.target) return '#16A34A';

  return props.nilai >= props.target * 0.8 ? '#D97706' : '#DC2626';
});

/** Takik target pada jalur, sebagai sudut. */
const sudutTarget = computed(() =>
  props.target === null ? null : Math.min(1, props.target / props.maks) * 360 - 90);

const teks = computed(() =>
  props.nilai === null ? '—' : `${Number.isInteger(props.nilai) ? props.nilai : props.nilai.toFixed(1)}`);
</script>

<template>
  <div class="grid place-items-center">
    <div class="relative" :style="{ width: `${ukuran}px`, height: `${ukuran}px` }">
      <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90"
           role="img"
           :aria-label="`${label ?? 'Kemajuan'}: ${nilai === null ? 'belum ada data' : nilai + satuan}`">
        <defs>
          <!-- Landaian sepanjang busurnya sendiri: pangkalnya lebih
               pekat daripada ujungnya, sehingga arah tumbuhnya terbaca
               tanpa panah. -->
          <linearGradient :id="`cincin-${warnaBusur.slice(1)}`" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" :stop-color="warnaBusur" />
            <stop offset="100%" :stop-color="warnaBusur" stop-opacity=".62" />
          </linearGradient>
        </defs>

        <!-- jalur -->
        <circle cx="50" cy="50" :r="R" fill="none" stroke="#F0EFEC" stroke-width="11" />

        <!-- busur -->
        <circle v-if="nilai !== null"
                cx="50" cy="50" :r="R" fill="none"
                :stroke="`url(#cincin-${warnaBusur.slice(1)})`"
                stroke-width="11" stroke-linecap="round"
                :stroke-dasharray="`${panjang} ${KELILING}`"
                style="transition:stroke-dasharray .45s cubic-bezier(.21,.6,.35,1)" />

        <!-- Takik target pada jalurnya: garis pendek yang menyilang
             cincin, bukan angka tersendiri. Tanpa ini "72%" tidak dapat
             dinilai — memadai atau tidak bergantung pada targetnya, dan
             target yang hanya tertulis di bawah harus dibaca dua kali. -->
        <line v-if="sudutTarget !== null"
              x1="50" y1="2" x2="50" y2="14"
              :transform="`rotate(${sudutTarget + 90} 50 50)`"
              stroke="#0F1720" stroke-width="2" stroke-linecap="round" opacity=".55" />
      </svg>

      <div class="absolute inset-0 grid place-items-center text-center leading-none">
        <div>
          <strong class="num block text-[22px] font-bold text-cam-ink">{{ teks }}</strong>
          <span v-if="nilai !== null" class="text-[11px] text-stone-400">{{ satuan }}</span>
          <span v-else class="text-[10.5px] text-stone-400">belum ada data</span>
        </div>
      </div>
    </div>

    <p v-if="label" class="text-[11.5px] text-stone-500 mt-2 text-center">{{ label }}</p>
  </div>
</template>
