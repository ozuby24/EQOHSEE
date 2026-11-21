<script setup lang="ts">
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Lencana from '../../Components/Energi/Lencana.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';
import { tgl } from '../../energi';

const props = propHalaman();
const pilih = reactive<Record<string, { tahun: number; bulan: number }>>({});
for (const t of props.templat ?? []) pilih[t.kode] = { tahun: t.periode === 'bulanan' ? new Date().getFullYear() : props.tahunBawaan, bulan: new Date().getMonth() || 12 };

const susun = (t: any) => router.post(`/energi/pelaporan/${t.kode}/susun`, { ...pilih[t.kode], perusahaan: props.perusahaan ?? undefined });
</script>

<template>
  <Head title="Pusat Pelaporan" />
  <div class="space-y-4">
    <div class="flex justify-end"><PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" :nama="props.namaPerusahaan" /></div>

    <Kartu judul="Templat laporan" ket="Setiap laporan disusun dari periode terkunci, berparaf bertahap (penyusun → penyetuju → pengesah), dan dibekukan sebagai snapshot ber-hash saat terbit. Format resmi wajib dicocokkan dengan naskah peraturannya sebelum disampaikan.">
      <div class="grid gap-3 md:grid-cols-2">
        <article v-for="t in props.templat" :key="t.kode" class="rounded-xl border border-stone-100 bg-stone-50 p-4 flex flex-col gap-2">
          <div class="flex items-start justify-between gap-2">
            <p class="text-[13px] font-bold text-cam-ink">{{ t.judul }}</p>
            <span class="text-[10px] font-bold text-stone-400 whitespace-nowrap">{{ t.kode }}</span>
          </div>
          <p class="text-[11px] text-stone-500">{{ t.dasar }}</p>
          <p class="text-[11px] text-stone-600">Periode {{ t.periode }} · tenggat: {{ t.tenggat }}</p>
          <form v-if="props.boleh.susun" class="flex flex-wrap items-end gap-2 mt-auto" @submit.prevent="susun(t)">
            <label class="text-[10.5px] font-semibold text-stone-500">Tahun<input v-model="pilih[t.kode].tahun" type="number" min="2000" max="2100" class="block mt-0.5 rounded-lg border-stone-200 text-[12px] w-24"></label>
            <label v-if="t.periode === 'bulanan'" class="text-[10.5px] font-semibold text-stone-500">Bulan<input v-model="pilih[t.kode].bulan" type="number" min="1" max="12" class="block mt-0.5 rounded-lg border-stone-200 text-[12px] w-20"></label>
            <button class="eq-btn-mini">Susun draf</button>
          </form>
        </article>
      </div>
    </Kartu>

    <Kartu judul="Laporan tersusun" ket="Versi baru menggantikan versi lama setelah terbit; versi lama tetap dapat dibuka.">
      <table class="w-full text-[12px]">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Laporan</th><th class="py-2">Periode</th><th class="py-2">Versi</th><th class="py-2">Nomor</th><th class="py-2">Status</th><th class="py-2">Disampaikan</th></tr></thead>
        <tbody>
          <tr v-for="l in props.laporan" :key="l.id" class="border-b border-stone-50">
            <td class="py-2"><Link :href="l.tautan" class="font-semibold text-cam-lime-deep">{{ l.judul }}</Link><span class="block text-[10.5px] text-stone-400">{{ l.penyusun ?? '' }}<template v-if="l.penyetuju"> · disetujui {{ l.penyetuju }}</template></span></td>
            <td class="py-2">{{ tgl(l.periode[0]) }} – {{ tgl(l.periode[1]) }}</td>
            <td class="py-2">v{{ l.versi }}</td><td class="py-2 text-[11px]">{{ l.nomor_dokumen ?? '—' }}</td>
            <td class="py-2"><Lencana :status="l.status" /></td><td class="py-2">{{ l.disampaikan ? '✔' : '—' }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="!props.laporan?.length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada laporan yang disusun.</p>
    </Kartu>

    <Kartu judul="Kewajiban terdekat">
      <template #aksi><Link href="/energi/kalender" class="eq-tautan">Kalender kepatuhan</Link></template>
      <ul class="divide-y divide-stone-100">
        <li v-for="k in props.kewajiban" :key="k.kode" class="py-2 flex items-center justify-between gap-3 text-[12px]">
          <span class="min-w-0"><b>{{ k.judul }}</b><span class="block text-[10.5px] text-stone-400">{{ k.dasar }} · tenggat {{ tgl(k.tenggat) }}</span></span>
          <Lencana :status="k.status" />
        </li>
      </ul>
    </Kartu>
  </div>
</template>
