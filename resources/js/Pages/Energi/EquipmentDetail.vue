<script setup lang="ts">
/**
 * Kinerja energi satu unit alat.
 */
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import GrafikGaris from '../../Components/GrafikGaris.vue';
import KartuKpi from '../../Components/KartuKpi.vue';
import RentangTanggal from '../../Components/RentangTanggal.vue';
import { angka, rupiah } from '../../energi';
import type { HalamanEnergiEquipmentDetail } from '../../types';

const props = defineProps<HalamanEnergiEquipmentDetail>();

const trenLiter = computed(() => props.trenLiter.map((t) => t.nilai ?? 0));
const rataLiter = computed(() => (trenLiter.value.length ? trenLiter.value.reduce((a, b) => a + b, 0) / trenLiter.value.length : null));
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <section class="kartu-lux rounded-2xl p-6">
      <Link :href="tautan.equipment" class="text-[11.5px] font-bold text-cam-lime-deep hover:underline">← Semua unit</Link>

      <div class="flex flex-wrap items-start justify-between gap-4 mt-3">
        <div class="min-w-0">
          <div class="flex items-center gap-2.5 flex-wrap">
            <h2 class="font-display text-[21px] font-black text-cam-ink leading-tight">{{ unit.kode }}</h2>
            <span class="text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-1 rounded-lg"
                  :style="{ background: status.warna }">{{ status.label }}</span>
          </div>
          <p class="text-[12.5px] text-stone-500 mt-1">
            {{ unit.nama }} · {{ unit.kategori }}
            <template v-if="unit.merek"> · {{ unit.merek }}</template>
            <template v-if="unit.dayaHp"> · {{ angka(unit.dayaHp) }} HP</template>
            <template v-if="unit.payloadTon"> · payload {{ angka(unit.payloadTon, 1) }} ton</template>
          </p>
        </div>

        <RentangTanggal :dari="dari" :sampai="sampai" :rute="tautan.diriSendiri" />
      </div>

      <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
        <div>
          <div class="stat stat-sm" :style="{ color: status.warna }">{{ angka(m.l_hm, 2) }}<span class="stat-unit">L/HM</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Liter per Jam</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#9AA3AE">{{ angka(acuan, 2) }}<span class="stat-unit">L/HM</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Acuan Kategori</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#F57C00">{{ angka(m.liter) }}<span class="stat-unit">L</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Total Solar</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#E2663A">{{ rupiah(m.rupiah, 2) }}</div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Biaya Solar</div>
        </div>
      </div>
    </section>

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
      <KartuKpi label="Jam Operasi" :nilai="angka(m.hm, 1)" satuan="HM"
                :ket="m.idle > 0 ? `${angka(m.idle, 1)} jam di antaranya idle` : 'Tidak ada catatan idle'" />
      <KartuKpi label="Porsi Idle" :nilai="angka(m.idle_persen, 1) + '%'"
                :warna="m.idle_persen > 25 ? '#E2663A' : '#F57C00'" :rasio="Math.min(1, m.idle_persen / 100)"
                :ket="m.idle_persen > 25 ? 'Di atas 25% — solar terbakar tanpa hasil' : 'Dalam batas wajar'" />
      <KartuKpi label="Liter per Ton" :nilai="angka(m.l_ton, 3)" satuan="L/ton" :ket="`${angka(m.ton)} ton terangkut`" warna="#FF9800" />
      <KartuKpi label="Liter per BCM" :nilai="angka(m.l_bcm, 3)" satuan="L/BCM" :ket="`${angka(m.bcm)} BCM`" warna="#22312F" />
    </div>

    <section class="kartu-lux rounded-2xl p-6">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h3 class="font-display text-[16px] font-black text-cam-ink">Pemakaian Harian</h3>
          <p class="text-[11.5px] text-stone-500 mt-1">
            Liter per hari.
            <span class="text-cam-coral font-semibold">Garis putus-putus adalah rata-rata unit ini sendiri.</span>
          </p>
        </div>
        <div class="text-right">
          <div class="num text-[19px] font-bold text-cam-lime-deep">{{ hariBeroperasi }}</div>
          <div class="text-[10.5px] text-stone-400">hari beroperasi</div>
        </div>
      </div>

      <div class="mt-5">
        <GrafikGaris :titik="trenLiter" :target="rataLiter" :tinggi="150" satuan="L" :desimal="0" />
      </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
      <section class="kartu-lux rounded-2xl p-6">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Dampak Energi</h3>
        <div class="grid gap-4 grid-cols-2 mt-5">
          <KartuKpi label="Energi" :nilai="angka(m.gj, 2)" satuan="GJ" ket="Solar disamakan ke gigajoule" />
          <KartuKpi label="Emisi" :nilai="angka(m.tco2e, 2)" satuan="tCO₂e" ket="2,68 kg CO₂e tiap liter" warna="#22312F" />
        </div>

        <div v-if="potensiHemat" class="rounded-xl bg-cam-coral-soft border border-cam-coral/25 px-4 py-3 mt-5 text-[11.5px] leading-relaxed text-cam-ink">
          Bila unit ini berjalan pada acuan kategorinya, pemakaiannya akan lebih hemat sekitar
          <strong class="num">{{ angka(potensiHemat.liter) }}</strong> liter pada rentang ini —
          setara <strong>{{ rupiah(potensiHemat.rupiah, 2) }}</strong> dan
          <strong class="num">{{ angka(potensiHemat.tco2e, 2) }}</strong> tCO₂e.
        </div>
      </section>

      <section class="kartu-lux rounded-2xl p-6">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Pola Kerja</h3>
        <div class="grid gap-4 grid-cols-2 mt-5">
          <KartuKpi label="Jarak Tempuh" :nilai="angka(m.jarak, 1)" satuan="km" ket="Hanya terisi untuk unit angkut" warna="#FF9800" />
          <KartuKpi label="Kecepatan Rata-rata" :nilai="angka(m.kecepatan, 1)" satuan="km/jam" ket="Jarak dibagi jam operasi" />
          <KartuKpi label="Cycle Time" :nilai="m.cycle ? angka(m.cycle, 2) : '—'" satuan="menit" ket="Rata-rata satu siklus" warna="#D9993A" />
          <KartuKpi label="Solar per Hari" :nilai="hariBeroperasi ? angka(m.liter / hariBeroperasi, 1) : '0'" satuan="L/hari"
                    ket="Rata-rata hari beroperasi" warna="#E2663A" />
        </div>
      </section>
    </div>

    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Catatan Harian</h3>

      <div v-if="log.length" class="overflow-x-auto mt-4 -mx-1">
        <table class="w-full text-[12.5px] min-w-[620px]">
          <thead>
            <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
              <th class="text-left py-2.5">Tanggal</th>
              <th class="num py-2.5">HM</th>
              <th class="num py-2.5">Liter</th>
              <th class="num py-2.5">L/HM</th>
              <th class="num py-2.5">Idle</th>
              <th class="num py-2.5">Ton</th>
              <th class="num py-2.5">BCM</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(x, i) in log" :key="i" class="hairline">
              <td class="py-2.5 font-semibold text-cam-ink">{{ x.tanggal }}</td>
              <td class="num py-2.5">{{ angka(x.hm, 1) }}</td>
              <td class="num py-2.5">{{ angka(x.liter, 1) }}</td>
              <td class="num py-2.5 font-bold" :class="acuan > 0 && x.lHm > acuan * 1.2 ? 'text-cam-coral' : 'text-cam-lime-deep'">
                {{ angka(x.lHm, 2) }}
              </td>
              <td class="num py-2.5">{{ angka(x.idle, 1) }}</td>
              <td class="num py-2.5">{{ angka(x.ton, 1) }}</td>
              <td class="num py-2.5">{{ angka(x.bcm, 1) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="text-[12px] text-stone-400 mt-6">Belum ada catatan pada rentang ini.</p>
    </section>

  </div>
</template>
