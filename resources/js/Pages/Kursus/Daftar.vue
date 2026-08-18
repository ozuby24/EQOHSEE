<script setup lang="ts">
/**
 * Katalog kursus.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { HalamanDaftarKursus } from '../../types';

const props = defineProps<HalamanDaftarKursus>();

const saring = useForm({ ...props.f });

function terapkan() {
  saring
    .transform((d) => Object.fromEntries(
      Object.entries(d).filter(([k, v]) => v !== '' && !(k === 'urut' && v === 'baru')),
    ))
    .get(props.tautan.daftar, { preserveState: true, preserveScroll: true, replace: true });
}

function pilihKategori(k: string) {
  saring.kategori = k;
  terapkan();
}

function hapus(url: string, judul: string) {
  if (!confirm(`Hapus kursus "${judul}" beserta modul dan kuisnya?`)) return;
  router.delete(url, { preserveScroll: true });
}

const adaSaringan = () => props.f.q || props.f.kategori || props.f.status || props.f.urut !== 'baru';

const isian = 'ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600 transition';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-6xl mx-auto">

    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-3 mb-5"
          @submit.prevent="terapkan">
      <div class="flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-0 basis-[180px]">
          <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-300"
               fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
          <input v-model="saring.q" placeholder="Cari judul atau deskripsi kursus…"
                 class="ring-focus w-full rounded-xl border border-stone-200 pl-10 pr-3 py-2.5
                        text-[13px] transition">
        </div>

        <select v-model="saring.kategori" :class="[isian, 'flex-1 basis-[8.5rem]']" @change="terapkan" aria-label="Kategori">
          <option value="">Semua jenis</option>
          <option v-for="k in opsi.kategori" :key="k" :value="k">{{ k }}</option>
        </select>

        <select v-model="saring.status" :class="[isian, 'flex-1 basis-[8.5rem]']" @change="terapkan" aria-label="Status">
          <option value="">Semua status</option>
          <option v-for="o in opsi.status" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
        </select>

        <select v-model="saring.urut" :class="[isian, 'flex-1 basis-[8.5rem]']" @change="terapkan" aria-label="Urutan">
          <option v-for="o in opsi.urut" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
        </select>

        <button type="submit" class="rounded-xl bg-cam-ink text-white px-4 py-2.5 text-[12.5px]
                                     font-bold hover:bg-cam-panel transition">Cari</button>

        <Link v-if="adaSaringan()" :href="tautan.daftar"
              class="px-3 py-2.5 text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink transition">
          Reset
        </Link>
      </div>
    </form>

    <div v-if="opsi.kategori.length" class="flex flex-wrap gap-1.5 mb-5">
      <button type="button" @click="pilihKategori('')"
              class="px-3 py-1.5 rounded-full text-[11.5px] font-bold transition"
              :class="!f.kategori ? 'lime-gradient text-white shadow-glow'
                                  : 'bg-white border border-stone-200 text-stone-500 hover:border-cam-lime'">
        Semua
      </button>
      <button v-for="k in opsi.kategori" :key="k" type="button" @click="pilihKategori(k)"
              class="px-3 py-1.5 rounded-full text-[11.5px] font-bold transition"
              :class="f.kategori === k ? 'lime-gradient text-white shadow-glow'
                                       : 'bg-white border border-stone-200 text-stone-500 hover:border-cam-lime'">
        {{ k }}
      </button>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <p class="text-[12.5px] text-stone-400">
        <span class="num font-semibold text-stone-600">{{ halaman.total }}</span> kursus ditemukan
      </p>
      <a v-if="bolehKelola" :href="tautan.buat"
         class="lime-gradient shadow-glow inline-flex items-center gap-2 rounded-xl text-white
                px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
          <path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
        Tambah Kursus
      </a>
    </div>

    <div v-if="!kursus.length" class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
      <p class="text-[13px] text-stone-400">Belum ada kursus.</p>
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="c in kursus" :key="c.id"
           class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden card-hover">
        <div class="h-28 brand-gradient relative">
          <img v-if="c.sampul" :src="c.sampul" alt="" loading="lazy" class="w-full h-full object-cover">
          <div v-else class="w-full h-full grid place-items-center">
            <span class="font-display text-3xl font-black text-white/15">{{ c.inisial }}</span>
          </div>

          <div class="absolute top-2.5 left-2.5 flex gap-1.5">
            <span v-if="c.kategori" class="eq-lencana-kat" :class="`k-${c.nadaKategori}`">{{ c.kategori }}</span>
            <span v-if="c.perluKode" title="Perlu kode akses"
                  class="glass rounded-full text-[10px] font-bold text-white px-2 py-1 inline-flex items-center gap-1">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            </span>
            <span v-if="c.diikuti" class="rounded-full bg-cam-lime text-white text-[10px] font-bold px-2.5 py-1">
              Diikuti
            </span>
          </div>
        </div>

        <div class="p-4">
          <h3 class="text-[14px] font-bold text-cam-ink clamp-1">{{ c.judul }}</h3>
          <p class="text-[12px] text-stone-400 mt-1 clamp-2 leading-relaxed">{{ c.keterangan ?? '—' }}</p>

          <div class="flex items-center gap-2 mt-3 text-[11px] text-stone-400">
            <span class="inline-flex items-center gap-1">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h10" /></svg>
              <span class="num">{{ c.jumlahModul }}</span> modul
            </span>
          </div>

          <div class="mt-3 pt-3 border-t border-stone-100 flex items-center gap-1.5">
            <Link :href="c.urlBelajar"
                  class="lime-gradient rounded-lg text-white px-3.5 py-2 text-[11.5px]
                         font-bold hover:brightness-105 transition">Belajar</Link>
            <Link :href="c.urlDetail"
                  class="px-2.5 py-2 text-[11.5px] font-semibold rounded-lg text-stone-500
                         hover:bg-stone-50 transition">Detail</Link>
            <template v-if="bolehKelola">
              <Link :href="c.urlKelola"
                    class="px-2.5 py-2 text-[11.5px] font-semibold rounded-lg text-cam-lime-deep
                           hover:bg-cam-lime-soft transition">Kelola</Link>
              <button type="button" title="Hapus" @click="hapus(c.urlHapus, c.judul)"
                      class="ml-auto p-2 rounded-lg text-stone-300 hover:text-red-500
                             hover:bg-red-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        d="M19 7l-.87 12.14A2 2 0 0116.14 21H7.86a2 2 0 01-1.99-1.86L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" /></svg>
              </button>
            </template>
          </div>
        </div>
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
</template>
