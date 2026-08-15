<script setup lang="ts">
/**
 * Gudang — ringkasan persediaan.
 *
 * Pelanggaran penyimpanan B3 diletakkan di atas angka apa pun: bahan
 * berpantangan yang tersimpan berdampingan adalah satu-satunya hal di
 * halaman ini yang bisa melukai orang hari ini juga.
 */
import { Head, Link } from '@inertiajs/vue3';
import BarisMutasi from '../../Components/BarisMutasi.vue';
import { angka } from '../../angka';
import type { HalamanGudang } from '../../types';

const props = defineProps<HalamanGudang>();

const kpi = [
  { label: 'Jenis Barang', nilai: props.r.jumlah,  ket: 'Terdaftar dan aktif',   nada: 'toska'  },
  { label: 'Stok Aman',    nilai: props.r.aman,    ket: 'Di atas batas minimum', nada: 'hijau'  },
  { label: 'Menipis',      nilai: props.r.menipis, ket: 'Perlu segera dipesan',  nada: 'kuning' },
  { label: 'Habis',        nilai: props.r.habis,   ket: 'Tidak ada di rak',      nada: 'merah'  },
];

const dua = (n: number) => String(n).padStart(2, '0');

const nadaSisa = (sisa: number) => (sisa < 0 ? 't-merah' : sisa <= 30 ? 't-kuning' : 't-biru');

const lebar = (n: number) => (props.r.jumlah > 0 ? Math.round((n / props.r.jumlah) * 100) : 0);
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="k in kpi" :key="k.label" class="eq-kpi">
        <span class="eq-kpi-ikon" :class="`t-${k.nada}`">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10" /></svg>
        </span>
        <div class="min-w-0">
          <p class="eq-kpi-label">{{ k.label }}</p>
          <p class="eq-kpi-nilai">{{ dua(k.nilai) }}</p>
          <p class="eq-kpi-ket">{{ k.ket }}</p>
        </div>
      </div>
    </div>

    <section v-if="langgar.length" class="rounded-2xl border border-red-200 bg-red-50 p-5">
      <div class="flex items-start gap-3">
        <svg viewBox="0 0 24 24" fill="none" stroke="#C03A3A" stroke-width="1.9"
             stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 shrink-0 mt-0.5" aria-hidden="true">
          <path d="M12 9.3v4.2m0 3.3h.01M10.4 4 2.5 17.8A1.8 1.8 0 0 0 4.1 20.5h15.8a1.8 1.8 0 0 0 1.6-2.7L13.6 4a1.8 1.8 0 0 0-3.2 0Z" />
        </svg>
        <div class="min-w-0 flex-1">
          <h3 class="text-[14px] font-bold text-red-800">
            {{ langgar.length }} pasang bahan berpantangan disimpan bersama
          </h3>
          <p class="text-[12px] text-red-700 mt-0.5">
            Pisahkan sebelum inspeksi berikutnya — dasar: PP 74/2001 dan Permenaker No. 5 Tahun 2018.
          </p>

          <ul class="mt-3 space-y-2">
            <li v-for="(x, i) in langgar" :key="i"
                class="rounded-xl bg-white/70 border border-red-100 px-3.5 py-2.5">
              <p class="text-[12.5px] font-bold text-red-800">
                {{ x.a }} <span class="font-normal text-red-500">×</span> {{ x.b }}
              </p>
              <p class="text-[11.5px] text-red-700 mt-0.5">{{ x.alasan }}</p>
              <p class="text-[11px] text-red-500 mt-1">Lokasi: {{ x.lokasi }}</p>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <div class="grid gap-5 lg:grid-cols-3">

      <section class="eq-panel lg:col-span-2">
        <div class="eq-panel-kepala">
          <h3>Perlu Ditindak</h3>
          <Link :href="tautan.menipis" class="eq-panel-lihat">Lihat Semua →</Link>
        </div>

        <div v-if="!kritis.length" class="eq-kosong">
          <strong>Seluruh stok di atas batas minimum</strong>
          <p>Tidak ada barang yang perlu dipesan hari ini.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-[12.5px]">
            <thead>
              <tr class="text-left text-stone-500 border-b border-stone-100">
                <th class="py-2.5 pr-3 font-semibold">Barang</th>
                <th class="py-2.5 px-3 font-semibold">Lokasi</th>
                <th class="py-2.5 px-3 font-semibold text-right">Stok</th>
                <th class="py-2.5 px-3 font-semibold text-right">Minimum</th>
                <th class="py-2.5 pl-3 font-semibold">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="b in kritis" :key="b.id" class="border-b border-stone-50 last:border-0">
                <td class="py-2.5 pr-3">
                  <span class="font-semibold text-cam-ink">{{ b.nama }}</span>
                  <span class="block text-[11px] text-stone-400">{{ b.kode }}</span>
                </td>
                <td class="py-2.5 px-3 text-stone-500">{{ b.lokasi ?? '—' }}</td>
                <td class="py-2.5 px-3 text-right font-bold tabular-nums">
                  {{ angka(b.stok) }} <span class="text-stone-400 font-normal">{{ b.satuan }}</span>
                </td>
                <td class="py-2.5 px-3 text-right text-stone-500 tabular-nums">{{ angka(b.stokMin) }}</td>
                <td class="py-2.5 pl-3">
                  <span class="eq-lencana-kat" :class="`k-${b.status.nada}`">{{ b.status.nama }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="eq-panel">
        <div class="eq-panel-kepala">
          <h3>Mendekati Kedaluwarsa</h3>
          <span class="text-[11px] text-stone-400">{{ ambang }} hari</span>
        </div>

        <div v-if="!kedaluwarsa.length" class="eq-kosong">
          <strong>Tidak ada yang mendekati kedaluwarsa</strong>
          <p>Batch yang tersimpan masih dalam masa berlaku.</p>
        </div>

        <ul v-else class="space-y-2.5">
          <li v-for="(k, i) in kedaluwarsa" :key="i" class="flex items-start gap-3">
            <span class="w-11 h-11 rounded-xl grid place-items-center shrink-0 text-[11px] font-bold"
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

    <div class="grid gap-5 lg:grid-cols-3">

      <section class="eq-panel">
        <div class="eq-panel-kepala"><h3>Menurut Kategori</h3></div>

        <div class="space-y-3">
          <Link v-for="k in kategori" :key="k.kode" :href="k.url" class="block group">
            <div class="flex items-baseline justify-between gap-3">
              <span class="text-[12.5px] font-semibold text-cam-ink group-hover:underline">{{ k.nama }}</span>
              <span class="text-[12.5px] font-bold tabular-nums"
                    style="color:var(--eq-aksen,#F57C00)">{{ k.jumlah }}</span>
            </div>
            <div class="eq-bilah mt-1.5"><i :style="{ width: lebar(k.jumlah) + '%' }"></i></div>
          </Link>
        </div>

        <div v-if="r.tanpa_msds > 0" class="mt-4 rounded-xl bg-amber-50 border border-amber-100 px-3.5 py-3">
          <p class="text-[12px] font-bold text-amber-800">
            {{ r.tanpa_msds }} bahan B3 belum berlembar data
          </p>
          <p class="text-[11.5px] text-amber-700 mt-0.5">
            Tanpa LDK, petugas tidak punya rujukan penanganan tumpahan dan pertolongan pertama.
          </p>
        </div>
      </section>

      <section class="eq-panel lg:col-span-2">
        <div class="eq-panel-kepala">
          <h3>Mutasi Terakhir</h3>
          <Link :href="tautan.mutasi" class="eq-panel-lihat">Lihat Semua →</Link>
        </div>

        <div v-if="!terakhir.length" class="eq-kosong">
          <strong>Belum ada mutasi tercatat</strong>
          <p>Penerimaan dan pengeluaran akan muncul di sini.</p>
        </div>

        <ul v-else class="space-y-1">
          <BarisMutasi v-for="m in terakhir" :key="m.id" :m="m" />
        </ul>
      </section>
    </div>

  </div>
</template>
