<script setup lang="ts">
/**
 * Inspeksi — daftar pemeriksaan.
 */
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { HalamanDaftarInspeksi } from '../../types';

const props = defineProps<HalamanDaftarInspeksi>();

const isi = reactive({ ...props.saring });
const memuat = ref(false);

function kirim() {
  memuat.value = true;

  const data: Record<string, string> = {};
  for (const [k, v] of Object.entries(isi)) if (v) data[k] = String(v);

  router.get('/inspeksi', data, {
    only: ['inspeksi', 'halaman', 'saring'],
    preserveState: true, preserveScroll: true, replace: true,
    onFinish: () => { memuat.value = false; },
  });
}

function reset() {
  Object.assign(isi, { status: null, template: null });
  kirim();
}

const pilihan =
  'ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600';
</script>

<template>
  <Head title="Daftar Inspeksi" />

  <div class="max-w-6xl mx-auto space-y-5">

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-3 flex flex-wrap items-center gap-2">
      <select v-model="isi.status" :class="pilihan" @change="kirim" aria-label="Status">
        <option :value="null">Semua status</option>
        <option v-for="s in opsi.status" :key="s" :value="s">{{ s }}</option>
      </select>

      <select v-model="isi.template" :class="pilihan" @change="kirim" aria-label="Templat">
        <option :value="null">Semua jenis</option>
        <option v-for="t in opsi.template" :key="t.id" :value="String(t.id)">{{ t.nama }}</option>
      </select>

      <span class="text-[11.5px] text-stone-400 num px-1">
        {{ memuat ? 'memuat…' : `${halaman.total} inspeksi` }}
      </span>

      <button type="button" class="px-3 py-2.5 text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink"
              @click="reset">Reset</button>

      <a :href="tautan.buat"
         class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px]
                font-bold hover:brightness-105 transition ml-auto">+ Buat Inspeksi</a>
    </div>

    <div class="space-y-2.5 transition-opacity" :class="memuat ? 'opacity-50' : ''">
      <Link v-for="i in inspeksi" :key="i.id" :href="i.url"
            class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5
                   hover:border-cam-lime/40 transition">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-[10px] font-bold bg-cam-ink text-white px-2 py-0.5 rounded num">{{ i.kode }}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                    :class="i.status === 'Selesai' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                {{ i.status }}
              </span>
              <span v-if="i.template"
                    class="text-[10px] font-semibold bg-stone-100 text-stone-500 px-2 py-0.5 rounded-full">
                {{ i.template }}
              </span>
            </div>

            <p class="text-[13.5px] font-semibold text-cam-ink mt-2">{{ i.judul }}</p>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11.5px] text-stone-400 mt-2">
              <span>📍 {{ i.lokasi ?? '—' }}</span>
              <span>{{ i.tanggal }}</span>
              <span v-if="i.perusahaan">{{ i.perusahaan }}</span>
              <span class="num">{{ i.jumlahItem }} parameter</span>
            </div>

            <p v-if="i.inspektur.length" class="text-[11px] text-stone-400 mt-1.5">
              Inspektur: {{ i.inspektur.join(', ') }}
            </p>
          </div>
        </div>
      </Link>

      <div v-if="!inspeksi.length" class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
        <p class="text-[13px] text-stone-400">Belum ada inspeksi.</p>
        <a :href="tautan.buat" class="inline-block mt-3 text-[12.5px] font-bold text-cam-lime-deep hover:underline">
          Buat inspeksi pertama →
        </a>
      </div>
    </div>

    <div v-if="halaman.akhir > 1" class="flex flex-wrap gap-1.5">
      <component v-for="(t, n) in halaman.tautan" :key="n"
                 :is="t.url ? Link : 'span'" :href="t.url ?? undefined"
                 :only="['inspeksi', 'halaman']" preserve-state preserve-scroll
                 class="min-w-[36px] text-center rounded-lg border px-3 py-1.5 text-[12px] font-semibold transition"
                 :class="t.aktif
                   ? 'bg-[color:var(--eq-aksen,#F57C00)] text-white border-transparent'
                   : t.url ? 'bg-white text-stone-600 border-stone-200 hover:border-stone-400'
                           : 'bg-white text-stone-300 border-stone-100 cursor-default'"
                 v-html="t.label" />
    </div>
  </div>
</template>
