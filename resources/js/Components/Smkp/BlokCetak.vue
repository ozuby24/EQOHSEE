<script setup lang="ts">
/**
 * Satu blok berkas cetak (lib/blokCetak). Dipakai dua kali: di wadah ukur
 * tersembunyi dan di lembar cetak — keduanya harus sama persis supaya
 * pembagian lembar tepat. Tabel dapat dicetak sebagian (`dari`..`sampai`).
 */
import { computed, h, type FunctionalComponent } from 'vue';
import type { BlokCetak, Isi } from '../../smkp/lib/blokCetak';

const props = defineProps<{ blok: BlokCetak; dari?: number; sampai?: number }>();

const Teks: FunctionalComponent<{ isi: Isi }> = (p) => p.isi.map((r) => (typeof r === 'string' ? r
  : h('span', { class: [r.b && 'font-bold', r.i && 'italic', r.s && 'line-through'] }, r.t)));
Teks.props = ['isi'];

const INDEN = ['', 'pl-[26px]', 'pl-[44px]'];
const inden = computed(() => {
  const b = props.blok;
  if (b.k === 'judul') return b.tingkat === 2 ? INDEN[1] : b.tingkat === 3 ? INDEN[2] : '';
  if (b.k === 'teks' || b.k === 'daftar') return INDEN[b.inden ?? 2];
  return b.k === 'ttd' ? '' : INDEN[2];
});
const baris = computed(() => (props.blok.k === 'tabel' ? props.blok.baris.slice(props.dari ?? 0, props.sampai ?? props.blok.baris.length) : []));
// Potongan tabel yang berlanjut ke lembar berikutnya tidak membawa jarak bawah.
const jarak = computed(() => {
  const b = props.blok;
  if (b.k === 'tabel') return (props.sampai ?? b.baris.length) < b.baris.length ? '' : 'pb-4';
  if (b.k === 'judul') return ['pb-6', 'pt-2 pb-2.5', 'pb-2', 'pb-1.5'][b.tingkat];
  return 'pb-2.5';
});
const tanda = (gaya: string, i: number, awalan = '') => (gaya === '•' ? '•' : gaya === '1)' ? `${i + 1})` : `${awalan}${i + 1}.`);
</script>

<template>
  <div class="flow-root" :class="[inden, jarak]">
    <template v-if="blok.k === 'judul'">
      <div v-if="blok.tingkat === 0" class="text-center text-[12.5px] font-bold uppercase leading-snug text-slate-900">
        <p v-for="(r, i) in blok.isi" :key="i">{{ typeof r === 'string' ? r : r.t }}</p>
      </div>
      <p v-else class="flex font-bold text-slate-900" :class="blok.tingkat === 1 ? 'text-[12px] uppercase' : 'text-[11.5px]'">
        <span v-if="blok.nomor" class="shrink-0 pr-1.5" :class="blok.tingkat === 1 ? 'min-w-[26px]' : 'min-w-[18px]'">{{ blok.nomor }}</span>
        <span class="text-justify"><Teks :isi="blok.isi" /></span>
      </p>
    </template>

    <p v-else-if="blok.k === 'teks'" class="text-justify whitespace-pre-line text-slate-800"><Teks :isi="blok.isi" /></p>

    <ol v-else-if="blok.k === 'daftar'" class="space-y-0.5 text-slate-800">
      <li v-for="(b, i) in blok.butir" :key="i">
        <div class="flex"><span class="shrink-0" :class="blok.awalan ? 'w-9' : 'w-6'">{{ tanda(blok.gaya, i, blok.awalan) }}</span><span class="min-w-0 text-justify whitespace-pre-line"><Teks :isi="b.isi" /></span></div>
        <ol v-if="b.anak?.length" class="mt-0.5 space-y-0.5 pl-6">
          <li v-for="(a, j) in b.anak" :key="j" class="flex"><span class="w-6 shrink-0">{{ String.fromCharCode(97 + j) }}.</span><span class="min-w-0 text-justify whitespace-pre-line"><Teks :isi="a" /></span></li>
        </ol>
      </li>
    </ol>

    <ul v-else-if="blok.k === 'rincian'" class="space-y-0.5 text-slate-800">
      <li v-for="(r, i) in blok.baris" :key="i" class="flex">
        <span v-if="!blok.tanpaTitik" class="w-5 shrink-0">•</span><span class="w-48 shrink-0">{{ r[0] }}</span><span class="w-3 shrink-0">:</span>
        <span class="min-w-0 whitespace-pre-line"><Teks :isi="r[1]" /></span>
      </li>
    </ul>

    <table v-else-if="blok.k === 'tabel'" data-pecah class="tabel-form table-fixed text-slate-800">
      <colgroup><col v-for="(c, j) in blok.kolom" :key="j" :style="{ width: c.lebar }"></colgroup>
      <thead v-if="blok.kepala"><tr><th v-for="(c, j) in blok.kolom" :key="j"><Teks :isi="c.judul ?? []" /></th></tr></thead>
      <tbody>
        <tr v-for="(b, r) in baris" :key="(dari ?? 0) + r">
          <!-- Baris bersel tunggal = judul kelompok selebar tabel. -->
          <td v-if="b.length === 1 && blok.kolom.length > 1" :colspan="blok.kolom.length" class="font-semibold bg-slate-50"><Teks :isi="b[0]" /></td>
          <td v-for="(sel, j) in b" v-else :key="j" class="whitespace-pre-line" :class="blok.kolom[j]?.tengah ? 'text-center' : blok.kolom[j]?.rata ? 'text-justify' : ''"><Teks :isi="sel" /></td>
        </tr>
      </tbody>
    </table>

    <slot v-else />
  </div>
</template>
