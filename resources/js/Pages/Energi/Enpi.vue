<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import KartuAngka from '../../Components/Energi/KartuAngka.vue';
import Lencana from '../../Components/Energi/Lencana.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Garis from '../../Grafik/Garis.vue';
import { KATEGORI } from '../../Grafik/warna';
import { n } from '../../energi';

const props = propHalaman();
const m = computed(() => props.model ?? {});
const pilih = reactive({ mulai: props.mulai, sampai: props.sampai, variabel: [...(props.variabel ?? [])] });
const hitung = () => router.get('/energi/enpi', { mulai: pilih.mulai, sampai: pilih.sampai, variabel: pilih.variabel }, { preserveState: true });

const label = computed(() => (props.data ?? []).map((d: any) => d.label));
const deret = computed(() => [
  { nama: 'Aktual', nilai: (props.data ?? []).map((d: any) => d.gj), warna: KATEGORI[1] },
  { nama: 'EnB prediksi', nilai: (props.data ?? []).map((d: any) => d.prediksi), warna: KATEGORI[0] },
]);

const usul = useForm<Record<string, any>>({ tahun: new Date().getFullYear() + 1, mulai: props.mulai, sampai: props.sampai, variabel: props.variabel, target_gj_ton: '', target_tco2e_ton: '' });
const kirim = () => usul.transform(d => ({ ...d, mulai: pilih.mulai, sampai: pilih.sampai, variabel: pilih.variabel, target_tco2e_ton: d.target_tco2e_ton || null })).post('/energi/enpi', { preserveScroll: true });
</script>

<template>
  <Head title="EnPI & EnB" />
  <div class="space-y-4">
    <Kartu judul="Energy Baseline ternormalisasi (ISO 50006)" ket="Regresi energi bulanan (GJ) terhadap variabel relevan. Model diterima bila R² dan CV(RMSE) memenuhi ambang; bila tidak, gunakan EnPI rasio sederhana (GJ/ton).">
      <form class="flex flex-wrap items-end gap-3" @submit.prevent="hitung">
        <label class="text-[11px] font-semibold text-stone-500">Periode dasar dari<input v-model="pilih.mulai" type="date" class="block mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">sampai<input v-model="pilih.sampai" type="date" class="block mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <fieldset class="flex flex-wrap gap-2 items-center"><legend class="text-[11px] font-semibold text-stone-500 mb-1">Variabel bebas</legend>
          <label v-for="[k, l] in Object.entries((props.pilihanVariabel ?? {}) as Record<string, any>)" :key="k" class="text-[11px] rounded-lg bg-stone-50 px-2 py-1 flex items-center gap-1.5"><input v-model="pilih.variabel" type="checkbox" :value="k"> {{ l }}</label>
        </fieldset>
        <button class="eq-btn-utama" style="padding:9px 14px">Hitung regresi</button>
      </form>
    </Kartu>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <KartuAngka label="R²" :nilai="m.r2" :desimal="3" :rag="m.r2 === null || m.r2 === undefined ? null : (m.r2 >= props.ambang.r2 ? 'hijau' : 'merah')" :catatan="`ambang ≥ ${props.ambang.r2}`" />
      <KartuAngka label="CV(RMSE)" :nilai="m.cv_rmse" satuan="%" :rag="m.cv_rmse === null || m.cv_rmse === undefined ? null : (m.cv_rmse <= props.ambang.cv ? 'hijau' : 'merah')" :catatan="`ambang ≤ ${props.ambang.cv}%`" />
      <KartuAngka label="Titik data" :nilai="m.n" satuan="bulan" :desimal="0" />
      <article class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[11px] font-semibold text-stone-500">Persamaan</p>
        <p v-if="m.sah" class="text-[12px] text-cam-ink mt-1 font-mono break-words">GJ = {{ n(m.intercept, 2) }}<template v-for="(b, v) in m.koefisien" :key="v"> + {{ n(b, 5) }}·{{ v }} <span class="text-[10px] text-stone-400">(p {{ n(m.p?.[v], 3) }})</span></template></p>
        <p v-else class="text-[11.5px] text-red-600 mt-1">{{ m.alasan }}</p>
        <p v-if="m.sah && !m.diterima" class="text-[11px] text-amber-700 mt-1">{{ m.alasan }}</p>
      </article>
    </section>

    <KartuGrafik judul="Aktual vs EnB prediksi" catatan="Selisih positif (aktual di bawah prediksi) adalah penghematan ternormalisasi." :angka="`${props.data?.length ?? 0} bulan`">
      <Garis :label="label" :deret="deret" satuan="GJ" />
      <template #tabel>
        <table><thead><tr><th>Bulan</th><th>GJ aktual</th><th>GJ prediksi</th><th>Ton</th><th>BCM</th></tr></thead>
          <tbody><tr v-for="d in props.data" :key="d.bulan"><td>{{ d.label }}</td><td class="num">{{ n(d.gj, 1) }}</td><td class="num">{{ n(d.prediksi, 1) }}</td><td class="num">{{ n(d.ton, 0) }}</td><td class="num">{{ n(d.bcm, 0) }}</td></tr></tbody></table>
      </template>
    </KartuGrafik>

    <section class="grid gap-4 lg:grid-cols-2">
      <Kartu v-if="props.boleh.kelola" judul="Usulkan baseline & target" ket="Model periode dasar ini disimpan bersama usulan; berlaku setelah disetujui BOD di Control Tower.">
        <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="kirim">
          <label class="text-[11px] font-semibold text-stone-500">Tahun target<input v-model="usul.tahun" type="number" min="2000" max="2100" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500">Target GJ/ton<input v-model="usul.target_gj_ton" required type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500">Target tCO₂e/ton<input v-model="usul.target_tco2e_ton" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <div class="sm:col-span-3"><button class="eq-btn-utama" :disabled="usul.processing">Usulkan ke BOD</button></div>
        </form>
        <p v-for="(pesan, k) in usul.errors" :key="k" class="text-[11px] text-red-600 mt-1">{{ pesan }}</p>
      </Kartu>
      <Kartu judul="Baseline tersimpan">
        <ul class="divide-y divide-stone-100 text-[12px]">
          <li v-for="b in props.baseline" :key="b.id" class="py-2 flex items-center justify-between gap-2">
            <span>{{ b.tahun }} · {{ n(b.baseline_gj_ton, 4) }} → {{ n(b.target_gj_ton, 4) }} GJ/ton <span v-if="b.punya_model" class="text-[10.5px] text-stone-400">· model EnB</span></span>
            <Lencana :status="b.status" />
          </li>
        </ul>
      </Kartu>
    </section>
  </div>
</template>
