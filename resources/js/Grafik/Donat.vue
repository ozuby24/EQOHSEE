<script setup lang="ts">
/**
 * Bagian terhadap keseluruhan, dengan keterangan yang menyebut
 * angkanya.
 *
 * Donat dipakai HANYA untuk sedikit kelompok yang benar-benar
 * menjumlah menjadi satu keseluruhan — status inspeksi, sebaran
 * kelayakan alat. Ia buruk untuk membandingkan besaran (mata manusia
 * membandingkan sudut jauh lebih buruk daripada panjang), jadi
 * perbandingan tetap memakai batang.
 *
 * Keterangannya menyebut angka DAN persen, bukan warna saja. Itu yang
 * membuatnya tetap terbaca oleh yang tidak membedakan warna, dan yang
 * membuat kelompok berwarna terang tetap sah dipakai.
 *
 * Lubang tengahnya memuat angka utama — bukan hiasan: bagian yang
 * paling sering dicari dari grafik semacam ini adalah totalnya, dan
 * menaruhnya di tengah menghemat satu baris teks.
 */
import { computed, ref } from 'vue';
import { BINGKAI, KEADAAN, warnaDeret } from './warna';

const props = withDefaults(defineProps<{
  /**
   * `warna` disebut pemanggilnya ketika satu hal harus berwarna SAMA
   * di beberapa grafik pada satu halaman. Tanpa itu warnanya mengikuti
   * urutan di dalam donat ini saja, dan "Solar" dapat berwarna jingga
   * pada grafik garis lalu biru pada donat di bawahnya.
   */
  bagian: { label: string; nilai: number; keadaan?: keyof typeof KEADAAN; warna?: string }[];
  /** Angka besar di tengah; bila kosong dipakai jumlah seluruh bagian. */
  tengah?: string | null;
  tengahLabel?: string | null;
}>(), { tengah: null, tengahLabel: null });

const R = 42;
const KELILING = 2 * Math.PI * R;

const total = computed(() => props.bagian.reduce((j, b) => j + b.nilai, 0));

const warna = (b: { keadaan?: keyof typeof KEADAAN; warna?: string }, i: number) =>
  b.warna ?? (b.keadaan ? KEADAAN[b.keadaan] : warnaDeret(i));

const potongan = computed(() => {
  let jalan = 0;

  return props.bagian.map((b, i) => {
    const rasio   = total.value ? b.nilai / total.value : 0;
    const panjang = rasio * KELILING;

    /* Celah 2px berwarna latar memisahkan potongan yang bersentuhan —
       bukan garis tepi. Garis tepi menambah tinta yang bukan data;
       celah memisahkan dengan ketiadaan. Potongan yang lebih kecil
       daripada celahnya digambar utuh, sebab memangkasnya akan
       menghilangkannya sama sekali. */
    const celah = panjang > 6 ? 2 : 0;

    const p = {
      label:   b.label,
      nilai:   b.nilai,
      persen:  total.value ? Math.round(rasio * 100) : 0,
      warna:   warna(b, i),
      panjang: Math.max(0, panjang - celah),
      geser:   -jalan,
    };

    jalan += panjang;

    return p;
  });
});

const disorot = ref<string | null>(null);
</script>

<template>
  <div v-if="!total"
       class="h-full min-h-[140px] grid place-items-center text-[12px] text-stone-400">
    Belum ada isinya untuk dibagi.
  </div>

  <div v-else class="flex items-center gap-5 flex-wrap">
    <div class="relative shrink-0">
      <svg viewBox="0 0 100 100" class="w-[132px] h-[132px] -rotate-90" role="img"
           aria-label="Sebaran bagian terhadap keseluruhan">
        <circle cx="50" cy="50" :r="R" fill="none" :stroke="BINGKAI.bantu" stroke-width="14" />

        <circle v-for="p in potongan" :key="p.label"
                cx="50" cy="50" :r="R" fill="none" stroke-width="14"
                :stroke="p.warna"
                :stroke-dasharray="`${p.panjang} ${KELILING - p.panjang}`"
                :stroke-dashoffset="p.geser"
                :style="{ opacity: disorot && disorot !== p.label ? 0.4 : 1,
                          transition: 'opacity .12s' }" />
      </svg>

      <div class="absolute inset-0 grid place-items-center text-center pointer-events-none">
        <div>
          <p class="text-[19px] font-bold text-cam-ink leading-none num">
            {{ tengah ?? total.toLocaleString('id-ID') }}
          </p>
          <p v-if="tengahLabel" class="text-[10px] text-stone-500 mt-0.5">{{ tengahLabel }}</p>
        </div>
      </div>
    </div>

    <ul class="min-w-0 flex-1 space-y-1" @pointerleave="disorot = null">
      <li v-for="p in potongan" :key="p.label">
        <button type="button"
                class="w-full flex items-center gap-2 text-[12px] rounded px-1 py-1
                       hover:bg-stone-50 focus:outline-none focus-visible:ring-2
                       focus-visible:ring-cam-lime"
                @pointerenter="disorot = p.label" @focus="disorot = p.label"
                @blur="disorot = null">
          <span class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ background: p.warna }"></span>
          <span class="text-stone-600 truncate text-left">{{ p.label }}</span>
          <span class="ml-auto font-bold text-cam-ink num shrink-0">{{ p.nilai }}</span>
          <span class="text-stone-400 num shrink-0 w-9 text-right">{{ p.persen }}%</span>
        </button>
      </li>
    </ul>
  </div>
</template>
