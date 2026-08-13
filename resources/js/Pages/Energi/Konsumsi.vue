<script setup lang="ts">
/**
 * Energy Consumption — sandingan seluruh sumber.
 */
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import GrafikGaris from '../../Components/GrafikGaris.vue';
import KartuKpi from '../../Components/KartuKpi.vue';
import KepalaEnergi from '../../Components/KepalaEnergi.vue';
import { angka, ringkas, rupiah } from '../../energi';
import type { HalamanEnergiKonsumsi } from '../../types';

const props = defineProps<HalamanEnergiKonsumsi>();

const solarHarian = computed(() => props.tren.map((t) => t.liter ?? 0));
const listrikHarian = computed(() => props.tren.map((t) => t.kwh ?? 0));

const rataSolar = computed(() =>
  solarHarian.value.length ? solarHarian.value.reduce((a, b) => a + b, 0) / solarHarian.value.length : null);
const rataListrik = computed(() =>
  listrikHarian.value.length ? listrikHarian.value.reduce((a, b) => a + b, 0) / listrikHarian.value.length : null);
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <KepalaEnergi judul="Energy Consumption"
        ket="Seluruh sumber energi disandingkan setelah disamakan ke gigajoule. Angka mentahnya
             tetap ditampilkan pada satuan asalnya — liter, kilowatt-jam, meter kubik — karena
             itulah yang dicatat orang lapangan."
        :dari="dari" :sampai="sampai" :rute="tautan.konsumsi">

      <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
        <div>
          <div class="stat stat-sm" style="color:#F57C00">{{ angka(r.gj, 1) }}<span class="stat-unit">GJ</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Total Energi</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#FF9800">{{ angka(r.intensitas, 3) }}<span class="stat-unit">GJ/ton</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Intensitas</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#22312F">{{ angka(r.tco2e, 1) }}<span class="stat-unit">tCO₂e</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Emisi</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#E2663A">{{ rupiah(r.rupiah, 2) }}</div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Biaya</div>
        </div>
      </div>
    </KepalaEnergi>

    <div class="grid gap-4 lg:grid-cols-2">
      <section class="kartu-lux rounded-2xl p-6">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Solar Harian</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">Garis putus-putus adalah rata-rata harian pada rentang ini.</p>
        <div class="mt-5">
          <GrafikGaris :titik="solarHarian" warna="#F57C00" satuan="L" :desimal="0" :tinggi="140" :target="rataSolar" />
        </div>
      </section>
      <section class="kartu-lux rounded-2xl p-6">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Listrik Harian</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">Garis putus-putus adalah rata-rata harian pada rentang ini.</p>
        <div class="mt-5">
          <GrafikGaris :titik="listrikHarian" warna="#FF9800" satuan="kWh" :desimal="0" :tinggi="140" :target="rataListrik" />
        </div>
      </section>
    </div>

    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Rincian per Sumber</h3>

      <div class="overflow-x-auto mt-4 -mx-1">
        <table class="w-full text-[12.5px] min-w-[560px]">
          <thead>
            <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
              <th class="text-left py-2.5">Sumber</th>
              <th class="num py-2.5">Jumlah</th>
              <th class="num py-2.5">Gigajoule</th>
              <th class="num py-2.5">Porsi</th>
              <th class="num py-2.5">tCO₂e</th>
              <th class="num py-2.5">Biaya</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(x, nama) in r.rincian" :key="nama" class="hairline">
              <td class="py-2.5 font-semibold text-cam-ink">{{ nama }}</td>
              <td class="num py-2.5">{{ angka(x.jumlah, 1) }} <span class="text-stone-400">{{ x.satuan }}</span></td>
              <td class="num py-2.5">{{ angka(x.gj, 2) }}</td>
              <td class="num py-2.5 font-bold text-cam-lime-deep">
                {{ r.gj > 0 ? angka((x.gj / r.gj) * 100, 1) : '0,0' }}%
              </td>
              <td class="num py-2.5">{{ angka(x.tco2e, 2) }}</td>
              <td class="num py-2.5">Rp {{ angka(x.rupiah) }}</td>
            </tr>
            <tr class="font-bold text-cam-ink">
              <td class="py-3">Total</td>
              <td class="num py-3">—</td>
              <td class="num py-3">{{ angka(r.gj, 2) }}</td>
              <td class="num py-3">100%</td>
              <td class="num py-3">{{ angka(r.tco2e, 2) }}</td>
              <td class="num py-3">Rp {{ angka(r.rupiah) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Rasio terhadap Produksi</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">
        Pemakaian yang naik belum tentu buruk bila produksinya naik lebih cepat. Rasio inilah
        yang membedakan keduanya.
      </p>

      <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-5">
        <KartuKpi label="Liter per Ton" :nilai="angka(r.l_ton, 3)" satuan="L/ton" :ket="`${angka(r.ton)} ton produksi`" />
        <KartuKpi label="Liter per BCM" :nilai="angka(r.l_bcm, 3)" satuan="L/BCM" :ket="`${angka(r.bcm)} BCM`" warna="#FF9800" />
        <KartuKpi label="kWh per Ton" :nilai="angka(r.kwh_ton, 3)" satuan="kWh/ton" :ket="`${angka(r.kwh)} kWh terpakai`" warna="#22312F" />
        <KartuKpi label="Solar per Hari" :nilai="ringkas(r.liter_hari, 1)" satuan="L/hari"
                  :ket="`Rata-rata ${r.hari} hari`" warna="#E2663A" />
      </div>

      <div v-if="baseline" class="rounded-xl border px-4 py-3 mt-5 text-[12px] leading-relaxed"
           :class="baseline.penurunanTercapai >= 0 ? 'bg-cam-lime-soft border-cam-lime/25' : 'bg-cam-coral-soft border-cam-coral/25'">
        Intensitas rentang ini <strong class="num">{{ angka(r.intensitas, 3) }}</strong> GJ/ton, terhadap
        baseline {{ baseline.tahun }} sebesar <strong class="num">{{ angka(baseline.baselineGjTon, 3) }}</strong> —
        <strong>{{ baseline.penurunanTercapai >= 0 ? 'turun' : 'naik' }} {{ angka(Math.abs(baseline.penurunanTercapai), 1) }}%</strong>.
        Target tahun ini <span class="num">{{ angka(baseline.targetGjTon, 3) }}</span> GJ/ton.
      </div>
    </section>

  </div>
</template>
