<script setup lang="ts">
/**
 * Prosedur & SOP.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { HalamanDaftarProsedur } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, batal, lanjut } = useDialog();


const props = defineProps<HalamanDaftarProsedur>();

const saring = useForm({ q: props.q });

function cari() {
  saring
    .transform((d) => (d.q ? d : {}))
    .get(props.tautan.daftar, { preserveState: true, preserveScroll: true, replace: true });
}

async function hapus(url: string, judul: string) {
  if (!await tanya(`Hapus prosedur "${judul}"?`)) return;
  router.delete(url, { preserveScroll: true });
}
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto">

    <div class="flex flex-wrap items-center gap-2.5 mb-5">
      <form class="flex-1 min-w-0 basis-[200px]" @submit.prevent="cari">
        <input v-model="saring.q" placeholder="Cari prosedur atau kode…"
               class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-2.5
                      text-[13px] transition">
      </form>
      <a v-if="bolehUbah" :href="tautan.buat"
         class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px]
                font-bold hover:brightness-105 transition">+ Tambah Prosedur</a>
    </div>

    <div class="space-y-2.5">
      <div v-for="p in prosedur" :key="p.id"
           class="bg-white rounded-2xl shadow-card border border-stone-100 p-5
                  hover:border-cam-lime/30 transition">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span v-if="p.kode" class="text-[10px] font-bold bg-cam-ink text-white px-2 py-0.5
                                          rounded tracking-wide">{{ p.kode }}</span>
              <span v-if="p.kategori" class="text-[10px] font-semibold bg-cam-lime-soft
                                              text-cam-lime-deep px-2 py-0.5 rounded-full">{{ p.kategori }}</span>
            </div>
            <h3 class="text-[14px] font-bold text-cam-ink mt-2">{{ p.judul }}</h3>
            <p v-if="p.keterangan" class="text-[12.5px] text-stone-400 mt-1 leading-relaxed">{{ p.keterangan }}</p>
          </div>
          <div class="flex items-center gap-1 shrink-0">
            <a v-if="p.url" :href="p.url" target="_blank" rel="noopener"
               class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg text-stone-500 hover:bg-stone-50">Buka</a>
            <template v-if="bolehUbah">
              <a :href="p.urlUbah" class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg
                                          text-cam-lime-deep hover:bg-cam-lime-soft">Edit</a>
              <button type="button" @click="hapus(p.urlHapus, p.judul)"
                      class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg
                             text-red-500 hover:bg-red-50">Hapus</button>
            </template>
          </div>
        </div>
      </div>

      <div v-if="!prosedur.length"
           class="bg-white rounded-2xl border border-dashed border-stone-200 p-14
                  text-center text-[13px] text-stone-400">
        Belum ada prosedur.
      </div>
    </div>

    <nav v-if="halaman.akhir > 1" class="mt-6 flex flex-wrap gap-1.5">
      <component v-for="(t, i) in halaman.tautan" :key="i"
                 :is="t.url ? Link : 'span'" :href="t.url ?? undefined"
                 class="px-3 py-1.5 rounded-lg text-[12px] font-semibold border transition"
                 :class="t.aktif ? 'border-transparent bg-cam-ink text-white'
                       : t.url ? 'border-stone-200 text-stone-600 hover:bg-stone-50'
                               : 'border-stone-100 text-stone-300'"
                 v-html="t.label" />
    </nav>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>
