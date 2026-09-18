<script setup lang="ts">
/**
 * Pengumuman — kabar dan surat edaran untuk seluruh pengguna.
 *
 * Barisnya dibuka sebagai pop-out, bukan sebagai perpindahan halaman.
 * Yang menelusuri daftar ini biasanya membuka tiga atau empat
 * pengumuman berturut-turut, dan tombol kembali peramban mengembalikan
 * ke puncak daftar setiap kali — bukan ke baris yang tadi dibaca.
 *
 * Halaman penuhnya tetap ada dan tautannya ada di kaki pop-out. Alamat
 * pengumuman kerap disalin ke grup pesan, dan alamat yang mati karena
 * diganti pop-out adalah kerugian yang baru terlihat ketika orang
 * mengeluh tautannya rusak.
 */
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { HalamanDaftarBerita, Pengumuman } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import PopPengumuman from '../../Components/PopPengumuman.vue';
import { useDialog } from '../../dialog';

const { dialog, tanya, batal, lanjut } = useDialog();

defineProps<HalamanDaftarBerita>();

/* Barisnya sendiri yang disimpan, bukan indeksnya. Indeks akan menunjuk
   pengumuman LAIN begitu daftarnya bergeser — satu pengumuman baru
   terbit, dan yang terbuka bukan yang diklik. */
const terbuka = ref<Pengumuman | null>(null);

async function hapus(url: string, judul: string) {
  if (!await tanya(`Hapus pengumuman "${judul}"?`)) return;
  router.delete(url, { preserveScroll: true });
}
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-3xl mx-auto">

    <div v-if="bolehUbah" class="flex justify-end mb-5">
      <a :href="tautan.buat" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5
                                    text-[12.5px] font-bold hover:brightness-105 transition">
        + Tulis Pengumuman
      </a>
    </div>

    <div class="space-y-3">
      <article v-for="b in berita" :key="b.id"
               class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden
                      hover:border-cam-lime/30 transition">

        <!-- Satu tombol memuat seluruh badan kartu, jadi ke mana pun
             diklik hasilnya sama. Kartu yang hanya terbuka lewat
             judulnya membuat sisanya terasa mati. -->
        <button type="button" class="w-full text-left flex gap-4 p-6" @click="terbuka = b">
          <img v-if="b.sampul" :src="b.sampul" alt="" aria-hidden="true"
               class="w-24 h-24 rounded-xl object-cover shrink-0 bg-stone-100">

          <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-2">
              <span class="text-[10px] font-bold uppercase tracking-[0.15em] text-cam-lime-dark">
                {{ b.tanggal }}
              </span>

              <span v-if="b.sudahDibaca"
                    class="text-[9.5px] font-bold uppercase tracking-wide text-emerald-700
                           bg-emerald-50 rounded px-1.5 py-0.5">Sudah dibaca</span>

              <span v-if="b.lampiran"
                    class="text-[9.5px] font-bold uppercase tracking-wide text-stone-500
                           bg-stone-100 rounded px-1.5 py-0.5">Berlampiran</span>
            </span>

            <strong class="block text-[17px] font-bold text-cam-ink mt-1.5 leading-snug">{{ b.judul }}</strong>
            <small class="block text-[12.5px] text-stone-400 mt-2 clamp-3 leading-relaxed">{{ b.ringkasan }}</small>
          </span>
        </button>

        <div v-if="bolehUbah" class="flex gap-1 px-6 pb-4 pt-3 mx-0 border-t border-stone-100">
          <a :href="b.urlUbah" class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg
                                      text-cam-lime-deep hover:bg-cam-lime-soft">Edit</a>
          <button type="button" @click="hapus(b.urlHapus, b.judul)"
                  class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg
                         text-red-500 hover:bg-red-50">Hapus</button>
        </div>
      </article>

      <div v-if="!berita.length"
           class="bg-white rounded-2xl border border-dashed border-stone-200 p-14
                  text-center text-[13px] text-stone-400">
        Belum ada pengumuman.
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

  <PopPengumuman :item="terbuka" @tutup="terbuka = null" />

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>
