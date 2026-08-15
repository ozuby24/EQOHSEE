<script setup lang="ts">
/**
 * Gudang — daftar barang.
 *
 * Penyaring dikirim ulang sebagai permintaan GET, bukan disaring di
 * peramban: status stok diturunkan dari mutasi di server, dan menyaring
 * salinan yang sudah terkirim berarti angka di layar bisa berbeda dari
 * yang tercatat.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { angka } from '../../angka';
import type { HalamanBarangGudang } from '../../types';

const props = defineProps<HalamanBarangGudang>();

const saring = useForm({ ...props.f });

function terapkan() {
  // Penyaring kosong dibuang dari alamatnya. Membiarkannya membuat
  // tautan yang disalin orang penuh '?cari=&kategori=&status=' — panjang,
  // sukar dibaca, dan tampak seperti penyaring yang sedang aktif.
  saring
    .transform((d) => Object.fromEntries(Object.entries(d).filter(([, v]) => v !== '')))
    .get(props.tautan.daftar, { preserveState: true, preserveScroll: true, replace: true });
}

const adaSaringan = () => Object.values(props.f).some((v) => v !== '');

function hapus(url: string, nama: string) {
  if (!confirm(`Hapus atau nonaktifkan "${nama}"?`)) return;
  router.delete(url, { preserveScroll: true });
}

const isian = 'rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] ring-focus transition';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-3
                 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-5"
          @submit.prevent="terapkan">
      <input v-model="saring.cari" :class="[isian, 'lg:col-span-2']"
             placeholder="Cari nama, kode, part number…">

      <select v-model="saring.kategori" :class="isian" @change="terapkan">
        <option value="">Semua kategori</option>
        <option v-for="o in opsi.kategori" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
      </select>

      <select v-model="saring.status" :class="isian" @change="terapkan">
        <option value="">Semua status</option>
        <option v-for="o in opsi.status" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
      </select>

      <div class="flex gap-2">
        <button type="submit" class="eq-btn-utama" style="padding:10px 20px">Saring</button>
        <Link v-if="adaSaringan()" :href="tautan.daftar"
              class="px-3 py-2.5 text-[12.5px] font-semibold text-stone-500 hover:underline self-center">
          Bersihkan
        </Link>
      </div>
    </form>

    <div class="flex items-center justify-between gap-3 flex-wrap">
      <p class="text-[12.5px] text-stone-500">
        <b class="text-cam-ink">{{ barang.length }}</b> barang ditemukan
      </p>
      <a v-if="bolehUbah" :href="tautan.baru" class="eq-btn-utama" style="flex:none;padding:10px 20px">
        + Tambah Barang
      </a>
    </div>

    <div v-if="!barang.length"
         class="bg-white rounded-2xl shadow-card border border-stone-100 px-6 py-12 text-center">
      <p class="text-[13.5px] font-bold text-cam-ink">Belum ada barang yang cocok</p>
      <p class="text-[12.5px] text-stone-500 mt-1">Ubah penyaring, atau tambahkan barang baru.</p>
    </div>

    <div v-else class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="text-left text-stone-500 bg-stone-50 border-b border-stone-100">
              <th class="py-3 px-4 font-semibold">Barang</th>
              <th class="py-3 px-3 font-semibold">Kategori</th>
              <th class="py-3 px-3 font-semibold">Lokasi</th>
              <th class="py-3 px-3 font-semibold text-right">Stok</th>
              <th class="py-3 px-3 font-semibold text-right">Min.</th>
              <th class="py-3 px-3 font-semibold">Status</th>
              <th v-if="bolehUbah" class="py-3 px-4"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in barang" :key="b.id" class="border-b border-stone-50 last:border-0">
              <td class="py-3 px-4">
                <span class="font-semibold text-cam-ink">{{ b.nama }}</span>
                <span class="block text-[11px] text-stone-400">
                  {{ b.kode }}<template v-if="b.partNumber"> · {{ b.partNumber }}</template>
                </span>

                <span v-if="b.kategori === 'b3' && b.namaKelas"
                      class="inline-block mt-1 text-[10.5px] font-bold px-2 py-0.5 rounded-md
                             bg-red-50 text-red-700">{{ b.namaKelas }}</span>
                <span v-if="b.kategori === 'b3' && !b.msds"
                      class="inline-block mt-1 ml-1 text-[10.5px] font-bold px-2 py-0.5 rounded-md
                             bg-amber-50 text-amber-700">Tanpa LDK</span>
              </td>
              <td class="py-3 px-3">
                <span class="eq-lencana-kat" :class="`k-${b.nadaKategori}`">{{ b.namaKategori }}</span>
              </td>
              <td class="py-3 px-3 text-stone-500">{{ b.lokasi ?? '—' }}</td>
              <td class="py-3 px-3 text-right font-bold tabular-nums text-cam-ink">
                {{ angka(b.stok) }} <span class="text-stone-400 font-normal">{{ b.satuan }}</span>
              </td>
              <td class="py-3 px-3 text-right text-stone-500 tabular-nums">{{ angka(b.stokMin) }}</td>
              <td class="py-3 px-3">
                <span class="eq-lencana-kat" :class="`k-${b.status.nada}`">{{ b.status.nama }}</span>
              </td>
              <td v-if="bolehUbah" class="py-3 px-4 text-right whitespace-nowrap">
                <a :href="b.urlUbah" class="text-[12px] font-semibold"
                   style="color:var(--eq-aksen,#F57C00)">Ubah</a>
                <button type="button" class="text-[12px] font-semibold text-red-600 ml-2"
                        @click="hapus(b.urlHapus, b.nama)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>
