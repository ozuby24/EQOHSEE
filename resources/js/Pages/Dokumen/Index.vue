<script setup lang="ts">
/**
 * Register dokumen terkendali.
 *
 * Masa tinjau ditandai pada barisnya sendiri, bukan hanya dihitung di
 * kartu ringkasan: yang perlu dilakukan pengendali dokumen bukan
 * mengetahui berapa banyak yang lewat tempo, melainkan yang mana.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { HalamanRegisterDokumen } from '../../types';

const props = defineProps<HalamanRegisterDokumen>();

const saring = useForm({ ...props.f });

function terapkan() {
  saring
    .transform((d) => Object.fromEntries(Object.entries(d).filter(([, v]) => v !== '' && v !== null)))
    .get(props.tautan.daftar, { preserveState: true, preserveScroll: true, replace: true });
}

const kpi = [
  { label: 'Total dokumen',  nilai: props.stat.total,   warna: '#0F1720' },
  { label: 'Berlaku',        nilai: props.stat.berlaku, warna: '#22C55E' },
  { label: 'Draft',          nilai: props.stat.draft,   warna: '#9AA3AE' },
  { label: 'Perlu ditinjau', nilai: props.stat.lewat,   warna: '#F57C00' },
];

const isian = 'ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600 transition';
const chip = 'text-[9.5px] font-bold uppercase tracking-wide px-2 py-0.5 rounded';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <div class="grid gap-3 grid-cols-2 lg:grid-cols-4">
      <div v-for="k in kpi" :key="k.label"
           class="bg-white rounded-2xl shadow-card border border-stone-100 p-4 relative overflow-hidden">
        <span class="absolute -right-6 -top-6 w-20 h-20 rounded-full"
              :style="{ background: k.warna + '0F' }"></span>
        <div class="relative flex items-start justify-between gap-2">
          <div class="min-w-0">
            <div class="stat stat-sm leading-none" :style="{ color: k.warna }">{{ k.nilai }}</div>
            <div class="text-[11px] text-stone-400 mt-1.5 leading-snug">{{ k.label }}</div>
          </div>
          <span class="shrink-0 w-8 h-8 rounded-lg grid place-items-center"
                :style="{ background: k.warna + '1A' }">
            <svg class="w-[17px] h-[17px]" fill="none" :stroke="k.warna" stroke-width="1.9" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M7 3h7l4 4v14H7a1 1 0 01-1-1V4a1 1 0 011-1zM14 3v4h4M9.5 12h5M9.5 15.5h3" /></svg>
          </span>
        </div>
      </div>
    </div>

    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-3" @submit.prevent="terapkan">
      <div class="flex flex-wrap items-center gap-2">
        <input v-model="saring.q" placeholder="Cari kode, judul, atau ringkasan…"
               class="ring-focus flex-1 min-w-0 basis-[180px] rounded-xl border border-stone-200
                      px-4 py-2.5 text-[13px] transition">

        <select v-model="saring.jenis" :class="[isian, 'flex-1 basis-[8.5rem]']" @change="terapkan">
          <option value="">Semua jenis</option>
          <option v-for="j in opsi.jenis" :key="j" :value="j">{{ j }}</option>
        </select>

        <select v-model="saring.status" :class="[isian, 'flex-1 basis-[8.5rem]']" @change="terapkan">
          <option value="">Semua status</option>
          <option v-for="s in opsi.status" :key="s" :value="s">{{ s[0].toUpperCase() + s.slice(1) }}</option>
        </select>

        <select v-model="saring.tinjau" :class="[isian, 'flex-1 basis-[8.5rem]']" @change="terapkan">
          <option value="">Semua masa tinjau</option>
          <option v-for="t in opsi.tinjau" :key="t.nilai" :value="t.nilai">{{ t.label }}</option>
        </select>

        <button type="submit" class="rounded-xl bg-cam-ink text-white px-4 py-2.5 text-[12.5px]
                                     font-bold hover:bg-cam-panel transition">Cari</button>
        <a :href="tautan.buat" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5
                                      text-[12.5px] font-bold hover:brightness-105 transition">+ Dokumen</a>
      </div>

      <div class="flex flex-wrap gap-3 mt-2.5 px-1">
        <Link :href="tautan.piramida" class="text-[11.5px] font-bold text-cam-lime-deep hover:underline">
          Piramida dokumen →
        </Link>
        <a :href="tautan.daftarInduk" class="text-[11.5px] font-bold text-stone-400 hover:underline">
          Daftar induk (cetak) →
        </a>
      </div>
    </form>

    <div class="space-y-2.5">
      <Link v-for="d in dokumen" :key="d.id" :href="d.url"
            class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5
                   hover:border-cam-lime/40 transition">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="num text-[11.5px] font-bold text-stone-500">{{ d.kode }}</span>
              <span :class="chip" class="text-white" :style="{ background: d.warnaStatus }">{{ d.status }}</span>
              <span :class="chip" class="bg-stone-100 text-stone-500">{{ d.jenis }}</span>
              <span v-if="d.perluTinjau" :class="chip" class="bg-amber-100 text-amber-700">Lewat tinjau</span>
              <span v-else-if="d.segeraTinjau" :class="chip" class="bg-amber-50 text-amber-600">Segera ditinjau</span>
            </div>
            <div class="text-[13.5px] font-bold text-cam-ink mt-1.5 clamp-1">{{ d.judul }}</div>
            <div class="text-[11px] text-stone-400 mt-0.5">
              {{ d.labelRevisi }}
              <template v-if="d.departemen"> · {{ d.departemen }}</template>
              <template v-if="d.tanggalTinjau"> · tinjau {{ d.tanggalTinjau }}</template>
            </div>
          </div>
          <span v-if="d.adaBerkas" class="shrink-0 text-[11px] font-bold text-cam-lime-deep">Ada berkas</span>
        </div>
      </Link>

      <div v-if="!dokumen.length" class="bg-white rounded-2xl border border-dashed border-stone-200 p-10 text-center">
        <p class="text-[13px] text-stone-400">
          Belum ada dokumen.
          <a :href="tautan.buat" class="text-cam-lime-deep font-bold hover:underline">Daftarkan yang pertama</a>.
        </p>
      </div>
    </div>

    <nav v-if="halaman.akhir > 1" class="flex flex-wrap gap-1.5">
      <component v-for="(t, i) in halaman.tautan" :key="i"
                 :is="t.url ? Link : 'span'" :href="t.url ?? undefined"
                 class="px-3 py-1.5 rounded-lg text-[12px] font-semibold border transition"
                 :class="t.aktif ? 'border-transparent bg-cam-ink text-white'
                       : t.url ? 'border-stone-200 text-stone-600 hover:bg-stone-50'
                               : 'border-stone-100 text-stone-300'"
                 v-html="t.label" />
    </nav>
  </div>
</template>
