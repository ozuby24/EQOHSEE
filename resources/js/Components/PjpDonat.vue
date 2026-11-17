<script setup lang="ts">
import { computed } from 'vue';

/**
 * Donat kelengkapan checklist: tercapai lawan kekurangan.
 *
 * Bukan grafik banyak kategori melainkan satu pengukur bernilai tunggal,
 * jadi warnanya mengikuti pita capaian yang sama dengan seluruh modul
 * dan sisanya abu-abu netral — bukan palet kategorikal.
 *
 * Kedua barisnya dapat diklik dan sengaja menuju tempat BERBEDA:
 * "sudah lengkap" ke rincian per kategori, "belum lengkap" langsung ke
 * kategori terlemah. Yang kedua itu intinya — jalan langsung dari "ada
 * yang menarik skor ke bawah" menuju formulir yang memperbaikinya,
 * tanpa membaca ulang tujuh belas kategori untuk mencarinya.
 */
const props = defineProps<{ persentase: number }>();

const emit = defineEmits<{ (e: 'tercapai'): void; (e: 'kurang'): void }>();

const R = 46;
const TEBAL = 16;
const KELILING = 2 * Math.PI * R;
const SELA = 2;

const tercapai = computed(() => Math.round(Math.min(Math.max(props.persentase, 0), 100) * 10) / 10);
const kurang = computed(() => Math.round((100 - tercapai.value) * 10) / 10);

const warna = computed(() => {
  const n = tercapai.value;
  if (n >= 80) return '#0ca30c';
  if (n >= 60) return '#fab219';
  if (n >= 40) return '#ec835a';
  return '#d03b3b';
});

const panjangTercapai = computed(() => Math.max((tercapai.value / 100) * KELILING - SELA, 0));
const panjangKurang = computed(() => Math.max((kurang.value / 100) * KELILING - SELA, 0));
const geserKurang = computed(() => -((tercapai.value / 100) * KELILING) - SELA);
</script>

<template>
  <div class="flex flex-col items-center gap-4 sm:flex-row sm:gap-6">
    <svg viewBox="0 0 120 120" class="h-32 w-32 shrink-0">
      <circle cx="60" cy="60" :r="R" fill="none" stroke="#e7e5e4" :stroke-width="TEBAL" />
      <g transform="rotate(-90 60 60)">
        <circle
          cx="60" cy="60" :r="R" fill="none" :stroke="warna" :stroke-width="TEBAL"
          stroke-linecap="round" :stroke-dasharray="`${panjangTercapai} ${KELILING}`"
          class="cursor-pointer transition-opacity hover:opacity-80"
          @click="emit('tercapai')"
        >
          <title>Sudah lengkap: {{ tercapai }}%</title>
        </circle>
        <circle
          v-if="panjangKurang > 0"
          cx="60" cy="60" :r="R" fill="none" stroke="#cbd5e1" :stroke-width="TEBAL"
          stroke-linecap="round" :stroke-dasharray="`${panjangKurang} ${KELILING}`"
          :stroke-dashoffset="geserKurang"
          class="cursor-pointer transition-opacity hover:opacity-80"
          @click="emit('kurang')"
        >
          <title>Belum lengkap: {{ kurang }}%</title>
        </circle>
      </g>
      <text x="60" y="57" text-anchor="middle" class="fill-stone-800 font-bold" style="font-size:20px">{{ tercapai }}%</text>
      <text x="60" y="74" text-anchor="middle" class="fill-stone-400" style="font-size:9px">Lengkap</text>
    </svg>

    <div class="w-full space-y-2">
      <button type="button"
              class="flex w-full items-center justify-between rounded-lg border border-stone-200 px-3 py-2 text-[12px] hover:bg-stone-50"
              @click="emit('tercapai')">
        <span class="flex items-center gap-2">
          <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: warna }"></span>
          Sudah lengkap
        </span>
        <span class="font-bold text-stone-700">{{ tercapai }}%</span>
      </button>

      <button type="button"
              class="flex w-full items-center justify-between rounded-lg border border-stone-200 px-3 py-2 text-left text-[12px] hover:bg-stone-50"
              @click="emit('kurang')">
        <span class="flex items-center gap-2">
          <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-stone-300"></span>
          Belum lengkap — perlu ditindaklanjuti
        </span>
        <span class="font-bold text-stone-700">{{ kurang }}%</span>
      </button>
    </div>
  </div>
</template>
