<script setup lang="ts">
/*
  Energy Performance Center — halaman operasional.

  Satu rute controller lama, satu komponen per mode. Sebelumnya mode
  selain dasbor digambar sebagai "tampilan darurat": kisi kunci mentah
  (`l_ton`, `kwh_ton`) yang menjelaskan bentuk data, bukan kinerjanya
  (cacat D4). Tiap mode kini punya susunannya sendiri.

  Prop diambil lewat propHalaman(), bukan defineProps — lihat
  resources/js/halaman.ts untuk alasannya.
*/
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Dasbor from './Dasbor.vue';
import EnergyInput from '../../Components/EnergyInput.vue';
import Konsumsi from '../../Components/Energi/ModeKonsumsi.vue';
import BahanBakar from '../../Components/Energi/ModeBahanBakar.vue';
import Listrik from '../../Components/Energi/ModeListrik.vue';
import Peralatan from '../../Components/Energi/ModePeralatan.vue';
import PeralatanRinci from '../../Components/Energi/ModePeralatanRinci.vue';
import Kpi from '../../Components/Energi/ModeKpi.vue';
import Baseline from '../../Components/Energi/ModeBaseline.vue';
import Penghematan from '../../Components/Energi/ModePenghematan.vue';
import Kalkulator from '../../Components/Energi/ModeKalkulator.vue';
import LaporanBulanan from '../../Components/Energi/ModeLaporan.vue';
import Master from '../../Components/Energi/ModeMaster.vue';

const props = propHalaman();

const judulTab: Record<string, string> = {
  index: 'Energy Performance Center', input: 'Input Lapangan', konsumsi: 'Energy Consumption', fuel: 'Fuel Management',
  listrik: 'Electricity', equipment: 'Equipment Performance', 'equipment-show': 'Equipment Detail', kpi: 'Energy KPI',
  baseline: 'Baseline & Target', hemat: 'Saving Opportunities', kalkulator: 'Energy Calculator',
  laporan: 'Laporan Energi', master: 'Master Data Energi',
};

const tabs = [
  ['index', 'Dashboard', '/energi'], ['input', 'Input', '/energi/input'], ['konsumsi', 'Konsumsi', '/energi/konsumsi'],
  ['fuel', 'Bahan bakar', '/energi/bahan-bakar'], ['listrik', 'Listrik', '/energi/listrik'], ['equipment', 'Peralatan', '/energi/alat'],
  ['kpi', 'KPI', '/energi/kpi'], ['baseline', 'Baseline', '/energi/baseline'], ['hemat', 'Penghematan', '/energi/penghematan'],
  ['karbon', 'Karbon & GRK', '/energi/grk'], ['kalkulator', 'Kalkulator', '/energi/kalkulator'], ['laporan', 'Laporan', '/energi/laporan'],
  ['master', 'Master', '/energi/data-induk'],
];

const rentang = reactive({ dari: String(props.dari ?? '').slice(0, 10), sampai: String(props.sampai ?? '').slice(0, 10) });

function filter() {
  router.get(window.location.pathname, rentang, { preserveState: true, preserveScroll: true });
}
</script>

<template>
  <Head :title="judulTab[props.mode] ?? 'Energi'" />

  <div class="space-y-5">
    <section v-if="props.mode !== 'laporan'" class="flex flex-wrap items-end justify-between gap-4">
      <p class="text-[12.5px] text-stone-500">Data, indikator, dan tindakan pengendalian kinerja energi.</p>
      <form v-if="props.dari || props.sampai" class="flex flex-wrap items-end gap-2" @submit.prevent="filter">
        <label class="text-[11px] font-semibold text-stone-500">Dari
          <input v-model="rentang.dari" type="date" class="block mt-1 rounded-lg border-stone-200 text-[12px]">
        </label>
        <label class="text-[11px] font-semibold text-stone-500">Sampai
          <input v-model="rentang.sampai" type="date" class="block mt-1 rounded-lg border-stone-200 text-[12px]">
        </label>
        <button class="eq-btn-utama" style="padding:9px 14px">Terapkan</button>
      </form>
    </section>

    <nav v-if="props.mode !== 'laporan'" class="flex gap-1 overflow-x-auto rounded-xl bg-stone-100 p-1 text-[11px]" aria-label="Halaman energi">
      <Link v-for="tab in tabs" :key="tab[0]" :href="tab[2]"
            class="whitespace-nowrap rounded-lg px-3 py-2 text-stone-500 hover:bg-white hover:text-cam-ink"
            :class="props.mode === tab[0] || (tab[0] === 'equipment' && props.mode === 'equipment-show') ? 'bg-white font-bold text-cam-ink shadow-sm' : ''">
        {{ tab[1] }}
      </Link>
    </nav>

    <Dasbor v-if="props.mode === 'index'"
            :r="props.r ?? {}" :tren="props.tren ?? []" :teratas="props.teratas ?? []"
            :peluang="props.peluang ?? []" :baseline="props.baseline ?? null" />
    <EnergyInput v-else-if="props.mode === 'input'" :units="props.units ?? []" :areas="props.areas ?? {}" :sources="props.sources ?? {}"
                 :sites="props.sites ?? []" :lain="props.lain ?? {}" :sumber-data="props.sumberData ?? {}" :fame-bawaan="props.fameBawaan ?? null"
                 :recent="props.recent ?? {}" :tautan="props.tautan ?? {}" :boleh="props.boleh ?? {}" />
    <Konsumsi v-else-if="props.mode === 'konsumsi'" :r="props.r ?? {}" :tren="props.tren ?? []" :baseline="props.baseline ?? null" />
    <BahanBakar v-else-if="props.mode === 'fuel'" :r="props.r ?? {}" :peringkat="props.peringkat ?? []" :per-kategori="props.perKategori ?? {}" :recon="props.recon ?? {}" />
    <Listrik v-else-if="props.mode === 'listrik'" :per-area="props.perArea ?? {}" :total="props.total ?? {}" :pln="props.pln ?? {}" :genset="props.genset ?? {}" />
    <Peralatan v-else-if="props.mode === 'equipment'" :peringkat="props.peringkat ?? []" :per-kategori="props.perKategori ?? {}" :kategori="props.kategori ?? null" />
    <PeralatanRinci v-else-if="props.mode === 'equipment-show'" :unit="props.unit ?? {}" :log="props.log ?? []" :m="props.m ?? {}" :acuan="props.acuan ?? 0" :status="props.status ?? {}" />
    <Kpi v-else-if="props.mode === 'kpi'" :r="props.r ?? {}" :baseline="props.baseline ?? null" :hemat="props.hemat ?? {}" />
    <Baseline v-else-if="props.mode === 'baseline'" :daftar="props.daftar ?? []" :baseline="props.baseline ?? null" :r="props.r ?? {}" :companies="props.companies ?? []" />
    <Penghematan v-else-if="props.mode === 'hemat'" :daftar="props.daftar ?? []" :terwujud="props.terwujud ?? {}" :potensi="props.potensi ?? {}" />
    <Kalkulator v-else-if="props.mode === 'kalkulator'" :kode="props.kode ?? {}" :kategori="props.kategori ?? {}" :terpasang="!!props.terpasang" :tautan="props.tautan ?? {}" />
    <LaporanBulanan v-else-if="props.mode === 'laporan'" :dok="props.dok ?? null" :r="props.r ?? {}" :baseline="props.baseline ?? null" :dari="props.dari" :sampai="props.sampai"
                    :per-kategori="props.perKategori ?? {}" :peringkat="props.peringkat ?? []" :peluang="props.peluang ?? []" :hemat="props.hemat ?? {}" />
    <Master v-else-if="props.mode === 'master'" :units="props.units ?? []" :companies="props.companies ?? []" :sites="props.sites ?? []"
            :pilihan="props.pilihan ?? {}" :boleh="props.boleh ?? {}" />
  </div>
</template>
