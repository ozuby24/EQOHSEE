<script setup lang="ts">
/**
 * Pengerjaan evaluasi SOP, berbatas waktu.
 *
 * Penghitung mundur hanya bantuan baca; yang menentukan tetap server.
 * Ketika waktunya habis jawabannya dikirim apa adanya — dibiarkan
 * menggantung, peserta kehilangan seluruh pekerjaannya tanpa nilai.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { HalamanKerjakanSop } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, batal, lanjut } = useDialog();


const props = defineProps<HalamanKerjakanSop>();

const form = useForm({ answers: {} as Record<number, string> });

const sisa = ref(props.ev.durasiDetik);
let jam: ReturnType<typeof setInterval> | undefined;

const waktu = computed(() => {
  const d = Math.max(0, sisa.value);
  return `${String(Math.floor(d / 60)).padStart(2, '0')}:${String(d % 60).padStart(2, '0')}`;
});

const terjawab = computed(() => Object.keys(form.answers).length);

async function kirim(paksa = false) {
  if (form.processing) return;

  if (!paksa && terjawab.value < props.soal.length && !await tanya(
    `Masih ada ${props.soal.length - terjawab.value} soal yang belum dijawab. Kirim sekarang?`)) return;

  form.post(props.tautan.kirim);
}

onMounted(() => {
  if (!props.ev.durasiDetik) return;

  jam = setInterval(() => {
    sisa.value--;
    if (sisa.value <= 0) {
      clearInterval(jam);
      kirim(true);
    }
  }, 1000);
});

onBeforeUnmount(() => clearInterval(jam));
</script>

<template>
  <Head :title="ev.judul" />

  <div class="max-w-3xl mx-auto">
    <div class="brand-gradient rounded-2xl p-6 mb-5 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-16 -top-16 w-48 h-48 rounded-full bg-cam-lime/20 blur-3xl"></div>
      <div class="relative flex flex-wrap items-center justify-between gap-4">
        <div>
          <h2 class="stat leading-tight">{{ ev.judul }}</h2>
          <p class="text-[12px] text-white/50 mt-1.5">
            {{ ev.jumlahSoal }} soal · lulus ≥ {{ ev.nilaiLulus }}
          </p>
          <p class="text-[12px] text-cam-lime-light mt-1.5 font-semibold">
            {{ terjawab }} dari {{ ev.jumlahSoal }} terjawab
          </p>
        </div>
        <div v-if="ev.durasiDetik" class="glass rounded-xl px-4 py-2.5 font-mono font-bold text-[16px]"
             :class="sisa <= 60 ? 'text-red-300' : 'text-cam-lime-light'">
          {{ waktu }}
        </div>
      </div>
    </div>

    <form class="space-y-3" @submit.prevent="kirim()">
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

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>
