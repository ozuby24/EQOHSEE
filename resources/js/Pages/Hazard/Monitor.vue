<script setup lang="ts">
/**
 * Hazard Report — monitor laporan.
 *
 * Saringan dikirim ke server, bukan disaring di peramban: yang dimuat
 * hanya satu halaman dari lima belas laporan, jadi menyaring di sini
 * hanya menyaring sisa yang kebetulan ikut terbawa.
 *
 * Kotak pencarian ditunda sebentar sebelum dikirim dan hanya memuat
 * ulang prop daftarnya, sehingga fokusnya tidak hilang di tengah orang
 * mengetik. Saringan pilihan dikirim seketika — orang sudah selesai
 * memutuskan begitu ia melepas pilihan.
 */
import { onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { HalamanMonitorBahaya } from '../../types';

const props = defineProps<HalamanMonitorBahaya>();

const isi = reactive({ ...props.saring });
const memuat = ref(false);

const HANYA = ['laporan', 'halaman', 'stat', 'saring', 'adaSaringan', 'tautan'];

function kirim(ganti = true) {
  memuat.value = true;

  /* Saringan kosong tidak ikut dikirim. Membawanya serta menghasilkan
     alamat seperti ?q=&bulan=&status= — yang lalu ikut tersalin saat
     orang membagikan tautan hasil saringannya. */
  const data: Record<string, string> = {};
  for (const [k, v] of Object.entries(isi)) {
    if (v !== null && v !== '') data[k] = String(v);
  }

  router.get('/hazard', data, {
    only: HANYA,
    preserveState: true,
    preserveScroll: true,
    replace: ganti,
    onFinish: () => { memuat.value = false; },
  });
}

let jeda: ReturnType<typeof setTimeout> | null = null;

watch(() => isi.q, () => {
  if (jeda) clearTimeout(jeda);
  jeda = setTimeout(kirim, 350);
});

onBeforeUnmount(() => { if (jeda) clearTimeout(jeda); });

function reset() {
  Object.assign(isi, {
    q: '', bulan: null, kategori: null, risiko: null, status: null, perusahaan: null,
  });
  kirim();
}

const kartu = [
  { kunci: 'total'  as const, label: 'Total laporan',              kelas: 'text-cam-ink' },
  { kunci: 'open'   as const, label: 'Open',                        kelas: 'text-red-500' },
  { kunci: 'proses' as const, label: 'In Progress',                 kelas: 'text-amber-500' },
  { kunci: 'closed' as const, label: 'Closed',                      kelas: 'text-emerald-600' },
  { kunci: 'tinggi' as const, label: 'Risiko tinggi belum tutup',   kelas: 'text-red-600' },
];

const pilihan =
  'ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600';
</script>

<template>
  <Head title="Monitor Hazard Report" />

  <div class="max-w-6xl mx-auto space-y-5">

    <div class="grid gap-3 grid-cols-2 lg:grid-cols-5">
      <div v-for="k in kartu" :key="k.kunci"
           class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
        <div class="stat stat-sm" :class="k.kelas">{{ stat[k.kunci] }}</div>
        <div class="text-[11px] text-stone-400 mt-1.5 leading-tight">{{ k.label }}</div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-3">
      <div class="flex flex-wrap items-center gap-2">
        <input v-model="isi.q" placeholder="Cari kode, lokasi, deskripsi, pelapor…"
               class="ring-focus flex-1 min-w-0 basis-[180px] rounded-xl border border-stone-200
                      px-4 py-2.5 text-[13px] transition">

        <select v-model="isi.bulan" :class="pilihan" @change="kirim()" aria-label="Bulan">
          <option :value="null">Semua bulan</option>
          <option v-for="b in opsi.bulan" :key="b.nilai" :value="b.nilai">{{ b.label }}</option>
        </select>

        <select v-model="isi.risiko" :class="pilihan" @change="kirim()" aria-label="Tingkat risiko">
          <option :value="null">Semua risiko</option>
          <option v-for="r in opsi.risiko" :key="r" :value="r">{{ r }}</option>
        </select>

        <select v-model="isi.status" :class="pilihan" @change="kirim()" aria-label="Status">
          <option :value="null">Semua status</option>
          <option v-for="s in opsi.status" :key="s" :value="s">{{ s }}</option>
        </select>

        <select v-model="isi.kategori" :class="pilihan" @change="kirim()" aria-label="Kategori">
          <option :value="null">Semua kategori</option>
          <option v-for="k in opsi.kategori" :key="k" :value="k">{{ k }}</option>
        </select>

        <select v-model="isi.perusahaan" :class="pilihan" @change="kirim()" aria-label="Perusahaan">
          <option :value="null">Semua perusahaan</option>
          <option v-for="c in opsi.perusahaan" :key="c.id" :value="String(c.id)">{{ c.nama }}</option>
        </select>

        <span class="text-[11.5px] text-stone-400 num px-1">
          {{ memuat ? 'memuat…' : `${halaman.total} laporan` }}
        </span>

        <button type="button" @click="reset"
                class="px-3 py-2.5 text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">
          Reset
        </button>

        <a :href="tautan.buat"
           class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px]
                  font-bold hover:brightness-105 transition">+ Buat Laporan</a>
      </div>

      <div class="flex flex-wrap items-center gap-2 mt-2.5 pt-2.5 border-t border-stone-100">
        <span class="text-[11px] font-bold uppercase tracking-wide text-stone-400 px-1">
          Ekspor hasil saringan
        </span>
        <a :href="tautan.csv"
           class="rounded-lg border border-stone-200 px-3 py-1.5 text-[11.5px] font-bold text-stone-600
                  hover:border-cam-lime hover:bg-cam-lime-soft transition">⤓ Excel (CSV)</a>
        <a :href="tautan.cetak" target="_blank" rel="noopener"
           class="rounded-lg border border-stone-200 px-3 py-1.5 text-[11.5px] font-bold text-stone-600
                  hover:border-cam-lime hover:bg-cam-lime-soft transition">⎙ PDF</a>
        <a :href="tautan.wa" target="_blank" rel="noopener"
           class="rounded-lg bg-[#25D366] text-white px-3 py-1.5 text-[11.5px] font-bold
                  hover:brightness-105 transition">Bagikan ke Grup WA</a>
        <a :href="tautan.pengingat"
           class="ml-auto rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-[11.5px]
                  font-bold text-amber-700 hover:bg-amber-100 transition">Pengingat PIC →</a>
      </div>
    </div>

    <div class="space-y-2.5 transition-opacity" :class="memuat ? 'opacity-50' : ''">
      <Link v-for="r in laporan" :key="r.id" :href="r.url"
            class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5
                   hover:border-cam-lime/40 transition">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-[10px] font-bold bg-cam-ink text-white px-2 py-0.5 rounded num">{{ r.kode }}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white"
                    :style="{ background: r.warnaRisiko }">{{ r.risiko }}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white"
                    :style="{ background: r.warnaStatus }">{{ r.status }}</span>
              <span v-if="r.kategori"
                    class="text-[10px] font-semibold bg-stone-100 text-stone-500 px-2 py-0.5 rounded-full">
                {{ r.kategori }}
              </span>
            </div>

            <p class="text-[13.5px] font-semibold text-cam-ink mt-2 clamp-2 leading-relaxed">{{ r.deskripsi }}</p>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11.5px] text-stone-400 mt-2">
              <span>📍 {{ r.lokasi ?? '—' }}</span>
              <span>👤 {{ r.pelapor }}</span>
              <span>{{ r.tanggal }}</span>
            </div>

            <div class="mt-2 inline-flex items-center gap-1.5 bg-stone-50 rounded-lg px-2.5 py-1.5">
              <svg class="w-3.5 h-3.5 text-stone-400 shrink-0" fill="none" stroke="currentColor"
                   stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
              </svg>
              <span class="text-[11.5px] text-stone-400">Ditujukan kepada</span>
              <span class="text-[12px] font-bold text-cam-ink">{{ r.tujuan ?? '— belum diisi —' }}</span>
              <span v-if="r.terlapor" class="text-[11px] text-stone-400">· {{ r.terlapor }}</span>
            </div>
          </div>

          <img v-if="r.foto" :src="r.foto" alt="" class="w-20 h-20 object-cover rounded-xl shrink-0">
        </div>
      </Link>

      <div v-if="!laporan.length" class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
        <template v-if="adaSaringan">
          <p class="text-[13px] text-stone-400">Tidak ada laporan yang cocok dengan saringan.</p>
          <button type="button" @click="reset"
                  class="inline-block mt-3 text-[12.5px] font-bold text-cam-lime-deep hover:underline">
            Hapus saringan →
          </button>
        </template>
        <template v-else>
          <p class="text-[13px] text-stone-400">Belum ada laporan bahaya.</p>
          <a :href="tautan.buat" class="inline-block mt-3 text-[12.5px] font-bold text-cam-lime-deep hover:underline">
            Buat laporan pertama →
          </a>
        </template>
      </div>
    </div>

    <div v-if="halaman.akhir > 1" class="flex flex-wrap gap-1.5">
      <component v-for="(t, i) in halaman.tautan" :key="i"
                 :is="t.url ? Link : 'span'" :href="t.url ?? undefined"
                 :only="HANYA" preserve-state preserve-scroll
                 class="min-w-[36px] text-center rounded-lg border px-3 py-1.5 text-[12px] font-semibold transition"
                 :class="t.aktif
                   ? 'bg-[color:var(--eq-aksen,#F57C00)] text-white border-transparent'
                   : t.url ? 'bg-white text-stone-600 border-stone-200 hover:border-stone-400'
                           : 'bg-white text-stone-300 border-stone-100 cursor-default'"
                 v-html="t.label" />
    </div>
  </div>
</template>
