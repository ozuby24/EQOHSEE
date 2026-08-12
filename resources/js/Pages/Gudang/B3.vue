<script setup lang="ts">
/**
 * Gudang — register B3 dan matriks pantangan penyimpanan.
 *
 * Matriksnya disusun di server. Menyusunnya di sini berarti menyalin
 * App\Support\Gudang::pantangan ke TypeScript, dan salinan yang
 * tertinggal akan melaporkan aman untuk pasangan yang justru berbahaya.
 */
import { Head } from '@inertiajs/vue3';
import { angka } from '../../angka';
import type { HalamanB3Gudang } from '../../types';

defineProps<HalamanB3Gudang>();

const nadaSisa = (sisa: number) => (sisa < 0 ? 't-merah' : sisa <= 30 ? 't-kuning' : 't-biru');
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="eq-panel">
      <div class="eq-panel-kepala">
        <div>
          <h3>Matriks Pantangan Penyimpanan</h3>
          <p class="text-[11.5px] text-stone-400 mt-0.5">
            Merah berarti kedua kelas tidak boleh disimpan berdekatan.
          </p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="text-[11px] border-collapse">
          <thead>
            <tr>
              <th class="p-2"></th>
              <!-- Judul kolom ditegakkan: sepuluh nama kelas mendatar
                   membuat tabelnya jauh lebih lebar daripada layar. -->
              <th v-for="k in judulKolom" :key="k" class="p-1.5 align-bottom">
                <span class="block whitespace-nowrap text-stone-500 font-semibold"
                      style="writing-mode:vertical-rl;transform:rotate(180deg);max-height:120px">{{ k }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="baris in matriks" :key="baris.kelas">
              <th class="p-2 text-right whitespace-nowrap text-stone-600 font-semibold">{{ baris.nama }}</th>
              <td v-for="(sel, i) in baris.sel" :key="i" class="p-0">
                <span class="block w-7 h-7 m-0.5 rounded-md"
                      :class="sel.sama ? 'bg-stone-100'
                            : sel.alasan ? 'bg-red-500' : 'bg-emerald-50 border border-emerald-100'"
                      :title="sel.alasan ?? undefined"></span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="text-[11px] text-stone-400 mt-3">
        Dasar: PP No. 74 Tahun 2001, PP No. 22 Tahun 2021, dan Permenaker No. 5 Tahun 2018.
        Arahkan penunjuk ke kotak merah untuk melihat alasannya.
      </p>
    </section>

    <section v-if="langgar.length" class="rounded-2xl border border-red-200 bg-red-50 p-5">
      <h3 class="text-[14px] font-bold text-red-800">
        {{ langgar.length }} pelanggaran penyimpanan sedang berlangsung
      </h3>
      <p class="text-[12px] text-red-700 mt-0.5">Hanya bahan yang benar-benar bersaldo yang dihitung.</p>
      <ul class="mt-3 grid gap-2 sm:grid-cols-2">
        <li v-for="(x, i) in langgar" :key="i" class="rounded-xl bg-white/70 border border-red-100 px-3.5 py-2.5">
          <p class="text-[12.5px] font-bold text-red-800">{{ x.a }} × {{ x.b }}</p>
          <p class="text-[11.5px] text-red-700 mt-0.5">{{ x.alasan }}</p>
          <p class="text-[11px] text-red-500 mt-1">Lokasi: {{ x.lokasi }}</p>
        </li>
      </ul>
    </section>

    <section class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-6 py-4 border-b border-stone-100">
        <h3 class="text-[15px] font-bold text-cam-ink">Daftar Bahan</h3>
      </div>

      <div v-if="!b3.length" class="px-6 py-12 text-center">
        <p class="text-[13.5px] font-bold text-cam-ink">Belum ada bahan B3 terdaftar</p>
        <p class="text-[12.5px] text-stone-500 mt-1">
          Tambahkan lewat Daftar Barang dengan kategori Bahan Berbahaya.
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="text-left text-stone-500 bg-stone-50 border-b border-stone-100">
              <th class="py-3 px-4 font-semibold">Bahan</th>
              <th class="py-3 px-3 font-semibold">Kelas Bahaya</th>
              <th class="py-3 px-3 font-semibold">Wujud</th>
              <th class="py-3 px-3 font-semibold">Lokasi</th>
              <th class="py-3 px-3 font-semibold text-right">Stok</th>
              <th class="py-3 px-4 font-semibold">LDK</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in b3" :key="b.id" class="border-b border-stone-50 last:border-0">
              <td class="py-3 px-4">
                <span class="font-semibold text-cam-ink">{{ b.nama }}</span>
                <span class="block text-[11px] text-stone-400">
                  {{ b.kode }}<template v-if="b.unNumber"> · UN {{ b.unNumber }}</template>
                </span>
              </td>
              <td class="py-3 px-3">
                <template v-if="b.namaKelas">
                  <span class="font-semibold text-red-700">{{ b.namaKelas }}</span>
                  <span class="block text-[11px] text-stone-400 max-w-[240px]">{{ b.caraSimpan }}</span>
                </template>
                <span v-else class="text-amber-700 font-semibold">Belum digolongkan</span>
              </td>
              <td class="py-3 px-3 text-stone-500">{{ b.wujud ?? '—' }}</td>
              <td class="py-3 px-3 text-stone-500">{{ b.lokasi ?? '—' }}</td>
              <td class="py-3 px-3 text-right font-bold tabular-nums text-cam-ink">
                {{ angka(b.stok) }} <span class="text-stone-400 font-normal">{{ b.satuan }}</span>
              </td>
              <td class="py-3 px-4">
                <a v-if="b.msds" :href="b.msds" target="_blank" rel="noopener"
                   class="text-[12px] font-semibold" style="color:var(--eq-aksen,#F57C00)">Buka</a>
                <span v-else class="text-[11px] font-bold px-2 py-1 rounded-md bg-amber-50 text-amber-700">
                  Belum ada
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="kedaluwarsa.length" class="eq-panel">
      <div class="eq-panel-kepala"><h3>Batch Mendekati Kedaluwarsa</h3></div>
      <ul class="grid gap-2 sm:grid-cols-2">
        <li v-for="(k, i) in kedaluwarsa" :key="i"
            class="flex items-center gap-3 rounded-xl border border-stone-100 px-3.5 py-2.5">
          <span class="w-12 h-12 rounded-xl grid place-items-center shrink-0 text-[11px] font-bold"
                :class="nadaSisa(k.sisa)">
            {{ k.sisa < 0 ? 'Lewat' : k.sisa + 'h' }}
          </span>
          <div class="min-w-0">
            <p class="text-[12.5px] font-bold text-cam-ink truncate">{{ k.nama }}</p>
            <p class="text-[11.5px] text-stone-500">
              {{ k.tanggal }}<template v-if="k.batch"> · Batch {{ k.batch }}</template>
            </p>
          </div>
        </li>
      </ul>
    </section>

  </div>
</template>
