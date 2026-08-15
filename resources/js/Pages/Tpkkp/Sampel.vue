<script setup lang="ts">
/**
 * PTPKKP — Rencana Sampel.
 *
 * Seluruh metode datang sekaligus dari server, jadi berpindah tab tidak
 * menyentuh jaringan. Versi Blade-nya memuat ulang halaman penuh untuk
 * tiap tab hanya demi menampilkan tabel yang isinya tetap dan tidak
 * pernah berubah antar kunjungan.
 *
 * Nol dibedakan dari "tidak dialokasikan": nol berarti perusahaan itu
 * memang tidak kebagian responden pada metode ini, dan menuliskannya
 * sebagai 0 membuatnya tampak seperti kekurangan yang harus dikejar.
 */
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import type { HalamanSampel } from '../../types';

const props = defineProps<HalamanSampel>();

const aktif = ref(props.metodeAwal);

const blok = computed(() => props.metode.find((m) => m.kode === aktif.value) ?? props.metode[0]);

const angka = (n: number | null) => (n === null ? '—' : n.toLocaleString('id-ID'));

const desimal = (n: number | null) =>
  n === null ? '—' : n.toLocaleString('id-ID', { maximumFractionDigits: 2 });
</script>

<template>
  <Head title="PTPKKP — Rencana Sampel" />

  <div class="max-w-6xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-1">Rencana Sampel per Perusahaan</h3>
      <p class="text-[11.5px] text-stone-500">
        Populasi acuan: Management {{ angka(populasi.management) }} ·
        Employee {{ angka(populasi.employee) }} ·
        total {{ angka(populasi.total) }} orang.
      </p>

      <div class="flex flex-wrap gap-2 mt-3">
        <button v-for="m in metode" :key="m.kode" type="button" @click="aktif = m.kode"
                class="text-[12px] font-semibold px-3 py-1.5 rounded-lg border transition"
                :class="m.kode === aktif
                  ? 'bg-cam-ink text-white border-cam-ink'
                  : 'bg-white text-stone-600 border-stone-200 hover:border-stone-400'">
          {{ m.kode }}
        </button>
      </div>
    </div>

    <div v-if="blok" class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-stone-100">
        <h3 class="text-[13px] font-bold text-cam-ink">Alokasi metode {{ blok.kode }}</h3>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-[12px] min-w-[640px]">
          <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
            <tr>
              <th class="text-left px-5 py-2.5 font-bold">Perusahaan</th>
              <th class="text-right px-3 py-2.5 font-bold">Management</th>
              <th class="text-right px-3 py-2.5 font-bold">Employee</th>
              <th class="text-right px-5 py-2.5 font-bold">Jumlah</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in blok.baris" :key="r.perusahaan" class="border-b border-stone-50">
              <td class="px-5 py-2.5">{{ r.perusahaan }}</td>
              <td class="px-3 py-2.5 text-right num">{{ angka(r.mgm) }}</td>
              <td class="px-3 py-2.5 text-right num">{{ angka(r.emp) }}</td>
              <td class="px-5 py-2.5 text-right num font-semibold">{{ angka(r.jumlah) }}</td>
            </tr>
            <tr class="bg-stone-50 font-bold">
              <td class="px-5 py-2.5">Total rencana</td>
              <td class="px-3 py-2.5 text-right num">{{ angka(blok.total.mgm) }}</td>
              <td class="px-3 py-2.5 text-right num">{{ angka(blok.total.emp) }}</td>
              <td class="px-5 py-2.5 text-right num">{{ angka(blok.total.jumlah) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="px-5 py-3 text-[11px] text-stone-400 border-t border-stone-100 space-y-1">
        <p>
          Angka rencana dibulatkan ke atas per kolom dari alokasi proporsional instrumen.
          Hitungan Slovin ada di halaman Kalkulator Slovin.
        </p>
        <p v-if="blok.acuan">
          Alokasi proporsional instrumen sebelum dibulatkan:
          Management {{ desimal(blok.acuan.mgm) }} ·
          Employee {{ desimal(blok.acuan.emp) }} ·
          total {{ desimal(blok.acuan.jumlah) }} orang. Selisihnya terhadap baris di atas
          muncul karena tiap perusahaan dibulatkan ke atas — tidak ada pecahan orang yang
          bisa didatangi.
        </p>
      </div>
    </div>
  </div>
</template>
