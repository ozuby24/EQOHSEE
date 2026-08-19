<script setup lang="ts">
/**
 * Kelola perusahaan.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { HalamanDaftarPerusahaan } from '../../../types';
import Dialog from '../../../Components/Dialog.vue';
import { useDialog } from '../../../dialog';
const { dialog, tanya, batal, lanjut } = useDialog();


const props = defineProps<HalamanDaftarPerusahaan>();

const saring = useForm({ q: props.q });

function cari() {
  saring
    .transform((d) => (d.q ? d : {}))
    .get(props.tautan.daftar, { preserveState: true, preserveScroll: true, replace: true });
}

async function hapus(url: string, nama: string) {
  if (!await tanya(`Hapus perusahaan "${nama}"? Data penilaian & kuesionernya ikut terhapus.`)) return;
  router.delete(url, { preserveScroll: true });
}

const nadaRisiko = (r: string) =>
  r === 'Tinggi' ? 'bg-red-100 text-red-700'
  : r === 'Sedang' ? 'bg-amber-100 text-amber-700'
  : 'bg-cam-lime-soft text-cam-lime-deep';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-6xl mx-auto">

    <div class="flex flex-wrap items-center gap-2.5 mb-5">
      <form class="flex-1 min-w-0 basis-[200px]" @submit.prevent="cari">
        <input v-model="saring.q" placeholder="Cari nama atau kode perusahaan…"
               class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-2.5 text-[13px]">
      </form>
      <a :href="tautan.buat" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5
                                    text-[12.5px] font-bold hover:brightness-105 transition">
        + Tambah Perusahaan
      </a>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
      <div v-for="c in perusahaan" :key="c.id"
           class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 card-hover">
        <div class="flex items-start gap-3">
          <div class="w-11 h-11 rounded-xl grid place-items-center shrink-0 overflow-hidden"
               :class="c.logo ? '' : 'lime-gradient'">
            <img v-if="c.logo" :src="c.logo" class="w-full h-full object-cover" alt="">
            <span v-else class="font-black text-white text-[15px]">{{ c.inisial }}</span>
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <span v-if="c.kode" class="text-[9.5px] font-bold bg-cam-ink text-white px-1.5 py-0.5
                                          rounded tracking-wide">{{ c.kode }}</span>
              <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded" :class="nadaRisiko(c.risiko)">
                Risiko {{ c.risiko }}
              </span>
            </div>
            <h3 class="text-[14px] font-bold text-cam-ink mt-1.5">{{ c.nama }}</h3>
            <div class="text-[11.5px] text-stone-400 mt-0.5">
              {{ c.izin ?? '—' }} · {{ c.komoditas ?? '—' }}
            </div>
            <div class="text-[11.5px] text-stone-400">{{ c.lokasi ?? '—' }}</div>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-stone-100 text-center">
          <div v-for="[l, v] in [['KTT', c.ktt ?? '—'], ['PJO', c.pjo ?? '—'], ['Pekerja', c.pekerja]]" :key="l">
            <div class="text-[9.5px] uppercase tracking-wide text-stone-400 font-bold">{{ l }}</div>
            <div class="text-[12px] font-semibold text-stone-600 truncate">{{ v }}</div>
          </div>
        </div>

        <div class="flex items-center justify-between mt-3 pt-3 border-t border-stone-100">
          <span class="text-[11px] text-stone-400">{{ c.pengguna }} pengguna</span>
          <div class="flex gap-1">
            <Link :href="c.urlTpkkp" class="px-2.5 py-1.5 text-[11.5px] font-semibold rounded-lg
                                            text-stone-500 hover:bg-stone-50">PTPKKP</Link>
            <a :href="c.urlUbah" class="px-2.5 py-1.5 text-[11.5px] font-semibold rounded-lg
                                        text-cam-lime-deep hover:bg-cam-lime-soft">Edit</a>
            <button type="button" @click="hapus(c.urlHapus, c.nama)"
                    class="px-2.5 py-1.5 text-[11.5px] font-semibold rounded-lg
                           text-red-500 hover:bg-red-50">Hapus</button>
          </div>
        </div>
      </div>

      <div v-if="!perusahaan.length"
           class="sm:col-span-2 bg-white rounded-2xl border border-dashed border-stone-200 p-14
                  text-center text-[13px] text-stone-400">
        Belum ada perusahaan.
      </div>
    </div>

    <nav v-if="halaman.akhir > 1" class="mt-5 flex flex-wrap gap-1.5">
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
