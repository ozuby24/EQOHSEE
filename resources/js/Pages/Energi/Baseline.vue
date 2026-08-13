<script setup lang="ts">
/**
 * Energy Baseline & Target.
 */
import { Head, useForm } from '@inertiajs/vue3';
import KepalaEnergi from '../../Components/KepalaEnergi.vue';
import { angka } from '../../energi';
import type { HalamanEnergiBaseline } from '../../types';

const props = defineProps<HalamanEnergiBaseline>();

const form = useForm({
  company_id: '',
  tahun: new Date().getFullYear(),
  baseline_gj_ton: '',
  target_gj_ton: '',
  catatan: '',
});

function simpan() {
  form.post(props.tautan.simpan, { preserveScroll: true, onSuccess: () => form.reset() });
}

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition';
const label = 'block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <KepalaEnergi judul="Energy Baseline &amp; Target"
        ket="Garis dasar pembanding seluruh capaian tahun berjalan. Sasaran energi berarti
             turun, bukan naik — target selalu harus lebih rendah daripada baseline."
        :dari="dari" :sampai="sampai" :rute="tautan.baseline" />

    <div class="grid gap-4 lg:grid-cols-5">

      <section class="kartu-lux rounded-2xl p-6 lg:col-span-2">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Tetapkan Baseline</h3>

        <form class="space-y-3.5 mt-5" @submit.prevent="simpan">
          <div v-if="Object.keys(form.errors).length"
               class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12px]">
            <ul class="space-y-0.5">
              <li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li>
            </ul>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label :class="label">Tahun</label>
              <input v-model="form.tahun" type="number" min="2000" max="2100" required :class="isian">
            </div>
            <div>
              <label :class="label">Perusahaan</label>
              <select v-model="form.company_id" :class="isian">
                <option value="">Tidak ditentukan</option>
                <option v-for="c in opsi.perusahaan" :key="c.nilai" :value="c.nilai">{{ c.label }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label :class="label">Baseline (GJ/ton)</label>
              <input v-model="form.baseline_gj_ton" type="number" step="0.001" min="0" max="100" required :class="isian">
            </div>
            <div>
              <label :class="label">Target (GJ/ton)</label>
              <input v-model="form.target_gj_ton" type="number" step="0.001" min="0" max="100" required :class="isian">
            </div>
          </div>

          <div>
            <label :class="label">Catatan</label>
            <textarea v-model="form.catatan" rows="3"
                      placeholder="Dasar penetapan angka ini." :class="isian"></textarea>
          </div>

          <button type="submit" :disabled="form.processing"
                  class="w-full rounded-xl bg-cam-ink text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-110 transition disabled:opacity-40">
            {{ form.processing ? 'Menyimpan…' : 'Simpan Baseline' }}
          </button>
        </form>
      </section>

      <section class="kartu-lux rounded-2xl p-6 lg:col-span-3">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Riwayat Baseline</h3>

        <div v-if="daftar.length" class="space-y-3 mt-5">
          <div v-for="b in daftar" :key="b.id" class="rounded-xl border p-4"
               :class="b.berlaku ? 'border-cam-lime/40 bg-cam-lime-soft/30' : 'border-stone-100'">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="text-[13px] font-bold text-cam-ink">{{ b.tahun }}</span>
                  <span v-if="b.berlaku" class="text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-0.5 rounded-lg bg-cam-lime-deep">
                    Berlaku
                  </span>
                </div>
                <div class="text-[10.5px] text-stone-400 mt-1">{{ b.perusahaan ?? 'Seluruh perusahaan' }}</div>
              </div>
              <div class="text-right shrink-0">
                <div class="num text-[12.5px] font-bold text-cam-ink">
                  {{ angka(b.baselineGjTon, 3) }} → {{ angka(b.targetGjTon, 3) }}
                </div>
                <div class="text-[10px] text-stone-400 mt-0.5">GJ/ton, target turun {{ angka(b.penurunanTarget, 1) }}%</div>
              </div>
            </div>
            <p v-if="b.catatan" class="text-[11.5px] text-stone-500 mt-2.5 leading-relaxed">{{ b.catatan }}</p>
          </div>
        </div>
        <p v-else class="text-[12px] text-stone-400 mt-6">Belum ada baseline yang ditetapkan.</p>
      </section>
    </div>

  </div>
</template>
