<script setup lang="ts">
/**
 * Daftar pekerja beserta keadaan keempat berkasnya.
 *
 * KEEMPATNYA BERDAMPINGAN, bukan diringkas menjadi satu kolom. Yang
 * menentukan boleh tidaknya seseorang masuk memang keadaan terburuknya
 * — satu angka — tetapi satu angka tidak memberi tahu apa yang harus
 * diurus. Orang yang tertahan karena MCU dan orang yang tertahan karena
 * SIMPOL sama-sama merah, dan yang harus dikerjakan berbeda sama sekali.
 *
 * Penyaringan dikerjakan server. Daftar pekerja dapat tumbuh melewati
 * satu layar, dan penyaring yang hanya bekerja atas baris yang sudah
 * terkirim akan diam-diam menyembunyikan sisanya.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { propHalaman } from '../../halaman';
import Lencana from './Lencana.vue';

const props = propHalaman();

const baris = computed<any[]>(() => (props.baris ?? []) as any[]);

const cari       = ref(String(props.saring?.cari ?? ''));
const status     = ref(String(props.saring?.status ?? ''));
const keadaan    = ref(String(props.saring?.keadaan ?? ''));
const departemen = ref(String(props.saring?.departemen ?? ''));

let tunda: ReturnType<typeof setTimeout> | undefined;

/* preserveState menahan kotak isian tetap terisi di tengah pengetikan;
   tanpanya tiap ketukan huruf memasang ulang komponennya. */
watch([cari, status, keadaan, departemen], () => {
  clearTimeout(tunda);

  tunda = setTimeout(() => {
    router.get('/miners', {
      cari: cari.value || undefined,
      status: status.value || undefined,
      keadaan: keadaan.value || undefined,
      departemen: departemen.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
  }, 300);
});

const JENIS = [
  ['mcu', 'MCU'],
  ['induksi', 'Induksi'],
  ['permit', 'Mine Permit'],
  ['simper', 'SIMPER'],
] as const;

/** Sebab kartu gugur, dijelaskan — bukan hanya diwarnai. */
const SEBAB: Record<string, string> = {
  mcu:    'MCU habis lebih dahulu',
  simpol: 'SIMPOL habis lebih dahulu',
  permit: 'Mine Permit habis lebih dahulu',
  simper: 'masa berlaku kartunya sendiri',
};
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">{{ props.subjudul }}</p>
      </div>

      <Link href="/miners/kedaluwarsa" class="eq-btn-lain">Pemantauan masa berlaku</Link>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center gap-3">
        <h3 class="text-[13.5px] font-bold text-cam-ink flex-1 min-w-0">
          Pekerja <span class="font-normal text-stone-400">| {{ baris.length }} orang</span>
        </h3>

        <select v-model="keadaan" class="rounded-lg border-stone-200 text-[12px]" aria-label="Saring keadaan">
          <option value="">Semua keadaan</option>
          <option v-for="(label, kode) in (props.KEADAAN ?? {})" :key="kode" :value="kode">{{ label }}</option>
        </select>

        <select v-model="status" class="rounded-lg border-stone-200 text-[12px]" aria-label="Saring status kerja">
          <option value="">Semua status</option>
          <option v-for="(label, kode) in (props.STATUS ?? {})" :key="kode" :value="kode">{{ label }}</option>
        </select>

        <select v-model="departemen" class="rounded-lg border-stone-200 text-[12px]" aria-label="Saring departemen">
          <option value="">Semua departemen</option>
          <option v-for="(nama, id) in (props.departemen ?? {})" :key="id" :value="id">{{ nama }}</option>
        </select>

        <input v-model="cari" placeholder="Cari nama, NIK, no induk…"
               class="rounded-lg border-stone-200 text-[12px] w-56" aria-label="Cari pekerja">
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold w-10">No</th>
              <th class="px-4 py-2 font-semibold">Nama</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Jabatan</th>
              <th v-for="[, label] in JENIS" :key="label"
                  class="px-4 py-2 font-semibold whitespace-nowrap">{{ label }}</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Catatan</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(b, i) in baris" :key="b.id" class="border-b border-stone-100 hover:bg-stone-50/60">
              <td class="px-4 py-2.5 num text-stone-400">{{ i + 1 }}</td>

              <td class="px-4 py-2.5">
                <Link :href="`/miners/${b.id}`" class="font-bold text-cam-lime-deep hover:underline">
                  {{ b.nama }}
                </Link>
                <div class="text-[10.5px] text-stone-400">{{ b.nik || '—' }} · {{ b.departemen || '—' }}</div>
              </td>

              <td class="px-4 py-2.5 text-stone-600">{{ b.jabatan || '—' }}</td>

              <td v-for="[kode] in JENIS" :key="kode" class="px-4 py-2.5">
                <Lencana :keadaan="b[kode].keadaan"
                         :label="props.KEADAAN?.[b[kode].keadaan]"
                         :nada="props.NADA"
                         :sisa="b[kode].sisa" />
              </td>

              <td class="px-4 py-2.5 text-[10.5px] text-stone-500">
                {{ b.sebab ? SEBAB[b.sebab] ?? b.sebab : '—' }}
              </td>
            </tr>

            <tr v-if="!baris.length">
              <td colspan="8" class="px-4 py-10 text-center text-stone-400">
                Tidak ada pekerja yang cocok dengan penyaring ini.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
