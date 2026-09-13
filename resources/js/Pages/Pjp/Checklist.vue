<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import PjpDonat from '../../Components/PjpDonat.vue';

type Item = {
  id: number; nomor: number; pertanyaan: string; petunjuk: string | null;
  bobot: number; grup_kode: string | null; grup_nama: string | null;
};

const props = defineProps<{
  judul: string;
  subjudul: string;
  pjp: { id: number; nama_perusahaan: string };
  kategori: Array<{ id: number; kode: string; nama: string; bobot: number; berbobot: boolean; items: Item[] }>;
  jawaban: Record<string, { jawaban: string | null; nilai: string | null; penjelasan: string | null }>;
  skor: { total_bobot: number; total_skor: number; persentase: number; kategori_risiko: string };
  rincianKategori: Array<{ kode: string; nama: string; bobot: number; bobot_dinilai: number; skor: number; persentase: number }>;
  legalitasStatus: { total: number; lengkap: number };
  jawabanOpsi: Record<string, string>;
  nilaiOpsi: Record<string, string>;
  kodeLegalitas: string;
  totalBobot: number;
  tautan: Record<string, string>;
}>();

/*
 * Seluruh 126 pertanyaan dikirim sekaligus — 126 × 4 isian = 504 nilai.
 * Masih di bawah `max_input_vars` bawaan PHP (1000), tetapi jaraknya
 * tidak lebar: menambah pertanyaan sampai melewati 250 akan membuat
 * sebagian kiriman DIPOTONG DIAM-DIAM oleh PHP, tanpa galat, dan yang
 * terlihat hanyalah jawaban terakhir yang tidak pernah tersimpan.
 */
const awal: Record<number, { item_id: number; jawaban: string; nilai: string; penjelasan: string }> = {};

props.kategori.forEach((k) => k.items.forEach((item) => {
  const ada = props.jawaban[String(item.id)];

  awal[item.id] = {
    item_id: item.id,
    jawaban: ada?.jawaban ?? '',
    nilai: ada?.nilai ?? '',
    penjelasan: ada?.penjelasan ?? '',
  };
}));

const form = useForm({ jawaban: awal });

const angka = (n: number) => Number.isInteger(n) ? String(n) : n.toFixed(1);

function warnaPita(n: number): string {
  if (n >= 80) return '#0ca30c';
  if (n >= 60) return '#fab219';
  if (n >= 40) return '#ec835a';
  return '#d03b3b';
}

function warnaTeks(n: number): string {
  if (n >= 80) return 'text-emerald-600';
  if (n >= 60) return 'text-amber-600';
  if (n >= 40) return 'text-orange-600';
  return 'text-red-600';
}

/** Kategori paling lemah — tujuan lompatan dari bagian "belum lengkap". */
const terlemah = computed(() => {
  if (!props.rincianKategori.length) return null;

  return [...props.rincianKategori].sort((a, b) => a.persentase - b.persentase)[0];
});

/** Akordeon dikendalikan di sini supaya donat dapat membukanya. */
const terbuka = reactive<Record<string, boolean>>(
  Object.fromEntries(props.kategori.map((k) => [k.kode, k.kode === props.kodeLegalitas])),
);

function bukaKategori(kode: string) {
  terbuka[kode] = true;

  // Menunggu satu putaran supaya elemennya sudah tergambar sebelum digulir.
  requestAnimationFrame(() => {
    document.getElementById(`kategori-${kode}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

function keRincian() {
  document.getElementById('rincian-kategori')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function keTerlemah() {
  if (terlemah.value) bukaKategori(terlemah.value.kode);
}

/** Kepala grup hanya digambar pada item pertama tiap grup. */
function awalGrup(items: Item[], i: number): boolean {
  const item = items[i];
  return Boolean(item.grup_kode) && items[i - 1]?.grup_kode !== item.grup_kode;
}

const DONAT_R = 28;
const DONAT_TEBAL = 9;
const DONAT_KELILING = 2 * Math.PI * DONAT_R;

const panjangDonat = (persen: number) => Math.max((persen / 100) * DONAT_KELILING, 2);

function simpan() {
  form.post(props.tautan.simpan, { preserveScroll: true });
}
</script>

<template>
  <Head :title="`${props.judul} — ${props.pjp.nama_perusahaan}`" />

  <div class="max-w-[1100px] mx-auto space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-cam-orange">Prakualifikasi SMKP</p>
        <h2 class="text-2xl font-extrabold tracking-tight text-stone-800">{{ props.judul }}</h2>
        <p class="text-[12px] text-stone-500 mt-1">{{ props.subjudul }}</p>
      </div>
      <div class="flex w-full flex-wrap gap-2 sm:w-auto">
        <Link :href="props.tautan.detail" class="eq-btn-lain px-4 !flex-none">← Detail PJP</Link>
        <Link :href="props.tautan.persyaratan" class="eq-btn-lain px-4 !flex-none">Semua PJP</Link>
      </div>
    </div>

    <section class="grid gap-3 sm:grid-cols-3">
      <article class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Total Skor</p>
        <p class="mt-2 text-xl font-extrabold text-stone-800">
          {{ angka(props.skor.total_skor) }} <small class="text-[11px] font-semibold text-stone-400">/ {{ props.skor.total_bobot }}</small>
        </p>
      </article>
      <article class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Persentase Kepatuhan</p>
        <p class="mt-2 text-xl font-extrabold" :class="warnaTeks(props.skor.persentase)">
          {{ angka(props.skor.persentase) }}%
        </p>
      </article>
      <article class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">Layak untuk Pekerjaan Risiko</p>
        <p class="mt-2 text-xl font-extrabold" :class="warnaTeks(props.skor.persentase)">{{ props.skor.kategori_risiko }}</p>
        <p class="text-[10px] text-stone-400 mt-0.5">Tingkat risiko pekerjaan yang layak dipercayakan</p>
      </article>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="font-bold text-[14px] text-stone-800 mb-3">Kelengkapan Checklist</h3>
      <PjpDonat :persentase="props.skor.persentase" @tercapai="keRincian" @kurang="keTerlemah" />
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4 flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-[12px] font-bold text-stone-800">Dokumen Legalitas (syarat wajib)</p>
        <p class="text-[11px] text-stone-400">
          Terpisah dari skor {{ props.totalBobot }} poin di atas — harus lengkap seluruhnya.
        </p>
      </div>
      <span class="rounded-full px-3 py-1 text-[12px] font-bold"
            :class="props.legalitasStatus.lengkap === props.legalitasStatus.total
              ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
        {{ props.legalitasStatus.lengkap }} / {{ props.legalitasStatus.total }} lengkap
      </span>
    </section>

    <!--
      Banyak donat kecil, bukan satu donat terbagi: tiap kategori adalah
      fakta yang berdiri sendiri dan dapat diklik sendiri, bukan potongan
      dari satu keseluruhan. Klik mana pun membuka kategori itu di
      formulir di bawah.
    -->
    <section id="rincian-kategori" class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="font-bold text-[14px] text-stone-800">Rincian Skor per Kategori</h3>
      <p class="text-[11px] text-stone-400 mt-0.5 mb-3">
        Klik salah satu donat untuk langsung membuka kategori itu pada formulir di bawah.
      </p>

      <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
        <button
          v-for="k in props.rincianKategori"
          :key="k.kode"
          type="button"
          class="flex flex-col items-center gap-1.5 rounded-lg p-2 text-center transition-colors hover:bg-stone-50"
          :title="`${k.kode}. ${k.nama}: ${angka(k.persentase)}%`"
          @click="bukaKategori(k.kode)"
        >
          <svg viewBox="0 0 72 72" class="h-16 w-16">
            <circle cx="36" cy="36" :r="DONAT_R" fill="none" stroke="#e7e5e4" :stroke-width="DONAT_TEBAL" />
            <circle cx="36" cy="36" :r="DONAT_R" fill="none" :stroke="warnaPita(k.persentase)"
                    :stroke-width="DONAT_TEBAL" stroke-linecap="round"
                    :stroke-dasharray="`${panjangDonat(k.persentase)} ${DONAT_KELILING}`"
                    transform="rotate(-90 36 36)" />
            <text x="36" y="40" text-anchor="middle" class="fill-stone-800 font-bold" style="font-size:13px">
              {{ angka(k.persentase) }}%
            </text>
          </svg>
          <span class="text-[11px] font-bold text-stone-500">{{ k.kode }}</span>
        </button>
      </div>
    </section>

    <p class="rounded-2xl bg-stone-50 border border-stone-200 px-4 py-3 text-[12px] text-stone-600">
      Kolom <b>Nilai</b> yang menentukan skor: 0 tidak ada, 1 belum terpenuhi, 2 cukup memadai tetapi perlu
      perbaikan, 3 sudah memadai, dan N/A untuk persyaratan yang tidak berlaku. Pertanyaan bernilai N/A
      dikeluarkan dari perhitungan maupun dari pembaginya; pertanyaan yang dibiarkan kosong tetap ikut
      sebagai pembagi dengan skor 0. Kategori <b>Dokumen Legalitas</b> adalah syarat wajib terpisah dan
      tidak ikut dihitung pada skor di atas.
    </p>

    <form class="space-y-4" @submit.prevent="simpan">
      <details
        v-for="k in props.kategori"
        :id="`kategori-${k.kode}`"
        :key="k.id"
        :open="terbuka[k.kode]"
        class="rounded-2xl bg-white border border-stone-100 shadow-card group"
        @toggle="terbuka[k.kode] = ($event.target as HTMLDetailsElement).open"
      >
        <summary class="flex cursor-pointer items-center justify-between gap-3 p-4 text-[13px] font-bold text-stone-800">
          <span>
            <template v-if="k.berbobot">{{ k.kode }}. </template>{{ k.nama }}
          </span>
          <span class="flex shrink-0 items-center gap-2">
            <span v-if="k.berbobot" class="rounded-full bg-stone-100 px-2 py-0.5 text-[10px] font-bold text-stone-500">
              Bobot {{ k.bobot }}
            </span>
            <span v-else class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">
              Syarat wajib
            </span>
          </span>
        </summary>

        <div class="space-y-4 border-t border-stone-100 p-4">
          <div v-for="(item, i) in k.items" :key="item.id">
            <p v-if="awalGrup(k.items, i)" class="mb-2 text-[10px] font-bold uppercase tracking-wide text-stone-400">
              {{ item.grup_kode }} · {{ item.grup_nama }}
            </p>

            <div class="rounded-lg border border-stone-100 p-3">
              <p class="whitespace-pre-line text-[12px] font-semibold text-stone-700">
                {{ item.nomor }}. {{ item.pertanyaan }}
              </p>
              <p v-if="item.petunjuk" class="mt-1 whitespace-pre-line text-[11px] italic text-stone-400">
                {{ item.petunjuk }}
              </p>

              <div class="mt-3 grid gap-2 sm:grid-cols-[110px_1fr_2fr]">
                <select v-model="form.jawaban[item.id].jawaban" class="rounded-lg border-stone-200 text-[12px]">
                  <option value="">Jawaban</option>
                  <option v-for="(label, nilai) in props.jawabanOpsi" :key="nilai" :value="nilai">{{ label }}</option>
                </select>

                <!--
                  Kategori legalitas tidak punya kolom Nilai: dokumennya
                  ada atau tidak ada, tidak ada tingkatan "cukup memadai".
                -->
                <select v-if="k.berbobot" v-model="form.jawaban[item.id].nilai"
                        class="rounded-lg border-stone-200 text-[12px]">
                  <option value="">Nilai</option>
                  <option v-for="(label, nilai) in props.nilaiOpsi" :key="nilai" :value="nilai">{{ label }}</option>
                </select>
                <span v-else class="self-center text-[11px] text-stone-400">Tidak dinilai 0–3</span>

                <input v-model="form.jawaban[item.id].penjelasan" type="text" maxlength="2000"
                       placeholder="Penjelasan (opsional)" class="rounded-lg border-stone-200 text-[12px]">
              </div>
            </div>
          </div>
        </div>
      </details>

      <div class="sticky bottom-4 flex justify-end">
        <button :disabled="form.processing" class="eq-btn-utama px-8 !flex-none shadow-lg">
          {{ form.processing ? 'Menyimpan…' : 'Simpan checklist' }}
        </button>
      </div>
    </form>
  </div>
</template>
