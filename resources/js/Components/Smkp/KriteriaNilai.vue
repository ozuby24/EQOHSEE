<script setup lang="ts">
import { computed, ref } from 'vue';
import { KELAS_NADA, nadaNilai } from '../../smkp/lib/nilai';
import type { Rubrik } from '../../smkp/types';

/*
 * Tangga kriteria penilaian satu butir SMKP: deskripsi resmi tiap nilai
 * 0..maks dan kondisi N/A dari Kepdirjen 185.K/37.04/DJB/2019. Bila
 * `bisaPilih`, mengetuk satu tingkat memilih nilai itu untuk butirnya.
 */
const props = withDefaults(defineProps<{
  rubrik: Rubrik | null;
  maks: number;
  v?: string;
  bisaPilih?: boolean;
  varian?: 'iup' | 'iujp';
}>(), { v: '', bisaPilih: false, varian: 'iup' });
const emit = defineEmits<{ pilih: [v: string] }>();

const pilihanVarian = ref(props.varian);
const aktif = computed(() => {
  const r = props.rubrik;
  if (!r) return null;
  if (!r.varian) return { nilai: r.nilai ?? [], na: r.na ?? null };
  return r.varian.find((x) => x.untuk === pilihanVarian.value) ?? r.varian[0];
});

const tingkat = computed(() => [
  ...(aktif.value?.nilai ?? []).map((t) => ({ kode: String(t.n), label: String(t.n), teks: t.teks })),
  ...(aktif.value?.na ? [{ kode: 'N/A', label: 'N/A', teks: `Kriteria audit tidak dapat diaplikasikan: ${aktif.value.na.replace(/^kriteria audit tidak dapat diaplikasikan dengan\s*/i, '')}` }] : []),
]);

function pilih(kode: string) {
  if (props.bisaPilih) emit('pilih', kode);
}
</script>

<template>
  <div v-if="rubrik && aktif" class="space-y-2.5">
    <div v-if="rubrik.varian" class="inline-flex flex-wrap rounded-lg bg-slate-100 p-0.5 text-[11.5px] font-semibold" role="tablist" aria-label="Versi kriteria">
      <button v-for="x in rubrik.varian" :key="x.untuk" type="button" role="tab" :aria-selected="pilihanVarian === x.untuk" :title="x.label"
              class="rounded-md px-2.5 py-1 transition" :class="pilihanVarian === x.untuk ? 'bg-white text-navy-900 shadow-kartu' : 'text-slate-500 hover:text-navy-900'"
              @click="pilihanVarian = x.untuk">
        {{ x.untuk === 'iujp' ? 'Pemegang IUJP' : 'Pemegang IUP / IUPK / IPR' }}
      </button>
    </div>

    <ol class="space-y-1.5">
      <li v-for="t in tingkat" :key="t.kode">
        <component :is="bisaPilih ? 'button' : 'div'" :type="bisaPilih ? 'button' : undefined"
                   class="w-full text-left flex items-start gap-3 rounded-lg border px-3 py-2.5 transition"
                   :class="[
                     v === t.kode ? KELAS_NADA[nadaNilai(t.kode, maks)].lembut : 'bg-white border-slate-200/80',
                     bisaPilih && v !== t.kode ? 'hover:border-brand-300 hover:bg-brand-50/40' : '',
                   ]"
                   :aria-pressed="bisaPilih ? v === t.kode : undefined" @click="pilih(t.kode)">
          <span class="shrink-0 grid place-items-center min-w-[28px] h-7 px-1 rounded-md text-[11.5px] font-bold tabular-nums"
                :class="v === t.kode ? KELAS_NADA[nadaNilai(t.kode, maks)].lencana : 'bg-slate-100 text-slate-600'">{{ t.label }}</span>
          <span class="flex-1 min-w-0 text-[12.5px] leading-relaxed whitespace-pre-line" :class="v === t.kode ? 'text-navy-900' : 'text-slate-600'">{{ t.teks }}</span>
        </component>
      </li>
    </ol>

    <p v-if="rubrik.catatan" class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-[11.5px] leading-relaxed text-amber-800">{{ rubrik.catatan }}</p>
    <p class="text-[11px] text-slate-400">Kriteria penilaian Kepdirjen Minerba 185.K/37.04/DJB/2019, hal. {{ rubrik.hal }}.</p>
  </div>
</template>
