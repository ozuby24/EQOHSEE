<script setup lang="ts">
/**
 * PTPKKP — Profil.
 *
 * Identitas penilaian. Perubahan yang belum tersimpan ditandai dan tombol
 * simpan mati selama tidak ada yang berubah — pada formulir yang jarang
 * disentuh, tombol yang selalu menyala membuat orang menekannya untuk
 * memastikan, lalu ragu apakah sesuatu benar-benar tersimpan.
 */
import { computed, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import type { HalamanProfil } from '../../types';

const props = defineProps<HalamanProfil>();

const form = reactive({ ...props.isian });
const awal = ref(JSON.stringify(props.isian));

watch(() => props.isian, (baru) => {
  Object.assign(form, baru);
  awal.value = JSON.stringify(baru);
});

const kotor = computed(() => JSON.stringify(form) !== awal.value);
const menyimpan = ref(false);

const medan: Array<[keyof typeof form, string]> = [
  ['judul', 'Judul penilaian'],
  ['organisasi', 'Organisasi yang dinilai'],
  ['site', 'Lokasi / site'],
  ['komoditas', 'Komoditas'],
  ['ktt', 'Kepala Teknik Tambang'],
  ['basis', 'Dasar hukum / acuan'],
];

function simpan() {
  menyimpan.value = true;

  router.post(`/tpkkp/profil?tahun=${props.tahun}`, { ...form }, {
    preserveScroll: true,
    onSuccess: () => { awal.value = JSON.stringify(form); },
    onFinish:  () => { menyimpan.value = false; },
  });
}
</script>

<template>
  <Head title="PTPKKP — Profil" />

  <div class="max-w-3xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
      <h3 class="text-[14px] font-bold text-cam-ink">Profil Penilaian {{ tahun }}</h3>

      <div v-for="[nama, label] in medan" :key="nama">
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">
          {{ label }}
        </label>
        <input v-model="form[nama]" type="text" :disabled="!bisaSunting"
               class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-3.5 py-2.5 text-[13px]">
      </div>

      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">
          Status
        </label>
        <select v-model="form.status" :disabled="!bisaSunting"
                class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-3.5 py-2.5 text-[13px] font-semibold" aria-label="Status">
          <option value="draft">Draft</option>
          <option value="aktif">Aktif</option>
          <option value="selesai">Selesai</option>
        </select>
      </div>

      <div v-if="bisaSunting" class="flex items-center gap-3 flex-wrap pt-1">
        <button type="button" :disabled="menyimpan || !kotor" @click="simpan"
                class="lime-gradient shadow-glow rounded-xl text-white px-6 py-2.5 text-[13px] font-bold
                       hover:brightness-105 transition disabled:opacity-40 disabled:cursor-not-allowed">
          {{ menyimpan ? 'Menyimpan…' : 'Simpan profil' }}
        </button>
        <span v-if="kotor" class="text-[11.5px] text-amber-700 font-semibold">
          Ada perubahan yang belum tersimpan.
        </span>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 p-6">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Roster entitas</h3>

      <p v-if="!roster.length" class="text-[12.5px] text-stone-400">
        Belum ada metode yang memakai entitas pada periode ini.
      </p>

      <div v-else class="space-y-3">
        <div v-for="r in roster" :key="r.kode">
          <div class="text-[11px] font-bold text-stone-500 mb-1.5">
            {{ r.kode }} · {{ r.label }} ({{ r.entitas.length }})
          </div>
          <div class="flex flex-wrap gap-1.5">
            <span v-for="e in r.entitas" :key="e"
                  class="text-[11px] px-2 py-1 rounded-lg bg-stone-50 border border-stone-200 text-stone-600">
              {{ e }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
