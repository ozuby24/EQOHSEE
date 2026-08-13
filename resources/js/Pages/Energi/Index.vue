<script setup lang="ts">
/**
 * Energy Performance Center — dashboard.
 */
import { Head, Link } from '@inertiajs/vue3';
import GrafikGaris from '../../Components/GrafikGaris.vue';
import KartuKpi from '../../Components/KartuKpi.vue';
import RentangTanggal from '../../Components/RentangTanggal.vue';
import { angka, ringkas, rupiah } from '../../energi';
import type { HalamanEnergiIndex } from '../../types';

defineProps<HalamanEnergiIndex>();
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-6xl mx-auto space-y-5">

    <section class="brand-gradient rounded-2xl p-6 md:p-7 text-white relative overflow-hidden">
      <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full"
           style="background:radial-gradient(circle,rgba(42,157,143,.45),transparent 70%)"></div>

      <div class="relative flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <div class="text-[10.5px] font-bold uppercase tracking-[.18em] text-white/55">Website #6</div>
          <h2 class="font-display text-[23px] md:text-[27px] font-black leading-tight mt-1">
            Energy Performance Center
          </h2>
          <p class="text-[12.5px] text-white/70 mt-2 leading-relaxed max-w-xl">
            Seluruh sumber energi dibawa ke satu satuan sebelum dijumlahkan. Tanpa itu liter solar
            dan kilowatt-jam tidak dapat dibandingkan, apalagi ditotal — dan intensitas energi
            kehilangan artinya.
          </p>
        </div>

        <div class="glass-panel rounded-2xl px-5 py-4 text-right shrink-0">
          <div class="text-[10px] font-bold uppercase tracking-wide text-white/55">Current Energy Intensity</div>
          <div class="stat stat-xl text-white mt-1.5">
            {{ angka(r.intensitas, 2) }}<span class="stat-unit">GJ/ton</span>
          </div>
          <div v-if="baseline" class="text-[11.5px] font-bold mt-2"
               :class="baseline.penurunanTercapai >= 0 ? 'text-cam-sage' : 'text-cam-coral'">
            {{ baseline.penurunanTercapai >= 0 ? '▼' : '▲' }}
            {{ angka(Math.abs(baseline.penurunanTercapai), 1) }}% vs Baseline {{ baseline.tahun }}
          </div>
          <div v-else class="text-[11.5px] text-white/50 mt-2">Baseline belum ditetapkan</div>
        </div>
      </div>

      <div class="relative mt-5 pt-5 border-t border-white/10">
        <RentangTanggal :dari="dari" :sampai="sampai" rute="/energi" gelap />
      </div>
    </section>

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
      <KartuKpi label="Fuel Consumption" :nilai="ringkas(r.liter, 2)" satuan="L"
                :ket="`Alat ${angka(r.liter_alat)} L · genset ${angka(r.liter_genset)} L`" />
      <KartuKpi label="Energy Cost" :nilai="rupiah(r.rupiah, 2)"
                ket="Solar, listrik, dan gas pada rentang ini" warna="#E2663A" />
      <KartuKpi label="Emission" :nilai="ringkas(r.tco2e, 1)" satuan="tCO₂e"
                :ket="r.ton > 0 ? `${angka(r.tco2e / r.ton, 4)} tCO₂e per ton produksi` : 'Belum ada produksi tercatat'"
                warna="#22312F" />
      <KartuKpi label="Total Energy" :nilai="ringkas(r.gj, 1)" satuan="GJ"
                :ket="`${angka(r.ton)} ton produksi · ${r.hari} hari`" warna="#FF9800" />
    </div>

    <section class="kartu-lux rounded-2xl p-6">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h3 class="font-display text-[16px] font-black text-cam-ink">Energy Trend</h3>
          <p class="text-[11.5px] text-stone-500 mt-1">
            Intensitas harian, gigajoule per ton produksi.
            <span v-if="baseline" class="text-cam-coral font-semibold">
              Garis putus-putus adalah sasaran tahun {{ baseline.tahun }}.
            </span>
          </p>
        </div>
        <div class="text-right">
          <div class="num text-[19px] font-bold text-cam-lime-deep">{{ tren.length }}</div>
          <div class="text-[10.5px] text-stone-400">hari tercatat</div>
        </div>
      </div>

      <div class="mt-5">
        <GrafikGaris :titik="tren.map((t) => t.intensitas ?? 0)" :target="baseline?.targetGjTon ?? null"
                     :tinggi="170" satuan="GJ/ton" :desimal="3" />
      </div>

      <div v-if="tren.length" class="flex justify-between text-[10.5px] text-stone-400 mt-2">
        <span>{{ tren[0].tanggal }}</span>
        <span>{{ tren[tren.length - 1].tanggal }}</span>
      </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
      <section class="kartu-lux rounded-2xl p-6">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Fuel &amp; Energy Mix</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">Porsi tiap sumber setelah disamakan ke gigajoule.</p>

        <div class="space-y-3.5 mt-5">
          <div v-for="(x, nama) in r.rincian" :key="nama">
            <div class="flex items-baseline justify-between gap-3">
              <span class="text-[12.5px] font-semibold text-cam-ink">{{ nama }}</span>
              <span class="num text-[12px] text-stone-500">
                {{ angka(x.jumlah) }} {{ x.satuan }}
                <span class="text-stone-300 mx-1">·</span>
                <span class="font-bold text-cam-ink">
                  {{ angka(r.gj > 0 ? (x.gj / r.gj) * 100 : 0, 1) }}%
                </span>
              </span>
            </div>
            <div class="mt-1.5 h-2 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full lime-gradient transition-all duration-700"
                   :style="{ width: (r.gj > 0 ? (x.gj / r.gj) * 100 : 0) + '%' }"></div>
            </div>
            <div class="text-[10.5px] text-stone-400 mt-1">
              {{ angka(x.gj, 1) }} GJ · {{ angka(x.tco2e, 2) }} tCO₂e · {{ rupiah(x.rupiah, 1) }}
            </div>
          </div>
        </div>
      </section>

      <section class="kartu-lux rounded-2xl p-6">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h3 class="font-display text-[16px] font-black text-cam-ink">Equipment Ranking</h3>
            <p class="text-[11.5px] text-stone-500 mt-1">Liter per jam operasi, dibanding rata-rata kategorinya.</p>
          </div>
          <Link :href="tautan.equipment" class="shrink-0 text-[11.5px] font-bold text-cam-lime-deep hover:underline">
            Semua unit →
          </Link>
        </div>

        <div v-if="teratas.length" class="space-y-2.5 mt-5">
          <Link v-for="b in teratas" :key="b.id" :href="b.url"
                class="flex items-center gap-3 rounded-xl border border-stone-100 px-3.5 py-2.5
                       hover:border-cam-lime/40 hover:bg-cam-lime-soft/40 transition">
            <span class="w-1.5 h-9 rounded-full shrink-0" :style="{ background: b.status.warna }"></span>
            <span class="min-w-0 flex-1">
              <span class="block text-[12.5px] font-bold text-cam-ink truncate">{{ b.kode }} — {{ b.nama }}</span>
              <span class="block text-[10.5px] text-stone-400">{{ b.kategori }} · {{ b.status.label }}</span>
            </span>
            <span class="text-right shrink-0">
              <span class="num block text-[14px] font-bold" :style="{ color: b.status.warna }">{{ angka(b.lHm, 1) }}</span>
              <span class="block text-[10px] text-stone-400">L/HM</span>
            </span>
          </Link>
        </div>
        <p v-else class="text-[12px] text-stone-400 mt-6">Belum ada catatan bahan bakar pada rentang ini.</p>
      </section>
    </div>

    <section class="kartu-lux rounded-2xl p-6">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h3 class="font-display text-[16px] font-black text-cam-ink">Saving Opportunities</h3>
          <p class="text-[11.5px] text-stone-500 mt-1">Yang sudah disetujui atau sedang berjalan.</p>
        </div>
        <Link :href="tautan.hemat" class="shrink-0 text-[11.5px] font-bold text-cam-lime-deep hover:underline">Kelola →</Link>
      </div>

      <div v-if="peluang.length" class="grid gap-3 sm:grid-cols-2 mt-5">
        <div v-for="(o, i) in peluang" :key="i" class="rounded-xl border border-stone-100 p-4">
          <div class="flex items-start justify-between gap-2">
            <span class="text-[12.5px] font-bold text-cam-ink leading-snug">{{ o.judul }}</span>
            <span class="shrink-0 text-[9.5px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-lg
                         bg-cam-lime-soft text-cam-lime-deep">{{ o.status }}</span>
          </div>
          <div class="text-[11px] text-stone-400 mt-2">
            {{ rupiah(o.rupiah, 1) }}/bulan · {{ angka(o.tco2e, 2) }} tCO₂e/bulan
          </div>
        </div>
      </div>
      <p v-else class="text-[12px] text-stone-400 mt-6">Belum ada peluang yang disetujui atau berjalan.</p>
    </section>

  </div>
</template>
