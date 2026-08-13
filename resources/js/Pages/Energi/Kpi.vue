<script setup lang="ts">
/**
 * Energy KPI — enam angka penilai kinerja energi.
 */
import { Head, Link } from '@inertiajs/vue3';
import KartuKpi from '../../Components/KartuKpi.vue';
import KepalaEnergi from '../../Components/KepalaEnergi.vue';
import { angka, rupiah } from '../../energi';
import type { HalamanEnergiKpi } from '../../types';

const props = defineProps<HalamanEnergiKpi>();
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <KepalaEnergi judul="Energy KPI"
        ket="Enam angka yang dipakai menilai kinerja energi. Semuanya diturunkan dari catatan
             harian yang sama, jadi tidak ada angka yang perlu dicocokkan belakangan."
        :dari="dari" :sampai="sampai" :rute="tautan.kpi" />

    <section v-if="baseline" class="brand-gradient rounded-2xl p-6 text-white relative overflow-hidden">
      <div class="absolute -right-10 -bottom-16 w-56 h-56 rounded-full"
           style="background:radial-gradient(circle,rgba(42,157,143,.4),transparent 70%)"></div>

      <div class="relative flex flex-wrap items-end justify-between gap-5">
        <div>
          <div class="text-[10.5px] font-bold uppercase tracking-[.18em] text-white/55">
            Kemajuan Menuju Sasaran {{ baseline.tahun }}
          </div>
          <div class="stat stat-xl text-white mt-2">{{ angka(baseline.kemajuan * 100, 1) }}<span class="stat-unit">%</span></div>
          <p class="text-[11.5px] text-white/60 mt-2 max-w-md leading-relaxed">
            Diukur pada rentang baseline→target, bukan terhadap baseline saja: yang ingin
            diketahui adalah seberapa jauh perjalanan yang sudah ditempuh dari seluruh jarak
            yang direncanakan.
          </p>
        </div>

        <div class="flex gap-6 text-right">
          <div>
            <div class="num text-[17px] font-bold text-white">{{ angka(baseline.baselineGjTon, 3) }}</div>
            <div class="text-[10px] text-white/50 mt-1">Baseline</div>
          </div>
          <div>
            <div class="num text-[17px] font-bold text-white">{{ angka(r.intensitas, 3) }}</div>
            <div class="text-[10px] text-white/50 mt-1">Sekarang</div>
          </div>
          <div>
            <div class="num text-[17px] font-bold text-white">{{ angka(baseline.targetGjTon, 3) }}</div>
            <div class="text-[10px] text-white/50 mt-1">Target</div>
          </div>
        </div>
      </div>

      <div class="relative mt-5 h-2.5 rounded-full bg-white/15 overflow-hidden">
        <div class="h-full rounded-full bg-cam-sage transition-all duration-700" :style="{ width: baseline.kemajuan * 100 + '%' }"></div>
      </div>
    </section>
    <div v-else class="rounded-2xl bg-cam-sand/60 border border-cam-sand px-5 py-4 text-[12.5px] text-cam-ink leading-relaxed">
      Baseline belum ditetapkan, jadi capaian belum dapat dinilai — angka intensitas tanpa
      pembandingnya hanya menjadi bilangan.
      <Link :href="tautan.baseline" class="font-bold text-cam-lime-deep hover:underline">Tetapkan baseline →</Link>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <KartuKpi label="Energy Intensity" :nilai="angka(r.intensitas, 3)" satuan="GJ/ton"
                :ket="`Total ${angka(r.gj, 1)} GJ untuk ${angka(r.ton)} ton`" />

      <KartuKpi label="Penurunan vs Baseline" :nilai="angka(baseline?.penurunanTercapai ?? 0, 1) + '%'"
                :warna="(baseline?.penurunanTercapai ?? 0) >= 0 ? '#F57C00' : '#E2663A'"
                :ket="baseline ? `Terhadap baseline ${baseline.tahun}` : 'Baseline belum ditetapkan'" />

      <KartuKpi label="Fuel Ratio" :nilai="angka(r.l_ton, 3)" satuan="L/ton" :ket="`${angka(r.liter)} L solar terpakai`" warna="#FF9800" />

      <KartuKpi label="Electricity Ratio" :nilai="angka(r.kwh_ton, 3)" satuan="kWh/ton" :ket="`${angka(r.kwh)} kWh terpakai`" warna="#22312F" />

      <KartuKpi label="Carbon Intensity" :nilai="r.ton > 0 ? angka(r.tco2e / r.ton, 4) : '0'" satuan="tCO₂e/ton"
                :ket="`Total ${angka(r.tco2e, 1)} tCO₂e`" warna="#22312F" />

      <KartuKpi label="Energy Cost Ratio" :nilai="r.ton > 0 ? rupiah(r.rupiah / r.ton, 0) : 'Rp 0'" satuan="/ton"
                :ket="`Total ${rupiah(r.rupiah, 2)}`" warna="#E2663A" />
    </div>

    <section class="kartu-lux rounded-2xl p-6">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h3 class="font-display text-[16px] font-black text-cam-ink">Penghematan yang Sudah Berjalan</h3>
          <p class="text-[11.5px] text-stone-500 mt-1">
            Hanya peluang berstatus berjalan atau selesai yang dihitung — usulan yang belum
            dikerjakan bukan penghematan.
          </p>
        </div>
        <Link :href="tautan.hemat" class="shrink-0 text-[11.5px] font-bold text-cam-lime-deep hover:underline">Kelola →</Link>
      </div>

      <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-5">
        <KartuKpi label="Program Berjalan" :nilai="hemat.jumlah" satuan="program" ket="Berstatus berjalan atau selesai" />
        <KartuKpi label="Energi Dihemat" :nilai="angka(hemat.gj, 1)" satuan="GJ/bulan" warna="#FF9800" ket="Perkiraan per bulan" />
        <KartuKpi label="Emisi Dihindari" :nilai="angka(hemat.tco2e, 2)" satuan="tCO₂e/bulan" warna="#22312F" ket="Perkiraan per bulan" />
        <KartuKpi label="Biaya Dihemat" :nilai="rupiah(hemat.rupiah, 2)" satuan="/bulan" warna="#E2663A" ket="Perkiraan per bulan" />
      </div>
    </section>

  </div>
</template>
