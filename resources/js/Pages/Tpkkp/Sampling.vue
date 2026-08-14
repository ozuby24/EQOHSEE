<script setup lang="ts">
/**
 * PTPKKP — Kalkulator Slovin.
 *
 * Angkanya dihitung ulang sambil mengetik, tapi bukan di peramban: tiap
 * perubahan memuat ulang prop `alokasi` saja dari halaman yang sama.
 * Menyalin rumus Slovin ke sini akan lebih cepat, namun pembulatannya
 * harus persis sama dengan yang di server — dan selisih satu orang antara
 * angka yang tampak saat mengetik dan angka yang akhirnya tersimpan tidak
 * menimbulkan galat apa pun, hanya laporan yang keliru.
 *
 * Selama pratinjau berjalan, hasilnya diredupkan supaya jelas bahwa yang
 * terlihat belum tentu yang tersimpan.
 */
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import type { HalamanSampling } from '../../types';

const props = defineProps<HalamanSampling>();

const N = reactive<Record<string, number>>({});
const e = ref(props.e);

function pulihkan() {
  for (const s of props.strata) N[s.nama] = s.N;
  e.value = props.e;
}

pulihkan();

const tersimpan = ref(JSON.stringify({ N: { ...N }, e: e.value }));
const kotor = computed(() => JSON.stringify({ N: { ...N }, e: e.value }) !== tersimpan.value);

const menghitung = ref(false);
const menyimpan  = ref(false);

/* ── pratinjau ── */

let jeda: ReturnType<typeof setTimeout> | null = null;

watch([() => ({ ...N }), e], () => {
  if (jeda) clearTimeout(jeda);

  jeda = setTimeout(() => {
    menghitung.value = true;

    router.reload({
      only: ['alokasi'],
      data: { N: { ...N }, e: e.value },
      replace: true,
      onFinish: () => { menghitung.value = false; },
    });
  }, 350);
});

onBeforeUnmount(() => { if (jeda) clearTimeout(jeda); });

/* ── simpan ── */

function simpan() {
  menyimpan.value = true;

  router.post(`/tpkkp/sampling?tahun=${props.tahun}`, { N: { ...N }, e: e.value }, {
    preserveScroll: true,
    onSuccess: () => { tersimpan.value = JSON.stringify({ N: { ...N }, e: e.value }); },
    onFinish:  () => { menyimpan.value = false; },
  });
}
</script>

<template>
  <Head title="PTPKKP — Kalkulator Slovin" />

  <div class="max-w-4xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div class="bg-white rounded-2xl border border-stone-200 p-6">
      <h3 class="text-[13px] font-bold text-cam-ink mb-1">Penentuan Jumlah Sampel — Slovin</h3>
      <p class="text-[11.5px] text-stone-500 mb-4">
        n = N / (1 + N·e²), dialokasikan proporsional per strata.
      </p>

      <div class="grid sm:grid-cols-3 gap-3 mb-4">
        <div v-for="s in strata" :key="s.nama">
          <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">
            Populasi {{ s.nama }}
          </label>
          <input v-model.number="N[s.nama]" type="number" min="0" :disabled="!bisaSunting"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[13px] num">
        </div>

        <div>
          <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">
            Margin galat (e)
          </label>
          <input v-model.number="e" type="number" step="0.01" :min="eMin" :max="eMaks"
                 :disabled="!bisaSunting"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[13px] num">
        </div>
      </div>

      <div v-if="bisaSunting" class="flex items-center gap-3 flex-wrap">
        <button type="button" :disabled="menyimpan || !kotor" @click="simpan"
                class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[13px] font-bold
                       hover:brightness-105 transition disabled:opacity-40 disabled:cursor-not-allowed">
          {{ menyimpan ? 'Menyimpan…' : 'Simpan' }}
        </button>
        <span v-if="kotor" class="text-[11.5px] text-amber-700 font-semibold">
          Hasil di bawah sudah memakai angka baru, tapi belum tersimpan.
        </span>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden transition-opacity"
         :class="menghitung ? 'opacity-50' : ''">
      <div class="px-5 py-3 border-b border-stone-100 flex items-center justify-between gap-3">
        <h3 class="text-[13px] font-bold text-cam-ink">Hasil Perhitungan</h3>
        <span class="num text-[12px] font-bold text-cam-ink">
          N {{ alokasi.N }} → n {{ alokasi.n }}
        </span>
      </div>

      <div class="tabel-scroll">
        <table class="w-full text-[12px]">
          <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
            <tr>
              <th class="text-left px-5 py-2.5 font-bold">Strata</th>
              <th class="text-right px-3 py-2.5 font-bold">Populasi</th>
              <th class="text-right px-5 py-2.5 font-bold">Sampel</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in alokasi.baris" :key="r.nama" class="border-b border-stone-50">
              <td class="px-5 py-2.5">{{ r.nama }}</td>
              <td class="px-3 py-2.5 text-right num">{{ r.N }}</td>
              <td class="px-5 py-2.5 text-right num font-bold">{{ r.nh }}</td>
            </tr>
            <tr class="bg-stone-50 font-bold">
              <td class="px-5 py-2.5">Total</td>
              <td class="px-3 py-2.5 text-right num">{{ alokasi.N }}</td>
              <td class="px-5 py-2.5 text-right num">{{ alokasi.total }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="alokasi.total !== alokasi.n" class="px-5 py-3 text-[11.5px] text-stone-500 border-t border-stone-100">
        Jumlah sampel per strata ({{ alokasi.total }}) berbeda dari n ({{ alokasi.n }})
        karena tiap strata dibulatkan sendiri-sendiri.
      </p>
    </div>
  </div>
</template>
