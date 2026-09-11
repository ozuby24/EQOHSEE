<script setup lang="ts">
/**
 * Berkas kelayakan kerja satu orang.
 *
 * DISUSUN MENURUT RANTAINYA — MCU, induksi, Mine Permit, SIMPER,
 * kompetensi — bukan menurut jenis dokumen yang kebetulan paling
 * banyak. Urutan itu yang menjelaskan mengapa kartu seseorang gugur:
 * yang menarik ke bawah hampir selalu dokumen yang lebih awal pada
 * rantainya, dan membacanya dari atas membuat sebabnya terlihat tanpa
 * perlu dijelaskan.
 */
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { propHalaman } from '../../halaman';
import Lencana from './Lencana.vue';
import Langkah from './Langkah.vue';

const props = propHalaman();

const p          = computed<any>(() => props.pekerja ?? {});
const keadaan    = computed<any>(() => props.keadaan ?? {});
const mcu        = computed<any[]>(() => (props.mcu ?? []) as any[]);
const induksi    = computed<any[]>(() => (props.induksi ?? []) as any[]);
const permit     = computed<any[]>(() => (props.permit ?? []) as any[]);
const simper     = computed<any[]>(() => (props.simper ?? []) as any[]);
const kompetensi = computed<any[]>(() => (props.kompetensi ?? []) as any[]);

const RINGKAS: [string, string][] = [
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

  <div class="max-w-[1200px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div class="min-w-0">
        <Link href="/miners" class="text-[11.5px] text-stone-400 hover:underline">← Daftar pekerja</Link>
        <h2 class="text-xl font-bold text-cam-ink mt-1">{{ p.nama }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">
          {{ p.jabatan || '—' }} · {{ p.departemen || '—' }}
          <span v-if="p.subkontraktor"> · {{ p.subkontraktor }}</span>
        </p>
      </div>

      <Lencana :keadaan="keadaan.terburuk" :label="props.KEADAAN?.[keadaan.terburuk]" :nada="props.NADA" />
    </section>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="[kode, nama] in RINGKAS" :key="kode"
           class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-stone-400">{{ nama }}</div>
        <div class="mt-2">
          <Lencana :keadaan="keadaan[kode]?.keadaan"
                   :label="props.KEADAAN?.[keadaan[kode]?.keadaan]"
                   :nada="props.NADA" :sisa="keadaan[kode]?.sisa" />
        </div>
        <div class="text-[11px] text-stone-500 mt-1.5 num">
          {{ keadaan[kode]?.sampai || 'belum ada tanggal' }}
        </div>
      </div>
    </section>

    <p v-if="keadaan.sebab" class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-[12px] text-amber-800">
      Yang menghentikan kartunya lebih dahulu: <strong>{{ SEBAB[keadaan.sebab] ?? keadaan.sebab }}</strong>.
      Itulah yang harus diperbarui — memperpanjang kartunya sendiri tidak mengubah apa pun.
    </p>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">Identitas</h3>
      </header>
      <dl class="grid gap-x-6 gap-y-3 px-5 py-4 text-[12px] sm:grid-cols-2 lg:grid-cols-3">
        <div><dt class="text-stone-400">NIK</dt><dd class="num text-cam-ink">{{ p.nik || '—' }}</dd></div>
        <div><dt class="text-stone-400">No. induk</dt><dd class="num text-cam-ink">{{ p.no_induk || '—' }}</dd></div>
        <div><dt class="text-stone-400">No. registrasi</dt><dd class="num text-cam-ink">{{ p.no_registrasi || '—' }}</dd></div>
        <div><dt class="text-stone-400">Tanggal lahir</dt><dd class="num text-cam-ink">{{ p.tanggal_lahir || '—' }} <span v-if="p.usia" class="text-stone-400">({{ p.usia }} th)</span></dd></div>
        <div><dt class="text-stone-400">Golongan darah</dt><dd class="text-cam-ink">{{ p.gol_darah || '—' }}</dd></div>
        <div><dt class="text-stone-400">Telepon</dt><dd class="num text-cam-ink">{{ p.telepon || '—' }}</dd></div>
        <div><dt class="text-stone-400">Telepon darurat</dt><dd class="num text-cam-ink">{{ p.telepon_darurat || '—' }}</dd></div>
        <div><dt class="text-stone-400">Lokasi kerja</dt><dd class="text-cam-ink">{{ p.lokasi || '—' }}</dd></div>
        <div><dt class="text-stone-400">Status</dt><dd class="text-cam-ink">{{ p.status }} · {{ p.status_kerja || '—' }}</dd></div>
      </dl>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">
          MCU <span class="font-normal text-stone-400">| {{ mcu.length }} pemeriksaan</span>
        </h3>
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold">Surat</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Diperiksa</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Berlaku sampai</th>
              <th class="px-4 py-2 font-semibold">Hasil</th>
              <th class="px-4 py-2 font-semibold">Napza</th>
              <th class="px-4 py-2 font-semibold">Keadaan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in mcu" :key="m.id" class="border-b border-stone-100">
              <td class="px-4 py-2.5 num text-stone-500">{{ m.surat || '—' }}</td>
              <td class="px-4 py-2.5 num">{{ m.tanggal || '—' }}</td>
              <td class="px-4 py-2.5 num">{{ m.sampai || '—' }}</td>
              <td class="px-4 py-2.5">
                {{ m.hasil || '—' }}
                <span v-if="m.hasil && !m.layak" class="text-red-600">· tidak layak</span>
              </td>
              <td class="px-4 py-2.5">{{ m.napza || '—' }}</td>
              <td class="px-4 py-2.5">
                <Lencana :keadaan="m.keadaan" :label="props.KEADAAN?.[m.keadaan]" :nada="props.NADA" :sisa="m.sisa" />
              </td>
            </tr>
            <tr v-if="!mcu.length"><td colspan="6" class="px-4 py-8 text-center text-stone-400">Belum ada MCU.</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">
          Induksi <span class="font-normal text-stone-400">| ambang lulus {{ props.NILAI_LULUS }}</span>
        </h3>
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold">Surat</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Tanggal</th>
              <th class="px-4 py-2 font-semibold text-right">Nilai</th>
              <th class="px-4 py-2 font-semibold text-right">Percobaan</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Berlaku sampai</th>
              <th class="px-4 py-2 font-semibold">Keadaan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="i in induksi" :key="i.id" class="border-b border-stone-100">
              <td class="px-4 py-2.5 num text-stone-500">{{ i.surat || '—' }}</td>
              <td class="px-4 py-2.5 num">{{ i.tanggal || '—' }}</td>
              <td class="px-4 py-2.5 num text-right" :class="i.lulus ? '' : 'text-red-600 font-semibold'">
                {{ i.nilai ?? '—' }}
              </td>
              <td class="px-4 py-2.5 num text-right">{{ i.percobaan }}</td>
              <td class="px-4 py-2.5 num">{{ i.sampai || '—' }}</td>
              <td class="px-4 py-2.5">
                <Lencana :keadaan="i.keadaan" :label="props.KEADAAN?.[i.keadaan]" :nada="props.NADA" />
              </td>
            </tr>
            <tr v-if="!induksi.length"><td colspan="6" class="px-4 py-8 text-center text-stone-400">Belum ada induksi.</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">Mine Permit</h3>
      </header>

      <ul class="divide-y divide-stone-100">
        <li v-for="k in permit" :key="k.id" class="px-5 py-4 space-y-2">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
              <span class="num font-bold text-cam-ink">{{ k.nomor || 'tanpa nomor' }}</span>
              <span class="text-[11.5px] text-stone-500"> · {{ k.tipe || '—' }} · {{ k.cakupan || '—' }}</span>
            </div>
            <Lencana :keadaan="k.keadaan" :label="props.KEADAAN?.[k.keadaan]" :nada="props.NADA" :sisa="k.sisa" />
          </div>

          <p class="text-[11.5px] text-stone-500">
            Terbit {{ k.tanggal || '—' }} · berlaku sampai <span class="num">{{ k.sampai || '—' }}</span>
            <span v-if="k.efektif && k.efektif !== k.sampai" class="text-amber-700">
              — efektif habis <span class="num">{{ k.efektif }}</span>, karena MCU-nya lebih dahulu
            </span>
          </p>

          <Langkah :alur="k.alur" />
        </li>

        <li v-if="!permit.length" class="px-5 py-8 text-center text-[12px] text-stone-400">
          Belum ada Mine Permit.
        </li>
      </ul>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">SIMPER</h3>
      </header>

      <ul class="divide-y divide-stone-100">
        <li v-for="s in simper" :key="s.id" class="px-5 py-4 space-y-2">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
              <span class="num font-bold text-cam-ink">{{ s.nomor || 'tanpa nomor' }}</span>
              <span class="text-[11.5px] text-stone-500">
                · kelas {{ s.kelas }} · SIM {{ s.simpol || '—' }}
              </span>
            </div>
            <Lencana :keadaan="s.keadaan" :label="props.KEADAAN?.[s.keadaan]" :nada="props.NADA" :sisa="s.sisa" />
          </div>

          <p class="text-[11.5px] text-stone-500">
            Berlaku sampai <span class="num">{{ s.efektif || '—' }}</span>
            <span v-if="s.sebab"> — yang menghentikannya: <strong>{{ SEBAB[s.sebab] ?? s.sebab }}</strong></span>
          </p>

          <ul v-if="s.unit?.length" class="flex flex-wrap gap-2">
            <li v-for="u in s.unit" :key="u.id"
                class="rounded-lg bg-stone-50 border border-stone-200 px-2.5 py-1 text-[11px]">
              <span class="font-semibold text-cam-ink">{{ u.unit || u.golongan || '—' }}</span>
              <span class="text-stone-500"> · {{ u.kewenangan || '—' }}</span>
              <span v-if="!u.lulus" class="text-red-600"> · nilai kurang</span>
            </li>
          </ul>

          <Langkah :alur="s.alur" />
        </li>

        <li v-if="!simper.length" class="px-5 py-8 text-center text-[12px] text-stone-400">
          Belum ada SIMPER. Pemegang Mine Permit tanpa SIMPER berkartu putih — tidak mengendarai unit.
        </li>
      </ul>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">Sertifikat kompetensi</h3>
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-4 py-2 font-semibold">Sertifikat</th>
              <th class="px-4 py-2 font-semibold">Lembaga</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Nomor</th>
              <th class="px-4 py-2 font-semibold whitespace-nowrap">Berlaku sampai</th>
              <th class="px-4 py-2 font-semibold">Keadaan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="k in kompetensi" :key="k.id" class="border-b border-stone-100">
              <td class="px-4 py-2.5 font-semibold text-cam-ink">{{ k.nama }}</td>
              <td class="px-4 py-2.5 text-stone-600">{{ k.lembaga || '—' }}</td>
              <td class="px-4 py-2.5 num text-stone-500">{{ k.nomor || '—' }}</td>
              <td class="px-4 py-2.5 num">{{ k.sampai || 'tanpa batas' }}</td>
              <td class="px-4 py-2.5">
                <Lencana :keadaan="k.keadaan" :label="props.KEADAAN?.[k.keadaan]" :nada="props.NADA" :sisa="k.sisa" />
              </td>
            </tr>
            <tr v-if="!kompetensi.length">
              <td colspan="5" class="px-4 py-8 text-center text-stone-400">Belum ada sertifikat kompetensi.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
