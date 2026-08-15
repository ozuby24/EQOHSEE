<script setup lang="ts">
/**
 * Personalia — Direktori.
 *
 * Pencariannya dikirim ke server, bukan disaring di peramban: yang
 * dimuat hanya satu halaman dari dua puluh empat orang, jadi menyaring
 * di sini hanya menyaring sisa yang kebetulan ikut terbawa.
 *
 * Ketikan ditunda sebentar sebelum dikirim, dan hanya prop daftarnya
 * yang dimuat ulang — kotak pencariannya tidak kehilangan fokus di
 * tengah orang mengetik.
 */
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type { HalamanDirektori, OrangDirektori, PropBersama } from '../../types';

const props = defineProps<HalamanDirektori>();
const diriId = (usePage().props as unknown as PropBersama).pengguna?.id ?? null;

/**
 * Menetapkan perusahaan seorang pengguna — administrator saja.
 *
 * Ini yang menentukan data siapa yang boleh dilihat orang itu, bukan
 * sekadar isian identitas, jadi daftarnya pun tidak dikirim ke pemakai
 * biasa. Perubahan langsung disimpan begitu dipilih: tombol simpan
 * terpisah pada dua puluh empat kartu sekaligus membuat orang kehilangan
 * jejak mana yang sudah tersimpan dan mana yang belum.
 */
const menetapkan = ref<number | null>(null);

function tetapkan(o: OrangDirektori, id: string) {
  menetapkan.value = o.id;

  router.post(`/personalia/direktori/${o.id}/perusahaan`, { company_id: id || null }, {
    preserveScroll: true,
    preserveState: true,
    only: ['orang', 'halaman', 'kilat'],
    onFinish: () => { menetapkan.value = null; },
  });
}

function pesan(o: OrangDirektori) {
  router.post('/pesan/mulai', { user_id: o.id });
}

const cari = ref(props.cari);
const mencari = ref(false);

let jeda: ReturnType<typeof setTimeout> | null = null;

watch(cari, (nilai) => {
  if (jeda) clearTimeout(jeda);

  jeda = setTimeout(() => {
    mencari.value = true;

    router.get('/personalia/direktori', { cari: nilai },
      {
        only: ['orang', 'halaman', 'cari'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => { mencari.value = false; },
      });
  }, 350);
});

onBeforeUnmount(() => { if (jeda) clearTimeout(jeda); });
</script>

<template>
  <Head title="Direktori" />

  <div class="max-w-[1200px] mx-auto space-y-5">

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-3 flex items-center gap-2.5">
      <input v-model="cari" placeholder="Cari nama, jabatan, atau departemen…"
             class="flex-1 rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]
                    focus:border-[color:var(--eq-aksen,#F57C00)] focus:ring-0">
      <span class="text-[11.5px] text-stone-400 num pr-2 shrink-0">
        {{ mencari ? 'mencari…' : `${halaman.total} orang` }}
      </span>
    </div>

    <div v-if="!orang.length" class="bg-white rounded-2xl shadow-card border border-stone-100 px-6 py-12 text-center">
      <p class="text-[13.5px] font-bold text-[#0F1720]">
        {{ cari ? 'Tidak ada yang cocok dengan pencarian Anda' : 'Belum ada rekan terdaftar' }}
      </p>
    </div>

    <template v-else>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 transition-opacity"
           :class="mencari ? 'opacity-50' : ''">
        <div v-for="o in orang" :key="o.id"
             class="bg-white rounded-2xl shadow-card border border-stone-100 p-4 flex items-start gap-3.5">
          <img v-if="o.avatar" :src="o.avatar" alt=""
               class="w-12 h-12 rounded-full object-cover border border-stone-200 shrink-0">
          <span v-else class="w-12 h-12 rounded-full grid place-items-center text-white font-black shrink-0"
                style="background:linear-gradient(135deg,var(--eq-aksen,#F57C00),#FF9800)">
            {{ o.inisial }}
          </span>

          <div class="min-w-0 flex-1">
            <p class="text-[13px] font-bold text-[#0F1720] truncate">{{ o.nama }}</p>
            <p class="text-[11.5px] text-stone-500 truncate">
              {{ o.jabatan ?? '—' }}<template v-if="o.departemen"> · {{ o.departemen }}</template>
            </p>

            <div class="mt-2.5 space-y-1">
              <a v-if="o.email" :href="`mailto:${o.email}`"
                 class="block text-[11.5px] text-stone-600 truncate hover:underline">{{ o.email }}</a>
              <a v-if="o.telepon" :href="`tel:${o.telepon}`"
                 class="block text-[11.5px] text-stone-600 hover:underline">{{ o.telepon }}</a>
            </div>

            <button v-if="o.id !== diriId" type="button" @click="pesan(o)"
                    class="mt-2.5 text-[11.5px] font-semibold text-[color:var(--eq-aksen,#F57C00)] hover:underline">
              Kirim Pesan
            </button>

            <div v-if="admin" class="mt-2.5">
              <select :value="o.perusahaanId ?? ''" :disabled="menetapkan === o.id"
                      @change="tetapkan(o, ($event.target as HTMLSelectElement).value)"
                      class="w-full rounded-lg border border-stone-200 px-2 py-1.5 text-[11px] text-stone-600
                             disabled:opacity-50
                             focus:border-[color:var(--eq-aksen,#F57C00)] focus:ring-0">
                <option value="">— tanpa perusahaan —</option>
                <option v-for="c in daftar" :key="c.id" :value="c.id">{{ c.nama }}</option>
              </select>
            </div>
            <p v-else-if="o.perusahaan" class="mt-2 text-[10.5px] text-stone-400 truncate">
              {{ o.perusahaan }}
            </p>
          </div>
        </div>
      </div>

      <div v-if="halaman.akhir > 1" class="flex flex-wrap gap-1.5">
        <component v-for="(t, i) in halaman.tautan" :key="i"
                   :is="t.url ? Link : 'span'" :href="t.url ?? undefined"
                   :only="['orang', 'halaman', 'cari']" preserve-state preserve-scroll
                   class="min-w-[36px] text-center rounded-lg border px-3 py-1.5 text-[12px] font-semibold transition"
                   :class="t.aktif
                     ? 'bg-[color:var(--eq-aksen,#F57C00)] text-white border-transparent'
                     : t.url ? 'bg-white text-stone-600 border-stone-200 hover:border-stone-400'
                             : 'bg-white text-stone-300 border-stone-100 cursor-default'"
                   v-html="t.label" />
      </div>
    </template>
  </div>
</template>
