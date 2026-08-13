<script setup lang="ts">
/**
 * Carbon & Emission.
 */
import { Head, Link } from '@inertiajs/vue3';
import KartuKpi from '../../Components/KartuKpi.vue';
import KepalaEnergi from '../../Components/KepalaEnergi.vue';
import { angka, rupiah } from '../../energi';
import type { HalamanEnergiKarbon } from '../../types';

const props = defineProps<HalamanEnergiKarbon>();
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <KepalaEnergi judul="Carbon &amp; Emission"
        ket="Emisi dihitung dari catatan energi yang sama, bukan dicatat terpisah. Itu menutup
             celah yang lazim: laporan energi dan laporan emisi yang tidak pernah cocok karena
             masing-masing punya sumber angkanya sendiri."
        :dari="dari" :sampai="sampai" :rute="tautan.karbon">

      <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
        <div>
          <div class="stat stat-sm" style="color:#22312F">{{ angka(r.tco2e, 2) }}<span class="stat-unit">tCO₂e</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Total Emisi</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#F57C00">{{ r.ton > 0 ? angka(r.tco2e / r.ton, 4) : '0' }}<span class="stat-unit">tCO₂e/ton</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Intensitas Karbon</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#E2663A">{{ angka(r.rincian['Solar']?.tco2e ?? 0, 2) }}<span class="stat-unit">tCO₂e</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Emisi Solar</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#FF9800">{{ angka(r.rincian['Listrik']?.tco2e ?? 0, 2) }}<span class="stat-unit">tCO₂e</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Emisi Listrik</div>
        </div>
      </div>
    </KepalaEnergi>

    <div class="grid gap-4 lg:grid-cols-2">
      <section class="kartu-lux rounded-2xl p-6">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Sumber Emisi</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">Porsi tiap sumber terhadap seluruh emisi rentang ini.</p>

        <div class="space-y-3.5 mt-5">
          <div v-for="(x, nama) in r.rincian" :key="nama">
            <div class="flex items-baseline justify-between gap-3">
              <span class="text-[12.5px] font-semibold text-cam-ink">{{ nama }}</span>
              <span class="num text-[12px] text-stone-500">
                {{ angka(x.tco2e, 2) }} tCO₂e
                <span class="text-stone-300 mx-1">·</span>
                <span class="font-bold text-cam-ink">{{ angka(r.tco2e > 0 ? (x.tco2e / r.tco2e) * 100 : 0, 1) }}%</span>
              </span>
            </div>
            <div class="mt-1.5 h-2 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full transition-all duration-700"
                   :style="{ width: (r.tco2e > 0 ? (x.tco2e / r.tco2e) * 100 : 0) + '%', background: '#22312F' }"></div>
            </div>
          </div>
        </div>

        <div class="rounded-xl bg-cam-sand/50 border border-cam-sand px-4 py-3 mt-5 text-[11.5px] leading-relaxed text-cam-ink">
          Faktor yang dipakai: <span class="num">{{ angka(faktor.literKgCo2, 2) }}</span> kg CO₂e tiap liter solar,
          <span class="num">{{ angka(faktor.kwhKgCo2, 4) }}</span> kg tiap kilowatt-jam listrik jaringan, dan
          <span class="num">{{ angka(faktor.m3KgCo2, 4) }}</span> kg tiap meter kubik gas. Faktor disimpan di satu
          tempat, jadi memperbaruinya langsung tercermin ke seluruh riwayat.
        </div>
      </section>

      <section class="kartu-lux rounded-2xl p-6">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Lingkup Emisi</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Pembakaran di lokasi masuk Lingkup 1; listrik jaringan yang dibeli masuk Lingkup 2.
          Solar genset tetap Lingkup 1 karena dibakar sendiri, meski keluarannya berupa listrik.
        </p>

        <div class="grid gap-4 grid-cols-2 mt-5">
          <KartuKpi label="Lingkup 1 — Langsung" :nilai="angka(lingkup.s1, 2)" satuan="tCO₂e"
                    :rasio="lingkup.porsiS1" :ket="`${angka(lingkup.porsiS1 * 100, 1)}% dari total`" warna="#E2663A" />
          <KartuKpi label="Lingkup 2 — Listrik" :nilai="angka(lingkup.s2, 2)" satuan="tCO₂e"
                    :rasio="lingkup.porsiS2" :ket="`${angka(lingkup.porsiS2 * 100, 1)}% dari total`" warna="#FF9800" />
        </div>

        <div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 px-4 py-3 mt-4 text-[11.5px] leading-relaxed text-cam-lime-deep">
          Emisi rentang ini setara dengan serapan tahunan sekitar
          <strong class="num">{{ angka(pohon) }}</strong> pohon tropis dewasa.
          Perbandingan ini untuk memberi ukuran, bukan untuk menggantikan penghitungan resmi.
        </div>
      </section>
    </div>

    <section class="kartu-lux rounded-2xl p-6">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h3 class="font-display text-[16px] font-black text-cam-ink">Emisi yang Dihindari</h3>
          <p class="text-[11.5px] text-stone-500 mt-1">Dari program penghematan yang benar-benar berjalan atau sudah selesai.</p>
        </div>
        <Link :href="tautan.hemat" class="shrink-0 text-[11.5px] font-bold text-cam-lime-deep hover:underline">Program →</Link>
      </div>

      <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-5">
        <KartuKpi label="Program Berjalan" :nilai="hemat.jumlah" satuan="program" />
        <KartuKpi label="Emisi Dihindari" :nilai="angka(hemat.tco2e, 2)" satuan="tCO₂e/bulan" warna="#22312F" />
        <KartuKpi label="Setara Setahun" :nilai="angka(hemat.tco2e * 12, 1)" satuan="tCO₂e/tahun" warna="#FF9800" ket="Bila laju bulanan bertahan" />
        <KartuKpi label="Nilai Penghematan" :nilai="rupiah(hemat.rupiah * 12, 2)" satuan="/tahun" warna="#E2663A" />
      </div>
    </section>

  </div>
</template>
