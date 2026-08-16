<script setup lang="ts">
/**
 * Satu angka terhadap targetnya.
 *
 * Bentuk yang tepat ketika yang ditanyakan bukan "berapa" melainkan
 * "sudah sampai mana" — ketaatan PM, indeks kelayakan, capaian
 * produksi. Targetnya digambar sebagai penanda di atas jalur, bukan
 * hanya ditulis: jarak menuju target adalah yang dibaca, dan jarak
 * lebih mudah dilihat daripada dihitung.
 *
 * BELUM DIUKUR TIDAK SAMA DENGAN NOL. `nilai` yang null menggambar
 * jalur kosong bertuliskan "belum ada data", bukan meter di angka nol.
 * Meter nol terbaca sebagai capaian terburuk yang mungkin — dan pada
 * halaman yang datanya memang belum masuk, itu kebalikan dari yang
 * sebenarnya diketahui.
 */
import { computed } from 'vue';
import { KEADAAN, TANGGA, ringkas } from './warna';

const props = withDefaults(defineProps<{
  nilai: number | null;
  target?: number | null;
  /** Batas atas jalur; 100 untuk persentase. */
  maks?: number;
  satuan?: string;
  /** Angka kecil dan target lebih besar berarti baik — mis. jam henti. */
  kecilLebihBaik?: boolean;
}>(), { target: null, maks: 100, satuan: '%', kecilLebihBaik: false });

const rasio = computed(() =>
  props.nilai === null ? 0 : Math.min(1, Math.max(0, props.nilai / props.maks)));

const rasioTarget = computed(() =>
  props.target === null ? null : Math.min(1, Math.max(0, props.target / props.maks)));

/**
 * Warna mengikuti KEADAANNYA terhadap target, bukan besarnya sendiri:
 * 92% adalah keberhasilan bila targetnya 90 dan kegagalan bila
 * targetnya 95, dan meter yang mewarnainya sama pada kedua hal itu
 * tidak membantu memutuskan apa pun.
 */
const warna = computed(() => {
  if (props.nilai === null) return TANGGA[0];
  if (props.target === null) return TANGGA[2];

  const capai = props.kecilLebihBaik
    ? props.target / Math.max(props.nilai, 0.0001)
    : props.nilai / props.target;

  if (capai >= 1)    return KEADAAN.baik;
  if (capai >= 0.95) return KEADAAN.ingat;
  if (capai >= 0.85) return KEADAAN.serius;

  return KEADAAN.gawat;
});

const keterangan = computed(() => {
  if (props.nilai === null)  return 'belum ada data';
  if (props.target === null) return null;

  const selisih = props.nilai - props.target;

  /* Ambangnya NISBI terhadap target, bukan angka tetap. Intensitas
     energi berukuran 0,024 GJ/ton; ambang tetap 0,05 menyatakan
     SELURUH nilai yang mungkin sebagai "tepat di target", termasuk
     yang setengahnya. Itu yang terjadi pada dasbor energi — meter
     0,0105 terhadap target 0,024 dilaporkan tepat sasaran. */
  if (Math.abs(selisih) < Math.abs(props.target) * 0.01) return 'tepat di target';

  const arahBaik = props.kecilLebihBaik ? selisih < 0 : selisih > 0;

  return `${ringkas(Math.abs(selisih))}${props.satuan} ${arahBaik ? 'di bawah' : 'di atas'} target`;
});
</script>

<template>
  <div>
    <div class="flex items-baseline gap-2 mb-2">
      <span class="text-[26px] font-bold leading-none num"
            :style="{ color: nilai === null ? '#A8A29E' : '#292524' }">
        {{ nilai === null ? '—' : ringkas(nilai) }}<span
          v-if="nilai !== null" class="text-[15px] font-semibold text-stone-400">{{ satuan }}</span>
      </span>

      <span v-if="keterangan" class="text-[11px] font-semibold"
            :style="{ color: nilai === null ? '#A8A29E' : warna }">{{ keterangan }}</span>
    </div>

    <div class="relative h-2.5 rounded-full bg-stone-100 overflow-visible"
         role="meter" :aria-valuenow="nilai ?? undefined" :aria-valuemin="0" :aria-valuemax="maks">
      <div class="absolute inset-y-0 left-0 rounded-full transition-[width] duration-300"
           :style="{ width: (rasio * 100) + '%', background: warna }"></div>

      <!-- Penanda target berdiri melampaui jalurnya supaya tetap
           terlihat ketika isian sudah melewatinya. -->
      <div v-if="rasioTarget !== null"
           class="absolute -top-1 -bottom-1 w-0.5 bg-stone-700 rounded-full"
           :style="{ left: `calc(${rasioTarget * 100}% - 1px)` }">
        <span class="sr-only">Target {{ target }}{{ satuan }}</span>
      </div>
    </div>

    <p v-if="target !== null" class="text-[10.5px] text-stone-400 mt-1.5">
      Target {{ ringkas(target) }}{{ satuan }}
    </p>
  </div>
</template>
