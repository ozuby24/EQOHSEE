<script setup lang="ts">
/**
 * Kesiapan armada: dua rasio terhadap targetnya, lalu tiga angka
 * pendamping.
 *
 * Menggantikan lima kartu berdampingan yang setiap angkanya berdiri
 * sendiri. Dua di antaranya — ketersediaan dan kepatuhan PM — adalah
 * rasio yang hanya berarti bila dibandingkan target, dan angka
 * telanjang "87%" tidak mengatakan apakah itu berhasil atau gagal.
 * Warnanya dahulu memang berganti pada ambang tetap, tetapi ambangnya
 * tidak tertulis di mana pun; pembacanya harus sudah menghafalnya.
 *
 * DUA KEADAAN YANG TIDAK BOLEH TERTUKAR, dan keduanya pernah tertukar
 * di sini:
 *
 *   - Armada tanpa satu pun alat terdaftar tidak "tersedia 100%".
 *   - Alat tanpa jadwal PM bukan alat yang patuh maupun tidak patuh;
 *     ia dilaporkan terpisah, bukan dilebur ke persentasenya.
 *
 * Inilah layar yang dahulu menampilkan "Kepatuhan PM 100%" pada
 * pemasangan yang belum punya satu alat pun.
 */
import { computed } from 'vue';
import Meter from '../../Grafik/Meter.vue';
import { KEADAAN } from '../../Grafik/warna';

const props = defineProps<{
  keandalan: Record<string, any>;
  pm: Record<string, any>;
  tunggakan: Record<string, any>;
}>();

/** Ambang yang lazim dipakai armada tambang; tertulis, bukan dihafal. */
const TARGET_KETERSEDIAAN = 85;
const TARGET_PM           = 90;

const jam = (v: unknown) =>
  typeof v === 'number' ? v.toLocaleString('id-ID', { maximumFractionDigits: 1 }) : '—';

const ketersediaan = computed(() =>
  Number(props.keandalan?.jamTersedia ?? 0) > 0
    ? Number(props.keandalan?.ketersediaan ?? 0)
    : null);

const pmPersen = computed(() =>
  props.pm?.persen === null || props.pm?.persen === undefined
    ? null : Number(props.pm.persen));

const kartu = computed(() => [
  { l: 'MTBF', v: props.keandalan?.mtbf == null ? '—' : jam(Number(props.keandalan.mtbf)) + ' jam',
    k: 'jarak antar kegagalan' },
  { l: 'MTTR', v: props.keandalan?.mttr == null ? '—' : jam(Number(props.keandalan.mttr)) + ' jam',
    k: 'lama pemulihan' },
  { l: 'Tunggakan', v: `${props.tunggakan?.jumlah ?? 0} WO`,
    k: `${props.tunggakan?.kritis ?? 0} berprioritas kritis`,
    gawat: (props.tunggakan?.kritis ?? 0) > 0 },
]);
</script>

<template>
  <section class="space-y-3">
    <div class="grid gap-3 lg:grid-cols-2">
    <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13px] font-bold text-cam-ink">Ketersediaan armada</h3>
      <p class="text-[11px] text-stone-500 mt-0.5 mb-3">Jam siap operasi terhadap jam kalender.</p>

      <Meter :nilai="ketersediaan" :target="TARGET_KETERSEDIAAN" :maks="100" satuan="%" />

      <p v-if="ketersediaan === null" class="text-[11px] text-amber-700 mt-3 leading-snug">
        Belum ada alat terdaftar, jadi ketersediaannya belum ada — bukan 100%.
      </p>
    </div>

    <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13px] font-bold text-cam-ink">Kepatuhan perawatan</h3>
      <p class="text-[11px] text-stone-500 mt-0.5 mb-3">Alat berjadwal yang PM-nya belum lewat.</p>

      <Meter :nilai="pmPersen" :target="TARGET_PM" :maks="100" satuan="%" />

      <p class="text-[11px] mt-3 leading-snug"
         :class="(pm?.tanpaJadwal ?? 0) > 0 ? 'text-amber-700' : 'text-stone-400'">
        <template v-if="pmPersen === null">
          Belum ada alat berjadwal PM, jadi kepatuhannya belum ada — bukan 100%.
        </template>
        <template v-else>
          {{ pm?.patuh ?? 0 }} dari {{ pm?.berjadwal ?? 0 }} alat berjadwal.
          <template v-if="(pm?.tanpaJadwal ?? 0) > 0">
            <b>{{ pm.tanpaJadwal }} alat belum punya jadwal</b> dan tidak ikut dihitung.
          </template>
        </template>
      </p>
    </div>

    </div>

    <!-- Barisnya sendiri, bukan kolom ketiga di samping kedua meter.
         Dijejalkan ke sepertiga lebar, "4.054,5 jam" terpotong menjadi
         dua baris di tengah angkanya. -->
    <div class="grid gap-3 grid-cols-1 sm:grid-cols-3">
      <article v-for="c in kartu" :key="c.l"
               class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">{{ c.l }}</p>
        <p class="mt-1.5 text-[17px] font-extrabold num leading-none"
           :style="{ color: c.gawat ? KEADAAN.gawat : '#292524' }">{{ c.v }}</p>
        <p class="text-[10.5px] text-stone-400 mt-1.5 leading-snug">{{ c.k }}</p>
      </article>
    </div>
  </section>
</template>
