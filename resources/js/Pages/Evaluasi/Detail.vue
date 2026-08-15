<script setup lang="ts">
/**
 * Hasil evaluasi seorang peserta.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import type { HalamanDetailEvaluasi } from '../../types';

const props = defineProps<HalamanDetailEvaluasi>();

function hapus() {
  if (!confirm('Hapus evaluasi ini?')) return;
  router.delete(props.tautan.hapus);
}

const adaCatatan = props.ev.strengths || props.ev.improvements || props.ev.notes;
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-3xl mx-auto space-y-5">

    <section class="brand-gradient rounded-2xl p-7 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
      <div class="relative flex flex-wrap items-center justify-between gap-6">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">
            Evaluasi Pasca-Pelatihan
          </span>
          <h2 class="stat mt-2 leading-tight">{{ ev.peserta ?? '—' }}</h2>
          <p class="text-[12.5px] text-white/50 mt-1">{{ ev.kursus ?? 'Tanpa kursus' }}</p>
          <p class="text-[11px] text-white/35 mt-2.5">
            Dinilai oleh {{ ev.trainer ?? '—' }} · {{ ev.tanggal }}
          </p>
        </div>
        <div class="glass rounded-2xl px-7 py-5 text-center">
          <div class="stat stat-xl leading-none text-cam-lime-light">{{ ev.nilai }}</div>
          <div class="text-[9.5px] uppercase tracking-[0.15em] text-white/40 mt-2 font-bold">Nilai Akhir</div>
        </div>
      </div>

      <div v-if="ev.rekomendasi"
           class="relative mt-5 inline-block glass rounded-xl px-4 py-2 text-[12px] font-bold">
        📋 {{ ev.rekomendasi }}
      </div>
    </section>

    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
      <h3 class="text-[14px] font-bold text-cam-ink mb-4">Rincian Penilaian</h3>
      <div class="space-y-4">
        <div v-for="r in rincian" :key="r.label">
          <div class="flex items-center justify-between text-[12.5px] mb-1.5">
            <span class="font-semibold text-stone-600">{{ r.label }}</span>
            <span class="font-bold" :class="r.nilai >= 70 ? 'text-cam-lime-deep' : 'text-stone-400'">
              {{ r.nilai }}
            </span>
          </div>
          <div class="h-2 rounded-full bg-stone-100 overflow-hidden">
            <div class="h-full rounded-full lime-gradient transition-all" :style="{ width: r.nilai + '%' }"></div>
          </div>
        </div>
      </div>
    </section>

    <div v-if="adaCatatan" class="grid gap-3 sm:grid-cols-2">
      <div v-if="ev.strengths" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-cam-lime-deep">Kekuatan</div>
        <p class="text-[13px] text-stone-600 mt-2 leading-relaxed whitespace-pre-line">{{ ev.strengths }}</p>
      </div>
      <div v-if="ev.improvements" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Perlu Ditingkatkan</div>
        <p class="text-[13px] text-stone-600 mt-2 leading-relaxed whitespace-pre-line">{{ ev.improvements }}</p>
      </div>
      <div v-if="ev.notes" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 sm:col-span-2">
        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Catatan Tambahan</div>
        <p class="text-[13px] text-stone-600 mt-2 leading-relaxed whitespace-pre-line">{{ ev.notes }}</p>
      </div>
    </div>

    <div class="flex items-center gap-2.5">
      <Link :href="tautan.daftar"
            class="rounded-xl border border-stone-200 px-5 py-2.5 text-[12.5px] font-bold
                   text-stone-600 hover:bg-stone-50 transition">← Kembali</Link>
      <template v-if="bolehUbah">
        <a :href="tautan.ubah"
           class="rounded-xl border border-stone-200 px-5 py-2.5 text-[12.5px] font-bold
                  text-cam-lime-deep hover:bg-cam-lime-soft transition">Edit</a>
        <button type="button" @click="hapus"
                class="rounded-xl px-5 py-2.5 text-[12.5px] font-bold text-red-500
                       hover:bg-red-50 transition">Hapus</button>
      </template>
    </div>
  </div>
</template>
