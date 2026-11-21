<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Lencana from '../../Components/Energi/Lencana.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import { tgl } from '../../energi';

const props = propHalaman();
</script>

<template>
  <Head title="Kalender Kepatuhan" />
  <div class="space-y-4">
    <div class="flex justify-end"><PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" :nama="props.namaPerusahaan" /></div>
    <Kartu judul="Kewajiban energi & GRK">
      <table class="w-full text-[12px]">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Kewajiban</th><th class="py-2">Dasar hukum</th><th class="py-2">Tahun lapor</th><th class="py-2">Tenggat</th><th class="py-2">Status</th><th class="py-2"></th></tr></thead>
        <tbody>
          <tr v-for="k in props.kewajiban" :key="k.kode" class="border-b border-stone-50 align-top">
            <td class="py-2 font-semibold">{{ k.judul }}<span v-if="k.keterangan" class="block text-[10.5px] text-stone-400 font-normal">{{ k.keterangan }}</span></td>
            <td class="py-2 text-stone-500">{{ k.dasar }}</td><td class="py-2">{{ k.tahun_lapor ?? '—' }}</td>
            <td class="py-2">{{ tgl(k.tenggat) }}<span v-if="k.sisa_hari !== null && !['selesai', 'tidak_berlaku'].includes(k.status)" class="block text-[10.5px]" :class="k.sisa_hari < 0 ? 'text-red-600' : 'text-stone-400'">{{ k.sisa_hari < 0 ? `lewat ${-k.sisa_hari} hari` : `${k.sisa_hari} hari lagi` }}</span></td>
            <td class="py-2"><Lencana :status="k.status" /></td>
            <td class="py-2 text-right"><Link v-if="k.laporan_id" :href="`/energi/pelaporan/terbit/${k.laporan_id}`" class="eq-tautan">Laporan</Link><Link v-else-if="k.templat" href="/energi/pelaporan" class="eq-tautan">Susun</Link></td>
          </tr>
        </tbody>
      </table>
    </Kartu>
  </div>
</template>
