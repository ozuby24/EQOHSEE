<script setup lang="ts">
/**
 * Energy Calculator — alat hitung cepat, tidak disimpan.
 */
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import KepalaEnergi from '../../Components/KepalaEnergi.vue';
import { angka } from '../../energi';
import type { HalamanEnergiKalkulator } from '../../types';

const props = defineProps<HalamanEnergiKalkulator>();

const liter = ref(1000);
const kwh = ref(5000);
const m3 = ref(0);
const ton = ref(2000);
const hematLiter = ref(0);
const hematKwh = ref(0);

const n = (v: number) => Number(v) || 0;

const gj = computed(() => n(liter.value) * props.faktor.gjLiter + n(kwh.value) * props.faktor.gjKwh + n(m3.value) * props.faktor.gjM3);
const tco2e = computed(() => n(liter.value) * props.faktor.co2Liter + n(kwh.value) * props.faktor.co2Kwh + n(m3.value) * props.faktor.co2M3);
const rp = computed(() => n(liter.value) * props.faktor.rpLiter + n(kwh.value) * props.faktor.rpKwh + n(m3.value) * props.faktor.rpM3);
const intensitas = computed(() => (n(ton.value) > 0 ? gj.value / n(ton.value) : 0));

const hematRp = computed(() => n(hematLiter.value) * props.faktor.rpLiter + n(hematKwh.value) * props.faktor.rpKwh);
const hematGj = computed(() => n(hematLiter.value) * props.faktor.gjLiter + n(hematKwh.value) * props.faktor.gjKwh);
const hematCo2 = computed(() => n(hematLiter.value) * props.faktor.co2Liter + n(hematKwh.value) * props.faktor.co2Kwh);

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition num';
const label = 'block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-4xl mx-auto space-y-5">

    <KepalaEnergi judul="Energy Calculator"
        ket="Alat hitung cepat memakai faktor konversi yang sama persis dengan seluruh halaman
             Energy. Angkanya tidak disimpan — ini untuk menimbang sebuah usulan sebelum
             dicatat, bukan untuk melaporkan." />

    <div class="grid gap-4 lg:grid-cols-2">

      <section class="kartu-lux rounded-2xl p-6">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Konversi Energi</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">Masukkan pemakaian, lihat setara energi, biaya, dan emisinya.</p>

        <div class="space-y-3.5 mt-5">
          <div>
            <label :class="label">Solar (liter)</label>
            <input v-model.number="liter" type="number" step="any" min="0" :class="isian">
          </div>
          <div>
            <label :class="label">Listrik (kWh)</label>
            <input v-model.number="kwh" type="number" step="any" min="0" :class="isian">
          </div>
          <div>
            <label :class="label">Gas (m³)</label>
            <input v-model.number="m3" type="number" step="any" min="0" :class="isian">
          </div>
          <div>
            <label :class="label">Produksi (ton)</label>
            <input v-model.number="ton" type="number" step="any" min="0" :class="isian">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 mt-5 pt-5 hairline border-b-0">
          <div>
            <div class="stat stat-sm" style="color:#F57C00">{{ angka(gj, 2) }}<span class="stat-unit">GJ</span></div>
            <div class="text-[10.5px] text-stone-400 mt-1.5">Total Energi</div>
          </div>
          <div>
            <div class="stat stat-sm" style="color:#FF9800">{{ angka(intensitas, 4) }}<span class="stat-unit">GJ/ton</span></div>
            <div class="text-[10.5px] text-stone-400 mt-1.5">Intensitas</div>
          </div>
          <div>
            <div class="stat stat-sm" style="color:#22312F">{{ angka(tco2e, 3) }}<span class="stat-unit">tCO₂e</span></div>
            <div class="text-[10.5px] text-stone-400 mt-1.5">Emisi</div>
          </div>
          <div>
            <div class="stat stat-sm" style="color:#E2663A">Rp {{ angka(rp) }}</div>
            <div class="text-[10.5px] text-stone-400 mt-1.5">Biaya</div>
          </div>
        </div>
      </section>

      <section class="kartu-lux rounded-2xl p-6">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Nilai Penghematan</h3>
        <p class="text-[11.5px] text-stone-500 mt-1 leading-relaxed">
          Perkiraan penghematan bulanan sebuah usulan, dinilai dalam rupiah, energi, dan karbon
          sekaligus — ketiganya sering dibutuhkan pada lembar usulan yang sama.
        </p>

        <div class="space-y-3.5 mt-5">
          <div>
            <label :class="label">Hemat solar (L per bulan)</label>
            <input v-model.number="hematLiter" type="number" step="any" min="0" :class="isian">
          </div>
          <div>
            <label :class="label">Hemat listrik (kWh per bulan)</label>
            <input v-model.number="hematKwh" type="number" step="any" min="0" :class="isian">
          </div>
        </div>

        <div class="space-y-3 mt-5 pt-5 hairline border-b-0">
          <div class="rounded-xl border border-stone-100 px-4 py-3">
            <div class="text-[10px] font-bold uppercase tracking-wide text-stone-400">Per bulan</div>
            <div class="flex flex-wrap items-baseline gap-x-5 gap-y-1 mt-2">
              <span class="num text-[17px] font-bold text-cam-coral">Rp {{ angka(hematRp) }}</span>
              <span class="num text-[12.5px] text-stone-500">{{ angka(hematGj, 2) }} GJ</span>
              <span class="num text-[12.5px] text-stone-500">{{ angka(hematCo2, 3) }} tCO₂e</span>
            </div>
          </div>
          <div class="rounded-xl border border-stone-100 px-4 py-3">
            <div class="text-[10px] font-bold uppercase tracking-wide text-stone-400">Per tahun</div>
            <div class="flex flex-wrap items-baseline gap-x-5 gap-y-1 mt-2">
              <span class="num text-[17px] font-bold text-cam-coral">Rp {{ angka(hematRp * 12) }}</span>
              <span class="num text-[12.5px] text-stone-500">{{ angka(hematGj * 12, 2) }} GJ</span>
              <span class="num text-[12.5px] text-stone-500">{{ angka(hematCo2 * 12, 3) }} tCO₂e</span>
            </div>
          </div>
        </div>

        <Link :href="tautan.hemat"
              class="block text-center rounded-xl bg-cam-ink text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-110 transition mt-4">
          Catat sebagai peluang penghematan
        </Link>
      </section>
    </div>

    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Faktor Konversi yang Dipakai</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">
        Faktor yang sama dipakai seluruh halaman Energy, disimpan di satu berkas. Mengubahnya
        di sana langsung tercermin di sini dan di seluruh riwayat.
      </p>

      <div class="overflow-x-auto mt-4 -mx-1">
        <table class="w-full text-[12.5px] min-w-[480px]">
          <thead>
            <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
              <th class="text-left py-2.5">Sumber</th>
              <th class="num py-2.5">Energi</th>
              <th class="num py-2.5">Emisi</th>
              <th class="num py-2.5">Harga</th>
            </tr>
          </thead>
          <tbody>
            <tr class="hairline">
              <td class="py-2.5 font-semibold text-cam-ink">Solar</td>
              <td class="num py-2.5">{{ angka(faktor.gjLiter, 4) }} GJ/L</td>
              <td class="num py-2.5">{{ angka(faktor.co2Liter * 1000, 2) }} kg CO₂e/L</td>
              <td class="num py-2.5">Rp {{ angka(faktor.rpLiter) }}/L</td>
            </tr>
            <tr class="hairline">
              <td class="py-2.5 font-semibold text-cam-ink">Listrik</td>
              <td class="num py-2.5">{{ angka(faktor.gjKwh, 5) }} GJ/kWh</td>
              <td class="num py-2.5">{{ angka(faktor.co2Kwh * 1000, 4) }} kg CO₂e/kWh</td>
              <td class="num py-2.5">Rp {{ angka(faktor.rpKwh) }}/kWh</td>
            </tr>
            <tr class="hairline">
              <td class="py-2.5 font-semibold text-cam-ink">Gas</td>
              <td class="num py-2.5">{{ angka(faktor.gjM3, 4) }} GJ/m³</td>
              <td class="num py-2.5">{{ angka(faktor.co2M3 * 1000, 4) }} kg CO₂e/m³</td>
              <td class="num py-2.5">Rp {{ angka(faktor.rpM3) }}/m³</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

  </div>
</template>
