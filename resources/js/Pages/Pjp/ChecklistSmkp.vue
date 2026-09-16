<script setup lang="ts">
import { reactive } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const JAWABAN_OPTIONS: Record<string, string> = { ya: 'Y', tidak: 'T', na: 'N/A' };
const NILAI_OPTIONS: Record<string, string> = {
  '0': '0 - Tidak ada / tidak tersedia',
  '1': '1 - Belum terpenuhi',
  '2': '2 - Cukup memadai, perlu perbaikan',
  '3': '3 - Sudah memadai',
  na: 'N/A - Tidak berlaku',
};

interface Item { id: number; grup_kode: string | null; grup_nama: string | null; nomor: number; pertanyaan: string; petunjuk: string | null }
interface Category { id: number; kode: string; nama: string; bobot: number; items: Item[] }
interface Answer { jawaban: string | null; nilai: string | null; penjelasan: string | null }
interface Breakdown { kode: string; nama: string; persentase: number }

const props = defineProps<{
  pjp: { id: number; nama_perusahaan: string };
  categories: Category[];
  answers: Record<number, Answer>;
  score: { total_skor: number; total_bobot: number; persentase: number; kategori_risiko: string };
  categoryBreakdown: Breakdown[];
  legalitasStatus: { total: number; lengkap: number };
}>();

function scoreColor(p: number): string {
  if (p > 75) return 'text-red-600';
  if (p >= 55) return 'text-amber-600';
  if (p >= 36) return 'text-sky-600';
  return 'text-emerald-600';
}
function barColor(p: number): string {
  if (p >= 80) return '#5EAE38';
  if (p >= 60) return '#F0B429';
  if (p >= 40) return '#F0834A';
  return '#D03B3B';
}

const jawaban: Record<number, { jawaban: string; nilai: string; penjelasan: string }> = reactive({});
for (const cat of props.categories) {
  for (const item of cat.items) {
    const a = props.answers[item.id];
    jawaban[item.id] = { jawaban: a?.jawaban ?? '', nilai: a?.nilai ?? '', penjelasan: a?.penjelasan ?? '' };
  }
}

const form = useForm({ jawaban });

function simpan() {
  form.transform((d) => ({
    jawaban: Object.entries(d.jawaban).map(([item_id, v]: [string, any]) => ({ item_id: Number(item_id), ...v })),
  })).post(`/pjp/${props.pjp.id}/checklist-smkp`, { preserveScroll: true });
}

function grupBaru(items: Item[], index: number): boolean {
  const item = items[index];
  return !!item.grup_kode && items[index - 1]?.grup_kode !== item.grup_kode;
}

function bukaKategori(kode: string) {
  const el = document.getElementById(`kategori-${kode}`) as HTMLDetailsElement | null;
  if (el) {
    el.open = true;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}
</script>

<template>
  <Head :title="`Persyaratan PJP - ${pjp.nama_perusahaan}`" />

  <div class="max-w-4xl mx-auto">
    <div class="flex items-start justify-between gap-4 mb-6">
      <div>
        <h2 class="font-serif text-xl font-bold text-cam-ink">Persyaratan PJP</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">{{ pjp.nama_perusahaan }}</p>
      </div>
      <Link :href="`/pjp/${pjp.id}`" class="shrink-0 rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-semibold text-stone-600 hover:bg-stone-50">← Kembali</Link>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 mb-6 grid sm:grid-cols-3 gap-4">
      <div>
        <p class="text-[11.5px] text-stone-500">Total Skor</p>
        <p class="text-xl font-extrabold text-cam-ink mt-1">{{ score.total_skor }} / {{ score.total_bobot }}</p>
      </div>
      <div>
        <p class="text-[11.5px] text-stone-500">Persentase Kepatuhan</p>
        <p class="text-xl font-extrabold mt-1" :class="scoreColor(score.persentase)">{{ score.persentase }}%</p>
      </div>
      <div>
        <p class="text-[11.5px] text-stone-500">Kategori Risiko</p>
        <p class="text-xl font-extrabold mt-1" :class="scoreColor(score.persentase)">{{ score.kategori_risiko }}</p>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 mb-6">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Kelengkapan Checklist</h3>
      <div class="h-3 rounded-full bg-stone-100 overflow-hidden">
        <div class="h-full rounded-full transition-all duration-700"
             :style="{ width: `${score.persentase}%`, background: barColor(score.persentase) }"></div>
      </div>
      <p class="text-[11.5px] text-stone-400 mt-2">{{ score.persentase }}% dari total bobot checklist terpenuhi.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4 mb-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-[13px] font-bold text-cam-ink">Dokumen Legalitas (syarat wajib)</p>
        <p class="text-[11.5px] text-stone-500">Terpisah dari skor di atas — harus lengkap semua.</p>
      </div>
      <span class="rounded-full px-3 py-1 text-[12.5px] font-bold"
            :class="legalitasStatus.lengkap === legalitasStatus.total ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
        {{ legalitasStatus.lengkap }} / {{ legalitasStatus.total }} Lengkap
      </span>
    </div>

    <div id="rincian-skor-kategori" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 mb-6">
      <h3 class="text-[13px] font-bold text-cam-ink mb-1">Rincian Skor per Kategori</h3>
      <p class="text-[11.5px] text-stone-500 mb-3">Klik salah satu kategori untuk langsung membuka formulirnya di bawah.</p>
      <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2">
        <button v-for="cat in categoryBreakdown" :key="cat.kode" type="button" @click="bukaKategori(cat.kode)"
                class="flex flex-col items-center gap-1 rounded-xl p-2 hover:bg-stone-50 transition" :title="`${cat.kode}. ${cat.nama}: ${cat.persentase}%`">
          <div class="relative w-14 h-14 rounded-full grid place-items-center"
               :style="{ background: `conic-gradient(${barColor(cat.persentase)} ${cat.persentase * 3.6}deg, #EDF0F2 0deg)` }">
            <div class="absolute inset-[5px] rounded-full bg-white grid place-items-center text-[11px] font-bold text-cam-ink">{{ Math.round(cat.persentase) }}%</div>
          </div>
          <span class="text-[10.5px] font-bold text-stone-500">{{ cat.kode }}</span>
        </button>
      </div>
    </div>

    <p class="rounded-xl bg-stone-50 px-4 py-3 text-[12.5px] text-stone-600 mb-6">
      Kolom <strong>Nilai</strong> menentukan skor (0 = tidak ada, 1 = belum terpenuhi, 2 = cukup memadai perlu perbaikan,
      3 = sudah memadai, N/A = tidak berlaku). Kategori <strong>Dokumen Legalitas</strong> adalah syarat wajib terpisah
      dan tidak dihitung dalam skor di atas.
    </p>

    <form class="space-y-3" @submit.prevent="simpan">
      <details v-for="cat in categories" :key="cat.id" :id="`kategori-${cat.kode}`"
                class="bg-white rounded-2xl shadow-card border border-stone-100 group" :open="cat.kode === 'LEGALITAS'">
        <summary class="flex items-center justify-between gap-3 p-4 text-[13px] font-bold text-cam-ink cursor-pointer">
          <span>{{ cat.kode !== 'LEGALITAS' ? `${cat.kode}. ` : '' }}{{ cat.nama }}</span>
          <span class="flex items-center gap-2 shrink-0">
            <span v-if="cat.kode !== 'LEGALITAS'" class="rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-600">Bobot {{ cat.bobot }}</span>
            <svg class="w-4 h-4 text-stone-400 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
            </svg>
          </span>
        </summary>
        <div class="border-t border-stone-100 p-4 space-y-4">
          <template v-for="(item, index) in cat.items" :key="item.id">
            <p v-if="grupBaru(cat.items, index)" class="text-[11px] font-bold uppercase tracking-wide text-stone-400">
              {{ item.grup_kode }} · {{ item.grup_nama }}
            </p>
            <div class="rounded-xl border border-stone-100 p-3">
              <p class="text-[13px] font-semibold text-cam-ink whitespace-pre-line">{{ item.nomor }}. {{ item.pertanyaan }}</p>
              <p v-if="item.petunjuk" class="text-[11.5px] italic text-stone-400 mt-1 whitespace-pre-line">{{ item.petunjuk }}</p>
              <div class="grid sm:grid-cols-[100px_1fr_2fr] gap-2 mt-3">
                <select v-model="jawaban[item.id].jawaban" class="rounded-lg border border-stone-200 px-2 py-1.5 text-[12.5px]">
                  <option value="">Jawaban</option>
                  <option v-for="(l, v) in JAWABAN_OPTIONS" :key="v" :value="v">{{ l }}</option>
                </select>
                <select v-if="cat.kode !== 'LEGALITAS'" v-model="jawaban[item.id].nilai" class="rounded-lg border border-stone-200 px-2 py-1.5 text-[12.5px]">
                  <option value="">Nilai</option>
                  <option v-for="(l, v) in NILAI_OPTIONS" :key="v" :value="v">{{ l }}</option>
                </select>
                <input v-model="jawaban[item.id].penjelasan" placeholder="Penjelasan (opsional)" class="rounded-lg border border-stone-200 px-2 py-1.5 text-[12.5px]">
              </div>
            </div>
          </template>
        </div>
      </details>

      <div class="sticky bottom-4 flex justify-end">
        <button type="submit" :disabled="form.processing"
                class="lime-gradient shadow-glow rounded-xl text-white px-6 py-2.5 text-[13px] font-bold disabled:opacity-50">
          {{ form.processing ? 'Menyimpan…' : 'Simpan Checklist' }}
        </button>
      </div>
    </form>
  </div>
</template>
