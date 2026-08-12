<script setup lang="ts">
/**
 * Formulir evaluasi pasca-pelatihan.
 *
 * Pilihan kursus menyempit mengikuti peserta yang dipilih. Membiarkan
 * seluruh katalog terbuka membuat evaluasi mudah menempel pada kursus
 * yang tidak pernah diikuti orangnya — dan itu baru ketahuan saat
 * sertifikatnya terbit dengan judul yang salah.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import type { HalamanFormEvaluasi } from '../../types';

const props = defineProps<HalamanFormEvaluasi>();

const form = useForm({ ...props.awal });

/** Kursus yang benar-benar diambil peserta terpilih; kosong → seluruh katalog. */
const opsiKursus = computed(() => {
  const milik = props.kursusPeserta[form.user_id];

  return milik?.length
    ? milik.map((k) => ({ nilai: String(k.id), label: k.title }))
    : props.opsi.kursus;
});

// Kursus yang tidak lagi ada dalam daftar peserta baru dilepas, bukan
// dibiarkan tersimpan diam-diam pada nilai lama.
watch(() => form.user_id, () => {
  if (!opsiKursus.value.some((k) => k.nilai === form.course_id)) form.course_id = '';
});

const rerata = computed(() => {
  const n = props.medan.map((m) => Number(form[m.nama] || 0));
  return Math.round(n.reduce((a, b) => a + b, 0) / n.length);
});

function simpan() {
  if (props.tersimpan) form.put(props.tautan.simpan);
  else form.post(props.tautan.simpan);
}

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition';
const label = 'block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
const kepala = 'text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-2xl mx-auto">
    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-6"
          @submit.prevent="simpan">

      <div v-if="Object.keys(form.errors).length"
           class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
        <ul class="space-y-0.5">
          <li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li>
        </ul>
      </div>

      <div class="space-y-4">
        <p :class="kepala">Peserta &amp; Pelatihan</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label :class="label">Peserta</label>
            <select v-model="form.user_id" :class="isian">
              <option value="">— pilih peserta —</option>
              <option v-for="p in opsi.peserta" :key="p.nilai" :value="p.nilai">{{ p.label }}</option>
            </select>
          </div>
          <div>
            <label :class="label">Kursus</label>
            <select v-model="form.course_id" :class="isian">
              <option value="">— tanpa kursus —</option>
              <option v-for="k in opsiKursus" :key="k.nilai" :value="k.nilai">{{ k.label }}</option>
            </select>
            <p class="text-[10.5px] text-stone-400 mt-1">Otomatis menyaring kursus yang diambil peserta.</p>
          </div>
        </div>
        <div>
          <label :class="label">Nama trainer</label>
          <input v-model="form.trainer_name" :class="isian">
        </div>
      </div>

      <div class="space-y-4 pt-1">
        <div class="flex items-baseline justify-between">
          <p :class="kepala">Penilaian (0–100)</p>
          <p class="text-[11px] text-stone-400">
            Nilai akhir <span class="font-bold text-cam-ink num">{{ rerata }}</span> — rata-rata otomatis
          </p>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div v-for="m in medan" :key="m.nama">
            <label :class="label">{{ m.label }}</label>
            <input v-model="form[m.nama]" type="number" min="0" max="100" :class="isian">
            <p class="text-[10.5px] text-stone-400 mt-1">{{ m.ket }}</p>
          </div>
        </div>
        <div>
          <label :class="label">Rekomendasi</label>
          <select v-model="form.recommendation" :class="isian">
            <option value="">— pilih —</option>
            <option v-for="r in opsi.rekomendasi" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
      </div>

      <div class="space-y-4 pt-1">
        <p :class="kepala">Catatan Trainer</p>
        <div v-for="c in catatan" :key="c.nama">
          <label :class="label">{{ c.label }}</label>
          <textarea v-model="form[c.nama]" rows="3" :placeholder="c.ph"
                    :class="[isian, 'leading-relaxed']"></textarea>
        </div>
      </div>

      <div class="flex items-center gap-3 pt-1">
        <button type="submit" :disabled="form.processing"
                class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px]
                       font-bold hover:brightness-105 transition disabled:opacity-40">
          {{ form.processing ? 'Menyimpan…' : 'Simpan Evaluasi' }}
        </button>
        <a :href="tautan.batal" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Batal</a>
      </div>
    </form>
  </div>
</template>
