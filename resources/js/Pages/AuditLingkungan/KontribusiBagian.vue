<script setup lang="ts">
/**
 * Dari mana persentase pemenuhan datang: sumbangan tertimbang tiap
 * bagian, ditumpuk menjadi satu batang 0–100.
 *
 * Bobotnya timpang — bagian B sendirian 0,60, bagian C dan F masing-
 * masing 0,05 — dan justru itu yang ingin terlihat: kekurangan di B
 * memakan skor jauh lebih besar daripada kekurangan yang sama di F.
 * Sisa sampai 100 digambar berarsir, sebagai poin yang belum tercapai.
 */
import { computed, ref } from 'vue';
import type { SkorBagianAudit } from '../../types';
import { WARNA_BAGIAN, angka } from './bantu';

const props = defineProps<{ bagian: Record<string, SkorBagianAudit>; pemenuhan: number }>();

const daftar = computed(() => Object.values(props.bagian));
const sisa = computed(() => Math.max(0, 100 - props.pemenuhan));

/* Satu tooltip untuk seluruh batang. */
const wadah = ref<HTMLElement | null>(null);
const tip = ref<{ x: number; judul: string; baris: string[] } | null>(null);

function tunjuk(e: Event, judul: string, baris: string[]) {
  const t = e.currentTarget as HTMLElement;
  const w = wadah.value?.getBoundingClientRect();
  const r = t.getBoundingClientRect();
  if (!w) return;
  /* Dijepit supaya tooltip segmen C atau F yang sempit di tepi tidak
     terpotong kartu. */
  const x = Math.min(Math.max(r.left - w.left + r.width / 2, 95), w.width - 95);
  tip.value = { x, judul, baris };
}
</script>

<template>
  <div ref="wadah" class="relative">
    <div class="akl-tumpuk" role="list" :aria-label="`Persentase pemenuhan ${angka(pemenuhan)} dari 100`"
         @mouseleave="tip = null">
      <button v-for="b in daftar" :key="b.kunci" type="button" role="listitem"
              :style="{ width: `${b.hasil}%`, background: WARNA_BAGIAN[b.kunci] }"
              :aria-label="`Bagian ${b.kunci.toUpperCase()} ${b.judul}: ${angka(b.hasil)} dari ${angka(b.bobot * 100)}`"
              @mouseenter="tunjuk($event, `${b.kunci.toUpperCase()}. ${b.judul}`,
                                  [`Sumbangan ${angka(b.hasil)} dari ${angka(b.bobot * 100)} poin`,
                                   `Capaian ${angka(b.persen, 1)}% · bobot ${angka(b.bobot)}`])"
              @focus="tunjuk($event, `${b.kunci.toUpperCase()}. ${b.judul}`,
                             [`Sumbangan ${angka(b.hasil)} dari ${angka(b.bobot * 100)} poin`])"
              @blur="tip = null">
        <template v-if="b.hasil >= 7">{{ b.kunci.toUpperCase() }}</template>
      </button>
      <button v-if="sisa > 0.01" type="button" class="sisa" role="listitem" :style="{ width: `${sisa}%` }"
              :aria-label="`Belum tercapai ${angka(sisa)} poin`"
              @mouseenter="tunjuk($event, 'Belum tercapai', [`${angka(sisa)} poin dari 100`])"
              @blur="tip = null"></button>
    </div>
    <div class="akl-skala-sumbu num" aria-hidden="true"><span>0</span><span>50</span><span>100</span></div>

    <div v-if="tip" class="akl-tip" :style="{ left: `${tip.x}px`, top: '0px' }">
      <b>{{ tip.judul }}</b>
      <span v-for="(t, i) in tip.baris" :key="i" class="block">{{ t }}</span>
    </div>

    <ul class="akl-legenda">
      <li v-for="b in daftar" :key="b.kunci">
        <span class="akl-titik" :style="{ background: WARNA_BAGIAN[b.kunci] }"></span>
        <span><b>{{ b.kunci.toUpperCase() }}.</b> {{ b.judul }} —
          <b class="num">{{ angka(b.hasil) }}</b><span class="num"> / {{ angka(b.bobot * 100) }}</span></span>
      </li>
    </ul>
  </div>
</template>
