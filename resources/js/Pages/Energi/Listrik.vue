<script setup lang="ts">
/**
 * Electricity Management — area dan sumber.
 */
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import KartuKpi from '../../Components/KartuKpi.vue';
import KepalaEnergi from '../../Components/KepalaEnergi.vue';
import { angka, rupiah } from '../../energi';
import type { HalamanEnergiListrik } from '../../types';

const props = defineProps<HalamanEnergiListrik>();

const totalAman = computed(() => Math.max(1e-9, props.total.kwh));
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <KepalaEnergi judul="Electricity Management"
        ket="Listrik dipisah menurut area dan sumbernya. Genset dan PLN sengaja tidak dijumlahkan
             begitu saja: biaya dan emisi tiap kilowatt-jamnya berbeda, dan genset masih menenggak
             solar dari tangki yang sama dengan alat berat."
        :dari="dari" :sampai="sampai" :rute="tautan.listrik">

      <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
        <div>
          <div class="stat stat-sm" style="color:#F57C00">{{ angka(total.kwh) }}<span class="stat-unit">kWh</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Total Listrik</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#FF9800">{{ angka(total.gj, 1) }}<span class="stat-unit">GJ</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Setara Energi</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#22312F">{{ angka(total.tco2e, 2) }}<span class="stat-unit">tCO₂e</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Emisi Listrik</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#E2663A">{{ rupiah(total.rupiah, 2) }}</div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Biaya Listrik</div>
        </div>
      </div>
    </KepalaEnergi>

    <div class="grid gap-4 lg:grid-cols-2">
      <section class="kartu-lux rounded-2xl p-6">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Sumber Daya</h3>

        <div class="space-y-4 mt-5">
          <div v-for="[nama, kwh, warna] in [['PLN', pln.kwh, '#F57C00'], ['Genset', genset.kwh, '#D9993A']] as const" :key="nama">
            <div class="flex items-baseline justify-between gap-3">
              <span class="text-[12.5px] font-bold text-cam-ink">{{ nama }}</span>
              <span class="num text-[12px] text-stone-500">
                {{ angka(kwh) }} kWh
                <span class="text-stone-300 mx-1">·</span>
                <span class="font-bold text-cam-ink">{{ angka((kwh / totalAman) * 100, 1) }}%</span>
              </span>
            </div>
            <div class="mt-1.5 h-2 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full transition-all duration-700"
                   :style="{ width: (kwh / totalAman) * 100 + '%', background: warna }"></div>
            </div>
          </div>
        </div>

        <div class="rounded-xl bg-cam-sand/50 border border-cam-sand px-4 py-3 mt-5 text-[11.5px] leading-relaxed text-cam-ink">
          Genset menghasilkan <strong class="num">{{ angka(genset.efisiensi, 2) }}</strong> kWh tiap liter solar
          selama <span class="num">{{ angka(genset.jam, 1) }}</span> jam operasi.
          Genset sehat umumnya berada di sekitar 3–4 kWh per liter; angka yang jauh di bawahnya
          menandakan beban terlalu ringan atau mesin yang perlu diperiksa.
        </div>
      </section>

      <section class="kartu-lux rounded-2xl p-6">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Angka Kunci</h3>
        <div class="grid gap-4 grid-cols-2 mt-5">
          <KartuKpi label="kWh per Ton" :nilai="angka(total.perTon, 3)" satuan="kWh/ton" ket="Listrik yang dipakai untuk tiap ton produksi" />
          <KartuKpi label="Solar Genset" :nilai="angka(genset.liter)" satuan="L" ket="Sudah ikut terhitung pada total solar" warna="#D9993A" />
          <KartuKpi label="Efisiensi Genset" :nilai="angka(genset.efisiensi, 2)" satuan="kWh/L" ket="Semakin tinggi semakin baik" warna="#FF9800" />
          <KartuKpi label="Area Tercatat" :nilai="perArea.length" satuan="area" ket="Area tanpa catatan tidak ditampilkan" warna="#22312F" />
        </div>
      </section>
    </div>

    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Pemakaian per Area</h3>
      <p class="text-[11.5px] text-stone-500 mt-1 leading-relaxed">
        Faktor beban membandingkan pemakaian rata-rata terhadap beban puncaknya. Faktor rendah
        berarti kapasitas terpasang jauh melebihi pemakaian biasanya — langganan terbayar tanpa
        terpakai.
      </p>

      <div v-if="perArea.length" class="overflow-x-auto mt-4 -mx-1">
        <table class="w-full text-[12.5px] min-w-[600px]">
          <thead>
            <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
              <th class="text-left py-2.5">Area</th>
              <th class="num py-2.5">kWh</th>
              <th class="num py-2.5">GJ</th>
              <th class="num py-2.5">Puncak (kW)</th>
              <th class="num py-2.5">Jam</th>
              <th class="num py-2.5">Faktor Beban</th>
              <th class="num py-2.5">Biaya</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(a, i) in perArea" :key="i" class="hairline">
              <td class="py-2.5 font-semibold text-cam-ink">{{ a.nama }}</td>
              <td class="num py-2.5">{{ angka(a.kwh) }}</td>
              <td class="num py-2.5">{{ angka(a.gj, 2) }}</td>
              <td class="num py-2.5">{{ angka(a.puncak, 1) }}</td>
              <td class="num py-2.5">{{ angka(a.jam, 1) }}</td>
              <td class="num py-2.5 font-bold" :class="a.faktor > 0 && a.faktor < 40 ? 'text-cam-coral' : 'text-cam-lime-deep'">
                {{ angka(a.faktor, 1) }}%
              </td>
              <td class="num py-2.5">Rp {{ angka(a.rupiah) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="text-[12px] text-stone-400 mt-6">Belum ada catatan listrik pada rentang ini.</p>
    </section>

  </div>
</template>
