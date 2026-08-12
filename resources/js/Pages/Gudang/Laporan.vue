<script setup lang="ts">
/**
 * Gudang — laporan persediaan per periode.
 *
 * Tiap baris berlaku awal + masuk − keluar + penyesuaian = akhir.
 * Penyesuaian dipisah sebagai kolom sendiri supaya barisnya tetap dapat
 * dijumlahkan pembaca — angka yang tidak berjumlah membuat seluruh
 * laporan dicurigai.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { angka, bertanda } from '../../angka';
import type { HalamanLaporanGudang } from '../../types';

const props = defineProps<HalamanLaporanGudang>();

const saring = useForm({ dari: props.dari, sampai: props.sampai });

function tampilkan() {
  saring.get(props.tautan.laporan, { preserveState: true, preserveScroll: true, replace: true });
}

const cetak = () => window.print();

const isian = 'rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] ring-focus transition';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-3
                 flex flex-wrap items-end gap-2.5 cetak-sembunyi"
          @submit.prevent="tampilkan">
      <div>
        <label class="block text-[11.5px] font-semibold text-cam-ink mb-1">Dari</label>
        <input v-model="saring.dari" type="date" :class="isian">
      </div>
      <div>
        <label class="block text-[11.5px] font-semibold text-cam-ink mb-1">Sampai</label>
        <input v-model="saring.sampai" type="date" :class="isian">
      </div>
      <button type="submit" class="eq-btn-utama" style="flex:none;padding:10px 20px">Tampilkan</button>
      <button type="button" class="px-4 py-2.5 rounded-xl border border-stone-200 text-[12.5px]
                                   font-semibold text-stone-600 hover:bg-stone-50 transition"
              @click="cetak">Cetak</button>
    </form>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">

      <div class="px-6 py-5 border-b border-stone-100">
        <h3 class="text-[16px] font-bold text-cam-ink">Laporan Persediaan</h3>
        <p class="text-[12.5px] text-stone-500 mt-1">
          Periode {{ label.dari }} – {{ label.sampai }} · {{ baris.length }} barang bermutasi atau bersaldo
        </p>
      </div>

      <div v-if="!baris.length" class="px-6 py-12 text-center">
        <p class="text-[13.5px] font-bold text-cam-ink">Tidak ada mutasi pada periode ini</p>
        <p class="text-[12.5px] text-stone-500 mt-1">Pilih rentang tanggal yang lain.</p>
      </div>

      <template v-else>
        <div class="overflow-x-auto">
          <table class="w-full text-[12.5px]">
            <thead>
              <tr class="text-left text-stone-500 bg-stone-50 border-b border-stone-100">
                <th class="py-3 px-4 font-semibold">Barang</th>
                <th class="py-3 px-3 font-semibold">Kategori</th>
                <th class="py-3 px-3 font-semibold text-right">Saldo Awal</th>
                <th class="py-3 px-3 font-semibold text-right">Masuk</th>
                <th class="py-3 px-3 font-semibold text-right">Keluar</th>
                <th class="py-3 px-3 font-semibold text-right">Penyesuaian</th>
                <th class="py-3 px-4 font-semibold text-right">Saldo Akhir</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(x, i) in baris" :key="i" class="border-b border-stone-50 last:border-0">
                <td class="py-2.5 px-4">
                  <span class="font-semibold text-cam-ink">{{ x.nama }}</span>
                  <span class="block text-[11px] text-stone-400">{{ x.kode }} · {{ x.satuan }}</span>
                </td>
                <td class="py-2.5 px-3">
                  <span class="eq-lencana-kat" :class="`k-${x.nadaKategori}`">{{ x.namaKategori }}</span>
                </td>
                <td class="py-2.5 px-3 text-right tabular-nums text-stone-600">{{ angka(x.awal) }}</td>
                <td class="py-2.5 px-3 text-right tabular-nums text-[#4A8E2C] font-semibold">
                  {{ x.masuk > 0 ? '+' + angka(x.masuk) : '—' }}
                </td>
                <td class="py-2.5 px-3 text-right tabular-nums text-[#C03A3A] font-semibold">
                  {{ x.keluar > 0 ? '−' + angka(x.keluar) : '—' }}
                </td>
                <td class="py-2.5 px-3 text-right tabular-nums">
                  <span v-if="x.penyesuaian === 0" class="text-stone-300">—</span>
                  <span v-else class="font-semibold" title="Selisih hasil stok opname"
                        :class="x.penyesuaian > 0 ? 'text-[#4A8E2C]' : 'text-[#C03A3A]'">
                    {{ bertanda(x.penyesuaian) }}
                  </span>
                </td>
                <td class="py-2.5 px-4 text-right tabular-nums font-bold text-cam-ink">{{ angka(x.akhir) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="px-6 py-4 bg-stone-50 border-t border-stone-100">
          <p class="text-[11.5px] text-stone-500 leading-relaxed">
            Saldo awal dan akhir dihitung dengan memutar ulang seluruh mutasi sampai tanggal
            bersangkutan, bukan dengan mengurangkan yang satu dari yang lain — sehingga periode
            yang memuat stok opname tetap benar. Kolom <b>penyesuaian</b> berisi selisih hasil
            opname; tiap baris berlaku
            <span class="whitespace-nowrap">awal + masuk − keluar + penyesuaian = akhir</span>.
          </p>
        </div>
      </template>
    </div>

  </div>
</template>

<style>
@media print {
  .cetak-sembunyi, #eqSidebar, .eq-topbar, #eqOverlay { display: none !important; }
  main { padding: 0 !important; }
  .shadow-card { box-shadow: none !important; }
  table { font-size: 10.5px; }
}
</style>
