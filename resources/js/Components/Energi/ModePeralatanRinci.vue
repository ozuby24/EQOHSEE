<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Kartu from './Kartu.vue';
import KartuAngka from './KartuAngka.vue';
import Lencana from './Lencana.vue';
import { n, tgl } from '../../energi';

defineProps<{ unit: Record<string, any>; log: any[]; m: Record<string, any>; acuan: number; status: Record<string, any> }>();
</script>

<template>
  <div class="space-y-4">
    <Kartu :judul="`${unit.kode} — ${unit.nama}`" :ket="`Kategori ${unit.kategori} · acuan kategori ${n(acuan, 2)} L/HM`">
      <template #aksi>
        <span class="text-[12px] font-bold" :style="{ color: status.warna }">{{ status.label }}</span>
        <Link href="/energi/alat" class="eq-btn-lain">Kembali ke peralatan</Link>
      </template>
      <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <KartuAngka label="Solar" :nilai="m.liter" satuan="L" :desimal="0" />
        <KartuAngka label="Jam operasi" :nilai="m.hm" satuan="HM" />
        <KartuAngka label="Liter per jam" :nilai="m.l_hm" satuan="L/HM" :desimal="2" />
        <KartuAngka label="Liter per ton" :nilai="m.l_ton" satuan="L/ton" :desimal="3" />
        <KartuAngka label="Liter per BCM" :nilai="m.l_bcm" satuan="L/BCM" :desimal="3" />
        <KartuAngka label="Idle" :nilai="m.idle_persen" satuan="%" :catatan="`${n(m.idle, 1)} jam`" />
        <KartuAngka label="Energi" :nilai="m.gj" satuan="GJ" />
        <KartuAngka label="Emisi" :nilai="m.tco2e" satuan="tCO₂e" :desimal="2" />
        <KartuAngka label="Biaya solar" :nilai="m.rupiah" satuan="Rp" :desimal="0" />
        <KartuAngka label="Tonase" :nilai="m.ton" satuan="ton" :desimal="0" />
        <KartuAngka label="Kecepatan rata-rata" :nilai="m.kecepatan" satuan="km/jam" />
        <KartuAngka label="Cycle time" :nilai="m.cycle" satuan="menit" :desimal="2" />
      </div>
    </Kartu>

    <Kartu judul="Catatan harian" ket="Status alur setiap baris: diajukan → terverifikasi → terkunci.">
      <table class="w-full text-[12px]">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Tanggal</th><th class="py-2 text-right">HM</th><th class="py-2 text-right">Liter</th><th class="py-2 text-right">L/HM</th><th class="py-2 text-right">Ton</th><th class="py-2 text-right">Idle</th><th class="py-2 text-right">FAME</th><th class="py-2">Sumber data</th><th class="py-2">Status</th></tr></thead>
        <tbody>
          <tr v-for="l in log" :key="l.id" class="border-b border-stone-50">
            <td class="py-2">{{ tgl(l.tanggal) }}</td><td class="py-2 text-right num">{{ n(l.hm, 1) }}</td><td class="py-2 text-right num">{{ n(l.liter, 0) }}</td>
            <td class="py-2 text-right num">{{ l.hm > 0 ? n(l.liter / l.hm, 2) : '—' }}</td><td class="py-2 text-right num">{{ n(l.ton, 0) }}</td>
            <td class="py-2 text-right num">{{ n(l.idle_jam, 1) }}</td><td class="py-2 text-right num">{{ l.persen_fame !== null ? n(l.persen_fame, 0) + '%' : 'bawaan' }}</td>
            <td class="py-2">{{ l.sumber_data ?? '—' }}</td><td class="py-2"><Lencana :status="l.status" /></td>
          </tr>
        </tbody>
      </table>
    </Kartu>
  </div>
</template>
