<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import BingkaiDialog from './BingkaiDialog.vue';
import { CARA_HITUNG, OBJEK_POPULASI, bacaJumlah, barisJumlah, hitungSampel, type CaraHitung } from '../../../smkp/lib/jumlahSampel';

/*
 * Kalkulator jumlah sampel per kriteria (port DBEST #23): objek & jumlah
 * populasi dan cara penentuan. Hasilnya satu baris baku di kotak sampel —
 * "Jumlah sampel: 55 dari populasi 120 manpower (Slovin, e 10%)" — yang
 * ikut tercetak dan dihitung ulang server saat Rencana disimpan.
 */
const props = defineProps<{ buka: boolean; kode: string; nama: string; sampel: string }>();
const emit = defineEmits<{ 'update:buka': [v: boolean]; terapkan: [baris: string] }>();

const objek = ref('manpower');
const populasi = ref<number | null>(null);
const cara = ref<CaraHitung>('slovin');
const e = ref(10);
const p = ref(10);
const n = ref<number | null>(null);
const idObjek = 'kalkulator-objek-populasi';

// Dibuka lagi pada kriteria yang sudah berbaris jumlah: isiannya dibaca kembali.
watch(() => props.buka, (b) => {
  if (!b) return;
  const ada = (props.sampel ?? '').split(/\r\n|\r|\n/)
    .map((x) => bacaJumlah(x.replace(/^\s*(?:[-•*]\s+)?/u, '')))
    .find((x) => x !== null);
  objek.value = ada?.objek || 'manpower';
  populasi.value = ada?.populasi ?? null;
  cara.value = ada?.cara ?? 'slovin';
  e.value = ada?.cara === 'slovin' && ada.param !== null ? Number(ada.param) : 10;
  p.value = ada?.cara === 'persen' && ada.param !== null ? Number(ada.param) : 10;
  n.value = ada?.cara === 'manual' ? Number(ada.param) : null;
});

const param = computed(() => (cara.value === 'slovin' ? e.value : cara.value === 'persen' ? p.value : cara.value === 'manual' ? n.value : null));
const hasil = computed(() => hitungSampel(cara.value, populasi.value, param.value));
const baris = computed(() => barisJumlah(cara.value, populasi.value, objek.value, param.value));

function terapkan() {
  if (!baris.value) return;
  emit('terapkan', baris.value);
  emit('update:buka', false);
}
</script>

<template>
  <BingkaiDialog :buka="buka" judul="Kalkulator jumlah sampel" :sub="kode ? `${kode} · ${nama}` : undefined" ikon="kalkulator" lebar="max-w-2xl"
                 @update:buka="(v) => emit('update:buka', v)">
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block">
        <span class="label">Objek populasi</span>
        <input v-model="objek" :list="idObjek" maxlength="60" class="input" placeholder="Mis. manpower, unit, dokumen">
        <datalist :id="idObjek"><option v-for="o in OBJEK_POPULASI" :key="o" :value="o" /></datalist>
      </label>
      <label class="block">
        <span class="label">Jumlah populasi (N)</span>
        <input v-model.number="populasi" type="number" min="1" max="9999999" step="1" inputmode="numeric" class="input" placeholder="Mis. 120">
      </label>
    </div>

    <fieldset class="mt-4">
      <legend class="label">Cara penentuan</legend>
      <div class="mt-1 grid gap-2 sm:grid-cols-2">
        <label v-for="(c, k) in CARA_HITUNG" :key="k"
               class="flex cursor-pointer items-start gap-2.5 rounded-xl border px-3 py-2.5 transition-colors"
               :class="cara === k ? 'border-brand-500 bg-brand-50/60' : 'border-slate-200 hover:border-slate-300'">
          <input v-model="cara" type="radio" name="cara-sampel" :value="k" class="mt-0.5 text-brand-600 focus:ring-brand-500">
          <span class="min-w-0">
            <span class="block text-[13px] font-semibold text-navy-900">{{ c.label }}</span>
            <span class="mt-0.5 block text-[11.5px] leading-relaxed text-slate-500">{{ c.ket }}</span>
          </span>
        </label>
      </div>
    </fieldset>

    <div v-if="cara === 'slovin' || cara === 'persen' || cara === 'manual'" class="mt-4 grid gap-4 sm:grid-cols-2">
      <label v-if="cara === 'slovin'" class="block">
        <span class="label">Batas kesalahan e (%)</span>
        <input v-model.number="e" type="number" min="1" max="30" step="0.5" class="input">
      </label>
      <label v-if="cara === 'persen'" class="block">
        <span class="label">Persentase p (%)</span>
        <input v-model.number="p" type="number" min="1" max="100" step="0.5" class="input">
      </label>
      <label v-if="cara === 'manual'" class="block">
        <span class="label">Jumlah sampel (n)</span>
        <input v-model.number="n" type="number" min="1" :max="populasi ?? undefined" step="1" class="input" placeholder="Tidak melebihi N">
      </label>
    </div>

    <div class="mt-5 rounded-xl border border-teal-100 bg-teal-50/60 px-4 py-3">
      <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-teal-800">Hasil</p>
      <p v-if="hasil !== null" class="mt-1 text-[22px] font-bold leading-none text-navy-900 tabular-nums">
        n = {{ hasil }} <span class="text-[13px] font-semibold text-slate-500">dari N = {{ populasi }}</span>
      </p>
      <p v-else class="mt-1 text-[12.5px] text-slate-500">Isi jumlah populasi{{ cara === 'manual' ? ' dan jumlah sampel' : '' }} untuk melihat hasilnya.</p>
      <p v-if="baris" class="mt-2 rounded-lg bg-white px-3 py-2 font-mono text-[12px] text-navy-900">{{ baris }}</p>
      <p class="mt-2 text-[11.5px] leading-relaxed text-slate-500">Baris ini ditulis di kotak sampel (menggantikan baris jumlah yang sudah ada), ikut tercetak, dan dihitung ulang saat Rencana disimpan.</p>
    </div>

    <template #kaki>
      <button type="button" class="btn-hantu" @click="emit('update:buka', false)">Batal</button>
      <button type="button" class="btn-utama" :disabled="!baris" @click="terapkan">Terapkan ke sampel</button>
    </template>
  </BingkaiDialog>
</template>
