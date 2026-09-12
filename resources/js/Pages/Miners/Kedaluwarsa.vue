<script setup lang="ts">
/**
 * Pemantauan masa berlaku.
 *
 * Menjawab "siapa yang hari ini tidak boleh masuk, dan apa yang harus
 * diurus" — bukan "siapa saja pekerja kita". Karena itu yang ditampilkan
 * bukan seluruh orang melainkan yang berkasnya bermasalah, dan sebab
 * tiap kartu gugur ditulis sebagai kalimat, bukan diwakili warna saja.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { propHalaman } from '../../halaman';
import Lencana from './Lencana.vue';

const props = propHalaman();

const baris = computed<any[]>(() => (props.baris ?? []) as any[]);

const cari       = ref(String(props.saring?.cari ?? ''));
const jenis      = ref(String(props.saring?.jenis ?? ''));
const keadaan    = ref(String(props.saring?.keadaan ?? ''));
const status     = ref(String(props.saring?.status ?? ''));
const perusahaan = ref(String(props.saring?.perusahaan ?? ''));

let tunda: ReturnType<typeof setTimeout> | undefined;

watch([cari, jenis, keadaan, status, perusahaan], () => {
  clearTimeout(tunda);

  tunda = setTimeout(() => {
    router.get('/miners/kedaluwarsa', {
      cari: cari.value || undefined,
      jenis: jenis.value || undefined,
      keadaan: keadaan.value || undefined,
      status: status.value || undefined,
      perusahaan: perusahaan.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
  }, 300);
});

const JENIS: [string, string][] = [
  ['mcu', 'MCU'],
  ['induksi', 'Induksi'],
  ['permit', 'Mine Permit'],
  ['simper', 'SIMPER'],
];

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
      </div>

      <Link href="/miners" class="eq-btn-lain">Daftar pekerja</Link>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="[kode, nama] in JENIS" :key="kode"
           class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-stone-400">{{ nama }}</div>

        <dl class="mt-2 space-y-1 text-[11.5px]">
          <div v-for="(label, k) in (props.KEADAAN ?? {})" :key="k" class="flex justify-between gap-2">
            <dt class="text-stone-500">{{ label }}</dt>
            <dd class="num font-semibold"
                :class="Number(props.ringkas?.[kode]?.[k] ?? 0) > 0 && k !== 'berlaku'
                  ? 'text-cam-ink' : 'text-stone-300'">
              {{ props.ringkas?.[kode]?.[k] ?? 0 }}
            </dd>
          </div>
        </dl>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center gap-3">
        <h3 class="text-[13.5px] font-bold text-cam-ink flex-1 min-w-0">
          Perlu diurus <span class="font-normal text-stone-400">| {{ baris.length }} orang</span>
        </h3>

        <select v-model="jenis" class="rounded-lg border-stone-200 text-[12px]" aria-label="Saring jenis berkas">
          <option value="">Semua berkas</option>
          <option v-for="(label, kode) in (props.JENIS ?? {})" :key="kode" :value="kode">{{ label }}</option>
        </select>

        <select v-model="keadaan" class="rounded-lg border-stone-200 text-[12px]" aria-label="Saring keadaan">
          <option value="">Semua keadaan</option>
          <option v-for="(label, kode) in (props.KEADAAN ?? {})" :key="kode" :value="kode">{{ label }}</option>
        </select>

        <select v-model="status" class="rounded-lg border-stone-200 text-[12px]" aria-label="Saring status kerja">
          <option value="">Semua status</option>
          <option v-for="(label, kode) in (props.STATUS ?? {})" :key="kode" :value="kode">{{ label }}</option>
        </select>

        <input v-model="cari" placeholder="Cari nama, NIK…"
               class="rounded-lg border-stone-200 text-[12px] w-48" aria-label="Cari pekerja">
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold w-10">No</th>
              <th class="px-4 py-2 font-semibold">Nama</th>
              <th v-for="[, label] in JENIS" :key="label"
                  class="px-4 py-2 font-semibold whitespace-nowrap">{{ label }}</th>
              <th class="px-4 py-2 font-semibold">Yang menghentikannya</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(b, i) in baris" :key="b.id" class="border-b border-stone-100 hover:bg-stone-50/60">
              <td class="px-4 py-2.5 num text-stone-400">{{ i + 1 }}</td>

              <td class="px-4 py-2.5">
                <Link :href="`/miners/${b.id}`" class="font-bold text-cam-lime-deep hover:underline">
                  {{ b.nama }}
                </Link>
                <div class="text-[10.5px] text-stone-400">{{ b.perusahaan || '—' }}</div>
              </td>

              <td v-for="[kode] in JENIS" :key="kode" class="px-4 py-2.5">
                <Lencana :keadaan="b[kode].keadaan" :label="props.KEADAAN?.[b[kode].keadaan]"
                         :nada="props.NADA" :sisa="b[kode].sisa" />
              </td>

              <td class="px-4 py-2.5 text-[10.5px] text-stone-500">
                {{ b.sebab ? SEBAB[b.sebab] ?? b.sebab : '—' }}
              </td>
            </tr>

            <tr v-if="!baris.length">
              <td colspan="7" class="px-4 py-10 text-center text-stone-400">
                Tidak ada berkas yang perlu diurus dengan penyaring ini.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
