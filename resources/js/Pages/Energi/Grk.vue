<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import KartuAngka from '../../Components/Energi/KartuAngka.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Garis from '../../Grafik/Garis.vue';
import Donat from '../../Grafik/Donat.vue';
import { KATEGORI } from '../../Grafik/warna';
import { n, persen } from '../../energi';

const props = propHalaman();
const r = computed(() => props.r ?? {});
const rentang = reactive({ dari: props.dari, sampai: props.sampai });
const terapkan = () => router.get(window.location.pathname, { ...rentang, perusahaan: props.perusahaan ?? undefined }, { preserveState: true });

const label = computed(() => (props.perBulan ?? []).map((b: any) => b.label));
const deret = computed(() => [
  { nama: 'Scope 1', nilai: (props.perBulan ?? []).map((b: any) => b.s1), warna: KATEGORI[1] },
  { nama: 'Scope 2', nilai: (props.perBulan ?? []).map((b: any) => b.s2), warna: KATEGORI[0] },
  { nama: 'Scope 3', nilai: (props.perBulan ?? []).map((b: any) => b.s3), warna: KATEGORI[6] },
]);
const bagian = computed(() => (props.perKategori ?? []).map((k: any, i: number) => ({ label: k.kategori, nilai: Math.round(k.co2e_t * 10) / 10, warna: KATEGORI[i % 8] })).filter((b: any) => b.nilai > 0));
const perubahan = computed(() => props.dasar && props.dasar.total > 0 ? (r.value.total - props.dasar.total) / props.dasar.total * 100 : null);
</script>

<template>
  <Head title="Inventarisasi GRK" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-end justify-between gap-3">
      <form class="flex flex-wrap items-end gap-2" @submit.prevent="terapkan">
        <label class="text-[11px] font-semibold text-stone-500">Dari<input v-model="rentang.dari" type="date" class="block mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Sampai<input v-model="rentang.sampai" type="date" class="block mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <button class="eq-btn-utama" style="padding:9px 14px">Terapkan</button>
      </form>
      <div class="flex flex-wrap items-center gap-2">
        <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
        <Link href="/energi/grk/batas" class="eq-btn-lain">Batas & tahun dasar</Link>
        <Link href="/energi/grk/scope3" class="eq-btn-lain">Scope 3</Link>
        <Link href="/energi/pelaporan" class="eq-btn-utama">Susun laporan GRK</Link>
      </div>
    </section>

    <p v-if="props.faktorCocok?.length" class="rounded-xl bg-amber-50 text-amber-800 text-[12px] p-3">
      Angka memakai faktor acuan yang belum dicocokkan dengan dokumen sumber: {{ props.faktorCocok.join(', ') }}. <Link href="/energi/faktor" class="font-bold underline">Buka registri faktor</Link>.
    </p>
    <p v-if="r.kurang?.length" class="rounded-xl bg-red-50 text-red-700 text-[12px] p-3">
      {{ r.baris_kurang }} kelompok baris tidak dapat dihitung — faktor tidak tersedia: {{ r.kurang.join(', ') }}. Angkanya tidak dijumlahkan sebagai nol.
    </p>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <KartuAngka label="Scope 1" :nilai="r.s1" satuan="tCO₂e" :desimal="2" catatan="Pembakaran stasioner & bergerak, proses, fugitive" />
      <KartuAngka label="Scope 2 location-based" :nilai="r.s2_lb" satuan="tCO₂e" :desimal="2" :catatan="`Market-based ${n(r.s2_mb, 2)} tCO₂e`" />
      <KartuAngka label="Scope 3" :nilai="r.s3" satuan="tCO₂e" :desimal="2" :catatan="r.s3_terpisah ? `+ ${n(r.s3_terpisah, 0)} tCO₂e kat. 11 (disajikan terpisah)` : 'Kategori terpilih'" />
      <KartuAngka label="Total S1 + S2" :nilai="r.total" satuan="tCO₂e" :desimal="2"
                  :catatan="perubahan !== null ? `${perubahan >= 0 ? '+' : ''}${n(perubahan, 1)}% vs tahun dasar ${props.dasar.tahun}` : (props.dasar ? `Tahun dasar ${props.dasar.tahun} belum berdata` : 'Tahun dasar belum ditetapkan')" />
      <KartuAngka label="CO₂ biogenik (FAME)" :nilai="r.co2_bio" satuan="tCO₂" :desimal="2" catatan="Dilaporkan terpisah, tidak masuk Scope 1" />
      <KartuAngka label="Intensitas emisi" :nilai="props.intensitas" satuan="tCO₂e/ton" :desimal="5" :catatan="`${n(props.produksi?.ton, 0)} ton produksi`" />
      <KartuAngka label="Ketidakpastian gabungan" :nilai="r.ketidakpastian_persen" satuan="%" catatan="IPCC Approach 1 (propagasi galat)" />
      <KartuAngka label="Mutu data" :nilai="r.persen_terverifikasi" satuan="% terverifikasi" :catatan="`${persen(r.persen_estimasi)} estimasi · GWP ${props.gwp}`" />
    </section>

    <section class="grid gap-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
      <KartuGrafik judul="Emisi bulanan per scope" catatan="Scope 3 kategori 11 tidak ikut digambar." :angka="`${n(r.total + r.s3, 0)} tCO₂e`">
        <Garis :label="label" :deret="deret" satuan="tCO₂e" />
        <template #tabel>
          <table><thead><tr><th>Bulan</th><th>Scope 1</th><th>Scope 2</th><th>Scope 3</th></tr></thead>
            <tbody><tr v-for="b in props.perBulan" :key="b.bulan"><td>{{ b.label }}</td><td class="num">{{ n(b.s1, 2) }}</td><td class="num">{{ n(b.s2, 2) }}</td><td class="num">{{ n(b.s3, 2) }}</td></tr></tbody></table>
        </template>
      </KartuGrafik>
      <KartuGrafik judul="Scope 1 per kategori IPCC" catatan="Stasioner, bergerak off-road/jalan, proses, fugitive." :tinggi="150">
        <Donat :bagian="bagian" :tengah="n(r.s1, 0)" tengah-label="tCO₂e" />
        <template #tabel>
          <table><thead><tr><th>Kategori</th><th>tCO₂e</th></tr></thead><tbody><tr v-for="k in props.perKategori" :key="k.kategori"><td>{{ k.kategori }}</td><td class="num">{{ n(k.co2e_t, 2) }}</td></tr></tbody></table>
        </template>
      </KartuGrafik>
    </section>

    <Kartu judul="Emisi per gas" ket="Perpres 110/2025 Ps. 11: CO₂, CH₄, N₂O, HFC, PFC, SF₆.">
      <table class="w-full text-[12px]">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Gas</th><th class="py-2 text-right">Massa (t)</th><th class="py-2 text-right">tCO₂e</th></tr></thead>
        <tbody><tr v-for="g in props.perGas" :key="g.gas" class="border-b border-stone-50"><td class="py-2 font-semibold">{{ g.gas }}</td><td class="py-2 text-right num">{{ n(g.massa_t, 4) }}</td><td class="py-2 text-right num">{{ n(g.co2e_t, 2) }}</td></tr></tbody>
      </table>
    </Kartu>

    <Kartu judul="Rincian per sumber" ket="Scope → sumber → gas, dengan ketidakpastian per baris. Baris bertanda merah kekurangan faktor.">
      <div class="overflow-x-auto">
        <table class="w-full text-[11.5px] min-w-[860px]">
          <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Scope</th><th class="py-2">Sumber</th><th class="py-2">Energi</th><th class="py-2 text-right">Jumlah</th><th class="py-2 text-right">GJ</th><th class="py-2 text-right">CO₂</th><th class="py-2 text-right">CH₄</th><th class="py-2 text-right">N₂O</th><th class="py-2 text-right">tCO₂e</th><th class="py-2 text-right">Bio CO₂</th><th class="py-2 text-right">±%</th></tr></thead>
          <tbody>
            <tr v-for="[i, b] in Object.entries((props.perSumber ?? {}) as Record<string, any>)" :key="i" class="border-b border-stone-50" :class="b.kurang?.length ? 'bg-red-50' : ''">
              <td class="py-1.5">S{{ b.scope || '—' }}</td><td class="py-1.5">{{ b.label }}<span class="block text-[10px] text-stone-400">{{ b.kelompok }}</span></td>
              <td class="py-1.5">{{ b.energi }}</td><td class="py-1.5 text-right num">{{ n(b.jumlah, b.jumlah < 10 ? 2 : 0) }} {{ b.satuan }}</td><td class="py-1.5 text-right num">{{ n(b.gj, 1) }}</td>
              <td class="py-1.5 text-right num">{{ n(b.co2_fosil_t, 2) }}</td><td class="py-1.5 text-right num">{{ n(b.ch4_t, 4) }}</td><td class="py-1.5 text-right num">{{ n(b.n2o_t, 4) }}</td>
              <td class="py-1.5 text-right num font-bold">{{ n(b.co2e_t, 2) }}</td><td class="py-1.5 text-right num">{{ n(b.co2_bio_t, 2) }}</td><td class="py-1.5 text-right num">{{ n(b.ketidakpastian_persen, 1) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="!props.perSumber?.length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada data aktivitas pada rentang ini.</p>
    </Kartu>

    <Kartu v-if="props.scope3?.length" judul="Scope 3 per kategori">
      <table class="w-full text-[12px]"><tbody>
        <tr v-for="k in props.scope3" :key="k.kategori" class="border-b border-stone-50"><td class="py-2">Kat. {{ k.kategori }} — {{ k.label }}<span v-if="k.terpisah" class="ml-1 text-[10.5px] font-bold text-amber-700">disajikan terpisah</span></td><td class="py-2 text-right num">{{ n(k.co2e_t, 2) }} tCO₂e</td></tr>
      </tbody></table>
    </Kartu>
  </div>
</template>
