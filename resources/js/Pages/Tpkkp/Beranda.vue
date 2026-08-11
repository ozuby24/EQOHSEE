<script setup lang="ts">
/**
 * PTPKKP — Beranda.
 *
 * Halaman ketiga yang dipindah ke Vue, dipilih karena ia pintu masuk
 * modul: dari sini chip ke Penilaian dan Rekapitulasi berpindah tanpa
 * memuat ulang, dan itulah yang membuat modulnya terasa satu kesatuan
 * alih-alih kumpulan halaman yang saling memuat ulang.
 *
 * Seluruh angka dihitung server. Yang dikerjakan di sini hanya
 * menggambar — termasuk dua grafik Chart.js, yang perlu perhatian khusus
 * pada navigasi Inertia: kanvasnya diganti tanpa halaman dimuat ulang,
 * jadi instance Chart lama harus dibuang sendiri. Kalau tidak, ia tetap
 * memegang kanvas yang sudah lepas dari dokumen dan menumpuk tiap kali
 * halaman ini dibuka lagi.
 */
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import type { HalamanBeranda } from '../../types';

const props = defineProps<HalamanBeranda>();

const kanvasRadar = ref<HTMLCanvasElement | null>(null);
const kanvasDonat = ref<HTMLCanvasElement | null>(null);

/* Instance disimpan supaya dapat dibuang saat komponen dilepas. */
let radar: any = null;
let donat: any = null;

const angka = (n: number | null | undefined, d = 3) =>
  n === null || n === undefined
    ? '—'
    : n.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });

function gambar() {
  const siap = (window as any).eqChartSiap;
  const Chart = (window as any).Chart;

  // Pemuat Chart.js berasal dari partial Blade yang sama dengan halaman
  // lain. Bila belum siap, `eqChartSiap` menunggu sendiri.
  if (typeof siap !== 'function') return;

  siap(() => {
    const C = (window as any).Chart ?? Chart;
    if (!C || !kanvasRadar.value || !kanvasDonat.value) return;

    radar = new C(kanvasRadar.value, {
      type: 'radar',
      data: {
        labels: props.radar.label,
        datasets: [
          { label: 'Capaian %', data: props.radar.capaian,
            borderColor: '#12897F', backgroundColor: 'rgba(18,137,127,.18)',
            pointBackgroundColor: '#12897F', borderWidth: 2 },
          { label: 'Target %', data: props.radar.target,
            borderColor: '#1B2024', borderDash: [5, 4], backgroundColor: 'transparent',
            pointBackgroundColor: '#1B2024', borderWidth: 1.5 },
        ],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: { r: { min: 0, max: 100, ticks: { stepSize: 25, backdropColor: 'transparent' },
                       grid: { color: '#e7e5e4' }, angleLines: { color: '#e7e5e4' } } },
        plugins: { legend: { position: 'bottom' } },
      },
    });

    donat = new C(kanvasDonat.value, {
      type: 'doughnut',
      data: {
        labels: props.tingkat.map((t) => t.nama),
        datasets: [{
          data: props.tingkat.map((t) => t.jumlah),
          backgroundColor: props.tingkat.map((t) => t.warna),
          borderWidth: 0,
        }],
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '62%',
        plugins: {
          legend: { position: 'bottom' },
          tooltip: { callbacks: { label: (c: any) => `${c.label}: ${c.raw} item` } },
        },
      },
    });
  });
}

onMounted(gambar);

onBeforeUnmount(() => {
  radar?.destroy();
  donat?.destroy();
  radar = donat = null;
});
</script>

<template>
  <Head title="PTPKKP — Beranda" />

  <div class="max-w-5xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <!-- ══════════ KARTU CAPAIAN ══════════ -->
    <div class="brand-gradient rounded-2xl p-7 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-24 -top-24 w-72 h-72 rounded-full bg-cam-lime/20 blur-3xl"></div>

      <div class="relative flex flex-wrap items-center justify-between gap-6">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">
            Tingkat Pencapaian Kinerja Keselamatan Pertambangan
          </span>
          <h2 class="stat mt-2 leading-tight">{{ identitas.organisasi }}</h2>
          <p class="text-[12px] text-white/45 mt-1">
            {{ identitas.site ?? '—' }} · {{ identitas.komoditas ?? '—' }} ·
            Periode {{ identitas.tahun }}
          </p>
        </div>
        <div class="glass rounded-2xl px-8 py-5 text-center">
          <div class="stat stat-xl leading-none text-cam-lime-light">{{ angka(hasil.skor) }}</div>
          <div class="text-[9.5px] uppercase tracking-[0.15em] text-white/40 mt-2 font-bold">
            Nilai Capaian (maks 1,000)
          </div>
        </div>
      </div>

      <div class="relative mt-6">
        <div class="flex justify-between text-[10px] font-bold uppercase tracking-wide mb-2">
          <span v-for="(t, i) in tingkat" :key="t.nama"
                :class="(i + 1) === hasil.tingkat ? 'text-cam-lime-light' : 'text-white/25'">
            {{ t.nama }}
          </span>
        </div>
        <div class="h-2.5 rounded-full bg-white/10 overflow-hidden">
          <div class="h-full rounded-full bg-cam-lime"
               :style="{ width: Math.max(1, Math.round((hasil.skor ?? 0) * 100)) + '%' }"></div>
        </div>

        <div class="flex flex-wrap items-center gap-2 mt-4">
          <span class="glass-light rounded-lg px-3 py-1.5 text-[11.5px] font-semibold">
            Kategori: {{ hasil.kategori ?? 'Belum dinilai' }}
          </span>
          <span class="glass-light rounded-lg px-3 py-1.5 text-[11.5px] font-semibold num">
            {{ hasil.selTerisi }} / {{ hasil.selTotal }} sel metode terisi
          </span>
          <span class="glass-light rounded-lg px-3 py-1.5 text-[11.5px] font-semibold num">
            Target {{ hasil.target.toFixed(2) }}
          </span>
          <span v-if="hasil.skor !== null"
                class="rounded-lg px-3 py-1.5 text-[11.5px] font-bold"
                :class="hasil.skor >= hasil.target ? 'bg-cam-lime text-cam-ink' : 'bg-white/15 text-white/80'">
            {{ hasil.skor >= hasil.target
                ? 'Target tercapai'
                : 'Kurang ' + angka(hasil.target - hasil.skor) }}
          </span>
        </div>
      </div>
    </div>

    <div v-if="hasil.selTerisi === 0"
         class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-[12.5px] text-amber-800">
      Belum ada nilai yang masuk. Mulai dari
      <a href="/tpkkp/penilaian" class="font-bold underline">Penilaian</a> — pilih metode,
      lalu isi nilai per entitas. Ingat: metode yang belum diisi dihitung nol, jadi angka
      akan naik seiring kelengkapan.
    </div>

    <!-- ══════════ CAPAIAN PER INDIKATOR ══════════ -->
    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-stone-100 flex items-center justify-between">
        <h3 class="text-[13px] font-bold text-cam-ink">Capaian per Indikator</h3>
        <span class="text-[11px] text-stone-400 num">
          kelengkapan {{ (hasil.kelengkapan * 100).toFixed(1) }}%
        </span>
      </div>

      <div class="divide-y divide-stone-50">
        <div v-for="ind in hasil.indikator" :key="ind.kode" class="px-5 py-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-[12.5px] font-semibold text-cam-ink pr-3">{{ ind.kode }}. {{ ind.nama }}</div>
            <div class="flex items-center gap-2">
              <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full text-white whitespace-nowrap"
                    :style="{ background: ind.kategori ? ind.warna : '#a8a29e' }">
                {{ ind.kategori ?? 'Belum dinilai' }}
              </span>
              <span class="num text-[13px] font-bold text-cam-ink">{{ angka(ind.skor) }}</span>
            </div>
          </div>
          <div class="h-1.5 rounded-full bg-stone-100 mt-2.5 overflow-hidden">
            <div class="h-full rounded-full"
                 :style="{ width: Math.max(0, Math.min(100, Math.round((ind.rasio ?? 0) * 100))) + '%',
                           background: ind.warna }"></div>
          </div>
          <div class="flex justify-between text-[10.5px] text-stone-400 mt-1.5 num">
            <span>bobot {{ ind.bobot.toFixed(2) }} · target {{ ind.target.toFixed(2) }}</span>
            <span>{{ ind.selTerisi }}/{{ ind.selTotal }} sel</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════ GRAFIK ══════════ -->
    <div class="grid md:grid-cols-2 gap-5">
      <div class="bg-white rounded-2xl border border-stone-200 p-5">
        <h3 class="text-[13px] font-bold text-cam-ink mb-3">Capaian vs Target per Indikator</h3>
        <div class="relative" style="height:260px"><canvas ref="kanvasRadar"></canvas></div>
      </div>
      <div class="bg-white rounded-2xl border border-stone-200 p-5">
        <h3 class="text-[13px] font-bold text-cam-ink mb-3">Sebaran Kategori Item</h3>
        <div class="relative" style="height:260px"><canvas ref="kanvasDonat"></canvas></div>
      </div>
    </div>

    <div class="grid md:grid-cols-2 gap-5">
      <!-- ══════════ REKAP METODE ══════════ -->
      <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-stone-100">
          <h3 class="text-[13px] font-bold text-cam-ink">Keterpenuhan per Metode</h3>
        </div>
        <div class="tabel-scroll">
          <table class="w-full text-[12px]">
            <tbody>
              <tr v-for="m in metode" :key="m.kode" class="border-b border-stone-50 last:border-0">
                <td class="px-5 py-2.5">
                  <b>{{ m.kode }}</b> <span class="text-stone-500">· {{ m.nama }}</span>
                </td>
                <td class="px-3 py-2.5 text-right num text-stone-500 whitespace-nowrap">
                  {{ m.terisi }}/{{ m.jumlah }}
                </td>
                <td class="px-5 py-2.5 text-right num font-bold">{{ angka(m.rasio) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ══════════ SEBARAN KATEGORI ══════════ -->
      <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-stone-100">
          <h3 class="text-[13px] font-bold text-cam-ink">Sebaran Kategori Item</h3>
        </div>
        <div class="p-5 space-y-2.5">
          <div v-for="t in tingkat" :key="t.nama">
            <div class="flex justify-between text-[11.5px] mb-1">
              <span class="font-semibold text-stone-600">{{ t.nama }}</span>
              <span class="num text-stone-500">{{ t.jumlah }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full"
                   :style="{ width: Math.max(0, Math.round(totalItem ? t.jumlah / totalItem * 100 : 0)) + '%',
                             background: t.warna }"></div>
            </div>
          </div>
          <div class="flex justify-between text-[11.5px] pt-1 border-t border-stone-100">
            <span class="text-stone-400">Belum lengkap</span>
            <span class="num text-stone-400">{{ belumLengkap }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════ GAP TERBESAR ══════════ -->
    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-stone-100">
        <h3 class="text-[13px] font-bold text-cam-ink">10 Item dengan Capaian Terendah</h3>
      </div>

      <p v-if="!gaps.length" class="px-5 py-6 text-[12.5px] text-stone-400">
        Belum ada item yang dinilai.
      </p>

      <div v-else class="tabel-scroll">
        <table class="w-full text-[12px]">
          <tbody>
            <tr v-for="g in gaps" :key="g.kode" class="border-b border-stone-50 last:border-0">
              <td class="px-5 py-2.5 num font-bold text-stone-500 w-16">{{ g.kode }}</td>
              <td class="px-2 py-2.5 text-stone-700">{{ g.nama.slice(0, 70) }}</td>
              <td class="px-3 py-2.5 text-right num text-stone-500 whitespace-nowrap">
                {{ g.nilai.toFixed(1) }}/{{ g.maks }}
              </td>
              <td class="px-5 py-2.5 text-right">
                <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full text-white whitespace-nowrap"
                      :style="{ background: g.kategori ? g.warna : '#a8a29e' }">
                  {{ g.kategori ?? 'Belum dinilai' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
