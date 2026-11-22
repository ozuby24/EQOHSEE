<script setup lang="ts">
import Kartu from './Kartu.vue';
import KartuAngka from './KartuAngka.vue';
import { n, rp } from '../../energi';

defineProps<{ perArea: Record<string, any>; total: Record<string, any>; pln: Record<string, any>; genset: Record<string, any> }>();
</script>

<template>
  <div class="space-y-4">
    <section class="ka-kisi">
      <KartuAngka label="Listrik terpakai" :nilai="total.kwh" satuan="kWh" :desimal="0" :catatan="`${n(total.gj, 1)} GJ`" />
      <KartuAngka label="Listrik per ton" :nilai="total.perTon" satuan="kWh/ton" :desimal="3" />
      <KartuAngka label="Emisi listrik beli" :nilai="total.tco2e" satuan="tCO₂e" :desimal="2" catatan="Scope 2" />
      <KartuAngka label="Biaya listrik" :nilai="total.rupiah" satuan="Rp" :desimal="0" />
      <KartuAngka label="Dari PLN" :nilai="pln.kwh" satuan="kWh" :desimal="0" />
      <KartuAngka label="Dari genset" :nilai="genset.kwh" satuan="kWh" :desimal="0" :catatan="`${n(genset.liter, 0)} L solar · ${n(genset.jam, 1)} jam`" />
      <KartuAngka label="Efisiensi genset" :nilai="genset.efisiensi" satuan="kWh/L" :desimal="2" />
    </section>

    <Kartu judul="Pemakaian per area">
      <table class="w-full text-[12px]">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Area</th><th class="py-2 text-right">kWh</th><th class="py-2 text-right">GJ</th><th class="py-2 text-right">Beban puncak</th><th class="py-2 text-right">Jam</th><th class="py-2 text-right">Faktor beban</th><th class="py-2 text-right">Biaya</th></tr></thead>
        <tbody>
          <tr v-for="(a, kode) in perArea" :key="kode" class="border-b border-stone-50">
            <td class="py-2 font-semibold">{{ a.nama }}</td><td class="py-2 text-right num">{{ n(a.kwh, 0) }}</td><td class="py-2 text-right num">{{ n(a.gj, 1) }}</td>
            <td class="py-2 text-right num">{{ n(a.puncak, 0) }} kW</td><td class="py-2 text-right num">{{ n(a.jam, 1) }}</td>
            <td class="py-2 text-right num" :class="a.faktor < 40 ? 'text-amber-700 font-bold' : ''">{{ n(a.faktor, 1) }}%</td>
            <td class="py-2 text-right num">{{ rp(a.rupiah) }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="!Object.keys(perArea).length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada catatan listrik pada rentang ini.</p>
    </Kartu>
  </div>
</template>
