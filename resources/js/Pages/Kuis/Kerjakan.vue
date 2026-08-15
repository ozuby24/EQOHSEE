<script setup lang="ts">
/**
 * Pengerjaan kuis.
 *
 * Kunci jawaban tidak pernah sampai ke halaman ini. Menilai di peramban
 * berarti jawabannya ada di perangkat peserta, dan siapa pun yang
 * membuka panel pengembang lulus tanpa membaca soalnya.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { HalamanKerjakanKuis } from '../../types';

const props = defineProps<HalamanKerjakanKuis>();

const form = useForm({ answers: {} as Record<number, string> });

const terjawab = computed(() => Object.keys(form.answers).length);
const lengkap = computed(() => terjawab.value === props.soal.length);

function kirim() {
  if (!lengkap.value && !confirm(
    `Masih ada ${props.soal.length - terjawab.value} soal yang belum dijawab. Kirim sekarang?`)) return;

  form.post(props.tautan.kirim);
}
</script>

<template>
  <Head :title="kuis.judul" />

  <div class="max-w-3xl mx-auto">
    <div class="brand-gradient rounded-2xl p-6 mb-5 text-white shadow-card">
      <h2 class="stat leading-tight">{{ kuis.judul }}</h2>
      <p class="text-[12px] text-white/50 mt-1.5">
        {{ kuis.jumlahSoal }} soal · nilai lulus {{ kuis.nilaiLulus }}
      </p>
      <p class="text-[12px] text-cam-lime-light mt-1.5 font-semibold">
        {{ terjawab }} dari {{ kuis.jumlahSoal }} terjawab
      </p>
    </div>

    <form class="space-y-3" @submit.prevent="kirim">
      <div v-for="(q, i) in soal" :key="q.id"
           class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <p class="text-[13.5px] font-bold text-cam-ink mb-3 leading-relaxed">{{ i + 1 }}. {{ q.soal }}</p>
        <div class="space-y-2">
          <label v-for="(opt, idx) in q.pilihan" :key="idx"
                 class="flex items-center gap-3 rounded-xl border px-4 py-2.5 cursor-pointer transition"
                 :class="form.answers[q.id] === String(idx)
                   ? 'border-cam-lime bg-cam-lime-soft/60'
                   : 'border-stone-200 hover:border-cam-lime hover:bg-cam-lime-soft/50'">
            <input v-model="form.answers[q.id]" type="radio" :value="String(idx)"
                   class="accent-[color:var(--eq-aksen,#F57C00)]">
            <span class="text-[13px] text-stone-600">{{ opt }}</span>
          </label>
        </div>
      </div>

      <button type="submit" :disabled="form.processing"
              class="lime-gradient shadow-glow w-full rounded-xl text-white py-3 text-[13.5px]
                     font-bold hover:brightness-105 transition disabled:opacity-40">
        {{ form.processing ? 'Mengirim…' : 'Kirim Jawaban' }}
      </button>
    </form>
  </div>
</template>
