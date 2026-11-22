<script setup lang="ts">
import { computed } from 'vue';
import KartuAngka from './KartuAngka.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Garis from '../../Grafik/Garis.vue';
import Donat from '../../Grafik/Donat.vue';
import { KATEGORI } from '../../Grafik/warna';
import { n, ringkas, rp, tgl } from '../../energi';

const props = defineProps<{ r: Record<string, any>; tren: any[]; baseline: Record<string, any> | null }>();

const WARNA: Record<string, string> = { Solar: KATEGORI[1], Listrik: KATEGORI[0], Gas: KATEGORI[2] };
const label = computed(() => props.tren.map(t => new Date(t.tanggal).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })));
const deret = computed(() => [
  { nama: 'Solar', nilai: props.tren.map(t => Number(t.gj_solar ?? 0)), warna: WARNA.Solar },
  { nama: 'Listrik', nilai: props.tren.map(t => Number(t.gj_listrik ?? 0)), warna: WARNA.Listrik },
]);
const bauran = computed(() => Object.entries((props.r?.rincian ?? {}) as Record<string, any>)
  .map(([nama, d]: [string, any]) => ({ label: nama, nilai: Math.round(Number(d.gj)), warna: WARNA[nama] }))
  .filter(b => b.nilai > 0));
</script>

<template>
  <div class="space-y-4">
    <section class="ka-kisi">
      <KartuAngka label="Total energi" :nilai="r.gj" satuan="GJ" :catatan="`${props.r.hari ?? 0} hari · ${ringkas(r.liter_hari)} L solar/hari`" />
      <KartuAngka label="Intensitas energi" :nilai="r.intensitas" satuan="GJ/ton" :desimal="4"
                  :catatan="baseline ? `Target ${n(baseline.target_gj_ton, 4)} GJ/ton` : 'Belum ada baseline'" />
      <KartuAngka label="Emisi" :nilai="r.tco2e" satuan="tCO₂e" :desimal="2" />
      <KartuAngka label="Biaya energi" :nilai="r.rupiah" satuan="Rp" :desimal="0" />
      <KartuAngka label="Solar per ton" :nilai="r.l_ton" satuan="L/ton" :desimal="3" />
      <KartuAngka label="Solar per BCM" :nilai="r.l_bcm" satuan="L/BCM" :desimal="3" />
      <KartuAngka label="Listrik per ton" :nilai="r.kwh_ton" satuan="kWh/ton" :desimal="3" />
      <KartuAngka label="Produksi" :nilai="r.ton" satuan="ton" :desimal="0" :catatan="`${ringkas(r.bcm)} BCM overburden`" />
    </section>

    <section class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
      <KartuGrafik judul="Energi harian menurut sumber" :angka="`${n(r.gj, 0)} GJ`">
        <Garis :label="label" :deret="deret" satuan="GJ" />
        <template #tabel>
          <table>
            <thead><tr><th>Tanggal</th><th>Solar (L)</th><th>Listrik (kWh)</th><th>GJ</th><th>Intensitas</th></tr></thead>
            <tbody>
              <tr v-for="(t, i) in tren" :key="i">
                <td>{{ tgl(t.tanggal) }}</td><td class="num">{{ n(t.liter, 0) }}</td><td class="num">{{ n(t.kwh, 0) }}</td>
                <td class="num">{{ n(t.gj, 1) }}</td><td class="num">{{ t.ton > 0 ? n(t.intensitas, 4) : 'tidak diukur' }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </KartuGrafik>

      <KartuGrafik judul="Bauran energi" :angka="rp(r.rupiah)" :tinggi="150">
        <Donat :bagian="bauran" :tengah="ringkas(r.gj)" tengah-label="GJ" />
        <template #tabel>
          <table>
            <thead><tr><th>Sumber</th><th>Jumlah</th><th>GJ</th><th>tCO₂e</th><th>Rp</th></tr></thead>
            <tbody>
              <tr v-for="(d, nama) in (r.rincian ?? {})" :key="nama">
                <td>{{ nama }}</td><td class="num">{{ n(d.jumlah, 0) }} {{ d.satuan }}</td><td class="num">{{ n(d.gj, 1) }}</td>
                <td class="num">{{ n(d.tco2e, 2) }}</td><td class="num">{{ rp(d.rupiah) }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </KartuGrafik>
    </section>
  </div>
</template>
