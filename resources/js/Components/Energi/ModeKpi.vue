<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Kartu from './Kartu.vue';
import KartuAngka from './KartuAngka.vue';
import Meter from '../../Grafik/Meter.vue';
import { n, rp } from '../../energi';

const props = defineProps<{ r: Record<string, any>; baseline: Record<string, any> | null; hemat: Record<string, any> }>();

const target = computed(() => props.baseline?.target_gj_ton ?? null);
const maks = computed(() => Math.max(Number(props.r.intensitas ?? 0), Number(target.value ?? 0), Number(props.baseline?.baseline_gj_ton ?? 0)) * 1.4 || 1);
const kemajuan = computed(() => {
  const b = Number(props.baseline?.baseline_gj_ton ?? 0), t = Number(target.value ?? 0), x = Number(props.r.intensitas ?? 0);
  return b > t && x > 0 ? Math.max(0, Math.min(100, (b - x) / (b - t) * 100)) : null;
});
</script>

<template>
  <div class="space-y-4">
    <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
      <Kartu judul="Intensitas energi vs target" :ket="baseline ? `Baseline ${baseline.tahun}: ${n(baseline.baseline_gj_ton, 4)} → target ${n(baseline.target_gj_ton, 4)} GJ/ton` : 'Belum ada baseline yang ditetapkan.'">
        <Meter :nilai="r.intensitas ?? null" :target="target" :maks="maks" satuan=" GJ/ton" kecil-lebih-baik />
        <p v-if="kemajuan !== null" class="text-[12px] text-stone-600 mt-3">Kemajuan menuju target: <b>{{ n(kemajuan, 0) }}%</b> dari rentang baseline ke target.</p>
        <Link v-if="!baseline" href="/energi/baseline" class="eq-tautan mt-2">Tetapkan baseline</Link>
      </Kartu>
      <div class="ubin-kisi">
        <KartuAngka label="Total energi" :nilai="r.gj" satuan="GJ" />
        <KartuAngka label="Emisi" :nilai="r.tco2e" satuan="tCO₂e" :desimal="2" />
        <KartuAngka label="Solar per ton" :nilai="r.l_ton" satuan="L/ton" :desimal="3" />
        <KartuAngka label="Listrik per ton" :nilai="r.kwh_ton" satuan="kWh/ton" :desimal="3" />
      </div>
    </section>

    <Kartu judul="Penghematan berjalan">
      <div class="ubin-kisi">
        <KartuAngka label="Peluang" :nilai="hemat.jumlah" satuan="inisiatif" :desimal="0" />
        <KartuAngka label="Energi dihemat" :nilai="hemat.gj" satuan="GJ/bulan" />
        <KartuAngka label="Emisi dihindari" :nilai="hemat.tco2e" satuan="tCO₂e/bulan" :desimal="2" />
        <KartuAngka label="Biaya dihemat" :nilai="hemat.rupiah" satuan="Rp/bulan" :desimal="0" :catatan="rp((hemat.rupiah ?? 0) * 12) + ' per tahun'" />
      </div>
    </Kartu>
  </div>
</template>
