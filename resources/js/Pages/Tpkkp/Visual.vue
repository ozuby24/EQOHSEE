<script setup lang="ts">
/**
 * PTPKKP — Visualisasi.
 *
 * Empat grafik. Seluruh datanya, termasuk warnanya, datang dari server:
 * versi Blade mengambil warna donat dari window.eqWarnaLevel — larik
 * global yang isinya menyalin Tpkkp::LVHEX — dan salinan seperti itu diam
 * saja ketika paletnya berubah, sehingga satu grafik berwarna beda dari
 * grafik di sebelahnya tanpa ada yang menyadari.
 *
 * Keempat instance dibuang saat komponen dilepas. Pada navigasi Inertia
 * kanvasnya diganti tanpa halaman dimuat ulang, jadi instance lama tetap
 * memegang kanvas yang sudah lepas dari dokumen dan menumpuk tiap kali
 * halaman ini dibuka lagi.
 */
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import type { HalamanVisual } from '../../types';

const props = defineProps<HalamanVisual>();

const kInd   = ref<HTMLCanvasElement | null>(null);
const kDonat = ref<HTMLCanvasElement | null>(null);
const kPar   = ref<HTMLCanvasElement | null>(null);
const kMet   = ref<HTMLCanvasElement | null>(null);

let grafik: any[] = [];

function gambar() {
  const siap = (window as any).eqChartSiap;
  if (typeof siap !== 'function') return;

  siap(() => {
    const C = (window as any).Chart;
    if (!C || !kInd.value || !kDonat.value || !kPar.value || !kMet.value) return;

    grafik.push(new C(kInd.value, {
      type: 'bar',
      data: {
        labels: props.indikator.label,
        datasets: [
          { label: 'Capaian', data: props.indikator.capaian,
            backgroundColor: '#12897F', borderRadius: 6, maxBarThickness: 44 },
          { label: 'Target', data: props.indikator.target,
            backgroundColor: '#d6d3d1', borderRadius: 6, maxBarThickness: 44 },
        ],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, grid: { color: '#f5f5f4' } },
                  x: { grid: { display: false } } },
        plugins: { legend: { position: 'bottom' } },
      },
    }));

    grafik.push(new C(kDonat.value, {
      type: 'doughnut',
      data: {
        labels: props.donat.label,
        datasets: [{ data: props.donat.nilai, backgroundColor: props.donat.warna, borderWidth: 0 }],
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '60%',
        plugins: {
          legend: { position: 'bottom' },
          tooltip: { callbacks: { label: (c: any) => `${c.label}: ${c.raw} item` } },
        },
      },
    }));

    grafik.push(new C(kPar.value, {
      type: 'bar',
      data: {
        labels: props.parameter.label,
        datasets: [
          { label: 'Capaian', data: props.parameter.capaian,
            backgroundColor: props.parameter.warna, borderRadius: 5, maxBarThickness: 26 },
          { label: 'Target', type: 'line', data: props.parameter.target,
            borderColor: '#1B2024', borderDash: [5, 4], borderWidth: 1.5,
            pointRadius: 0, tension: 0 },
        ],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, grid: { color: '#f5f5f4' } },
                  x: { grid: { display: false }, ticks: { maxRotation: 90, minRotation: 0 } } },
        plugins: { legend: { position: 'bottom' } },
      },
    }));

    grafik.push(new C(kMet.value, {
      type: 'bar',
      data: {
        labels: props.metode.label,
        datasets: [{ label: '% terpenuhi', data: props.metode.nilai,
                     backgroundColor: props.metode.warna, borderRadius: 6, maxBarThickness: 40 }],
      },
      options: {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        scales: { x: { beginAtZero: true, max: 100, grid: { color: '#f5f5f4' } },
                  y: { grid: { display: false } } },
        plugins: { legend: { display: false } },
      },
    }));
  });
}

onMounted(gambar);

onBeforeUnmount(() => {
  grafik.forEach((g) => g?.destroy());
  grafik = [];
});
</script>

<template>
  <Head title="PTPKKP — Visualisasi" />

  <div class="max-w-6xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div class="grid lg:grid-cols-2 gap-5">
      <div class="bg-white rounded-2xl border border-stone-200 p-5">
        <h3 class="text-[13px] font-bold text-cam-ink mb-3">Capaian vs Target per Indikator</h3>
        <div class="relative" style="height:280px"><canvas ref="kInd"></canvas></div>
      </div>
      <div class="bg-white rounded-2xl border border-stone-200 p-5">
        <h3 class="text-[13px] font-bold text-cam-ink mb-3">Sebaran Kategori Item</h3>
        <div class="relative" style="height:280px"><canvas ref="kDonat"></canvas></div>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-1">Capaian per Parameter</h3>
      <p class="text-[11px] text-stone-500 mb-3">
        Batang berwarna kategori, garis putus menandai target parameter.
      </p>
      <div class="relative" style="height:340px"><canvas ref="kPar"></canvas></div>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Keterpenuhan per Metode (%)</h3>
      <div class="relative" style="height:260px"><canvas ref="kMet"></canvas></div>
    </div>
  </div>
</template>
