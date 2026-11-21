<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Kartu from './Kartu.vue';
import KartuAngka from './KartuAngka.vue';
import Lencana from './Lencana.vue';
import { n, rp, tgl } from '../../energi';

const props = defineProps<{ r: Record<string, any>; peringkat: any[]; perKategori: Record<string, any>; recon: Record<string, any> }>();

const ragRekon = () => props.recon?.persen === null || props.recon?.persen === undefined || !props.recon?.disalurkan ? null
  : Math.abs(Number(props.recon.persen)) <= 2 ? 'hijau' : Math.abs(Number(props.recon.persen)) <= 3 ? 'kuning' : 'merah';
</script>

<template>
  <div class="space-y-4">
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <KartuAngka label="Solar terpakai" :nilai="r.liter" satuan="L" :desimal="0" :catatan="`Alat ${n(r.liter_alat, 0)} L · genset ${n(r.liter_genset, 0)} L`" />
      <KartuAngka label="Solar per ton" :nilai="r.l_ton" satuan="L/ton" :desimal="3" />
      <KartuAngka label="Solar per BCM" :nilai="r.l_bcm" satuan="L/BCM" :desimal="3" />
      <KartuAngka label="Selisih rekonsiliasi" :nilai="recon.disalurkan ? recon.persen : null" satuan="%" :rag="ragRekon()"
                  :catatan="recon.disalurkan ? `${n(recon.selisih, 0)} L dari ${n(recon.disalurkan, 0)} L terpakai menurut stok` : 'Belum ada rekonsiliasi pada rentang ini'" />
    </section>

    <section class="grid gap-4 lg:grid-cols-2">
      <Kartu judul="Konsumsi per kategori alat" ket="Liter per jam operasi dibandingkan antar kelompok alat.">
        <table class="w-full text-[12px]">
          <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Kategori</th><th class="py-2 text-right">Liter</th><th class="py-2 text-right">HM</th><th class="py-2 text-right">L/HM</th><th class="py-2 text-right">GJ</th></tr></thead>
          <tbody>
            <tr v-for="(k, kode) in perKategori" :key="kode" class="border-b border-stone-50">
              <td class="py-2 font-semibold">{{ k.nama }}</td><td class="py-2 text-right num">{{ n(k.liter, 0) }}</td>
              <td class="py-2 text-right num">{{ n(k.hm, 1) }}</td><td class="py-2 text-right num">{{ n(k.l_hm, 2) }}</td><td class="py-2 text-right num">{{ n(k.gj, 1) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-if="!Object.keys(perKategori).length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada catatan solar pada rentang ini.</p>
      </Kartu>

      <Kartu judul="Unit paling haus" ket="Dibandingkan terhadap rata-rata kategorinya sendiri, bukan angka mutlak.">
        <table class="w-full text-[12px]">
          <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Unit</th><th class="py-2 text-right">L/HM</th><th class="py-2 text-right">Acuan</th><th class="py-2 text-right">Biaya</th><th class="py-2">Status</th></tr></thead>
          <tbody>
            <tr v-for="u in peringkat" :key="u.unit?.id" class="border-b border-stone-50">
              <td class="py-2"><Link :href="`/energi/alat/${u.unit?.id}`" class="font-semibold text-cam-lime-deep">{{ u.unit?.kode }}</Link></td>
              <td class="py-2 text-right num">{{ n(u.l_hm, 2) }}</td><td class="py-2 text-right num">{{ n(u.acuan, 2) }}</td>
              <td class="py-2 text-right num">{{ rp(u.rupiah) }}</td>
              <td class="py-2"><span class="text-[11px] font-bold" :style="{ color: u.status?.warna }">{{ u.status?.label }}</span></td>
            </tr>
          </tbody>
        </table>
      </Kartu>
    </section>

    <Kartu judul="Rekonsiliasi tangki" ket="Terpakai menurut stok (stok awal + disalurkan − stok akhir) dibandingkan dengan yang tercatat per unit.">
      <table class="w-full text-[12px]">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Tanggal</th><th class="py-2 text-right">Stok awal</th><th class="py-2 text-right">Disalurkan</th><th class="py-2 text-right">Stok akhir</th><th class="py-2">Catatan</th><th class="py-2">Status</th></tr></thead>
        <tbody>
          <tr v-for="b in (recon.baris ?? [])" :key="b.id" class="border-b border-stone-50">
            <td class="py-2">{{ tgl(b.tanggal) }}</td><td class="py-2 text-right num">{{ n(b.stok_awal_liter, 0) }}</td>
            <td class="py-2 text-right num">{{ n(b.disalurkan_liter, 0) }}</td><td class="py-2 text-right num">{{ n(b.stok_akhir_liter, 0) }}</td>
            <td class="py-2 text-stone-500">{{ b.catatan ?? '—' }}</td><td class="py-2"><Lencana :status="b.status" /></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!(recon.baris ?? []).length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada rekonsiliasi. Isi lewat Input Lapangan.</p>
    </Kartu>
  </div>
</template>
