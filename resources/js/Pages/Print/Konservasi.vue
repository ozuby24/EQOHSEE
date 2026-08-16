<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok: any; tahun: number;
  ringkas: Record<string, number>;
  perKomoditas: Array<Record<string, any>>;
  records: Array<Record<string, any>>;
  tindak: Array<Record<string, any>>;
  kembali?: string;
}>();

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const persen = (v: unknown) => `${angka(v, 1)}%`;
const tanggal = (v: unknown) => v ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(String(v))) : '—';
</script>

<template>
  <Head title="Laporan Konservasi Mineral dan Batubara" />

  <PrintShell title="Laporan Konservasi Mineral dan Batubara" :kembali="props.kembali">
    <div class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">

      <KopCetak :dok="props.dok" />

      <header class="text-center border-b border-stone-200 pb-4 mb-5">
        <h1 class="font-bold text-[16px] uppercase">Laporan Konservasi Mineral dan Batubara</h1>
        <p class="text-[11px] text-stone-500 mt-1">Tahun {{ props.tahun }}</p>
        <p class="text-[10px] text-stone-400 mt-1">{{ props.dok?.divisi }} · {{ props.dok?.departemen }}</p>
      </header>

      <!--
        Jumlah yang belum ditinjau disebutkan, bukan disembunyikan. Laporan
        yang memuat empat dari sembilan catatan tetap sah dibaca asalkan
        pembacanya tahu.
      -->
      <section class="mb-5 rounded border border-stone-300 p-3 text-[10px] print:rounded-none"
               :class="Number(props.ringkas.belum_ditinjau) ? 'bg-amber-50' : ''">
        <b class="block mb-1">Dasar laporan</b>
        Disusun dari <b>{{ props.ringkas.jumlah_record }}</b> catatan konservasi yang telah ditinjau dan disetujui.
        <template v-if="Number(props.ringkas.belum_ditinjau)">
          <b>{{ props.ringkas.belum_ditinjau }}</b> catatan lain masih berstatus draf atau menunggu tinjauan
          dan tidak ikut dihitung pada laporan ini.
        </template>
      </section>

      <h3 class="font-bold text-[13px] mb-2">A. Ringkasan Konservasi</h3>
      <table class="w-full border-collapse text-[11px] mb-6">
        <tbody>
          <tr v-for="baris in [
            { k: 'Target produksi', v: angka(props.ringkas.target_produksi), s: '' },
            { k: 'Produksi aktual', v: angka(props.ringkas.produksi_aktual), s: `Capaian ${persen(props.ringkas.capaian_target)}` },
            { k: 'Material tergali', v: angka(props.ringkas.material_digali), s: '' },
            { k: 'Recovery', v: persen(props.ringkas.recovery), s: 'Produksi aktual terhadap material tergali' },
            { k: 'Kehilangan material', v: angka(props.ringkas.kehilangan_material), s: `${persen(props.ringkas.porsi_kehilangan)} dari yang tergali` },
            { k: 'Dilusi', v: angka(props.ringkas.dilusi), s: `${persen(props.ringkas.porsi_dilusi)} terhadap produksi` },
            { k: 'Stok akhir', v: angka(props.ringkas.stok_akhir), s: '' },
          ]" :key="baris.k" class="border-b border-stone-200">
            <td class="p-2 w-[35%]">{{ baris.k }}</td>
            <td class="p-2 font-semibold w-[20%]">{{ baris.v }}</td>
            <td class="p-2 text-stone-500">{{ baris.s }}</td>
          </tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">B. Kinerja per Komoditas</h3>
      <table class="w-full border-collapse text-[11px] mb-6">
        <thead>
          <tr class="border-b border-stone-300 text-left text-stone-500">
            <th class="p-2">Komoditas</th><th class="p-2 text-right">Target</th><th class="p-2 text-right">Aktual</th>
            <th class="p-2 text-right">Tergali</th><th class="p-2 text-right">Recovery</th>
            <th class="p-2 text-right">Kehilangan</th><th class="p-2 text-right">Catatan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="k in props.perKomoditas" :key="k.komoditas" class="border-b border-stone-100">
            <td class="p-2 font-semibold">{{ k.komoditas }}</td>
            <td class="p-2 text-right">{{ angka(k.target) }}</td>
            <td class="p-2 text-right">{{ angka(k.aktual) }}</td>
            <td class="p-2 text-right">{{ angka(k.digali) }}</td>
            <td class="p-2 text-right">{{ persen(k.recovery) }}</td>
            <td class="p-2 text-right">{{ angka(k.kehilangan) }}</td>
            <td class="p-2 text-right">{{ k.record }}</td>
          </tr>
          <tr v-if="!props.perKomoditas.length"><td colspan="7" class="p-4 text-center text-stone-400">Tidak ada data disetujui pada tahun ini.</td></tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">C. Rincian Catatan Konservasi</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead>
          <tr class="border-b border-stone-300 text-left text-stone-500">
            <th class="p-1.5">Periode</th><th class="p-1.5">Lokasi</th><th class="p-1.5">Komoditas</th>
            <th class="p-1.5 text-right">Target</th><th class="p-1.5 text-right">Aktual</th>
            <th class="p-1.5 text-right">Recovery</th><th class="p-1.5 text-right">Hilang</th>
            <th class="p-1.5 text-right">Dilusi</th><th class="p-1.5">Ditinjau</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in props.records" :key="i" class="border-b border-stone-100">
            <td class="p-1.5">{{ r.periode }}</td>
            <td class="p-1.5">{{ r.lokasi }}</td>
            <td class="p-1.5">{{ r.komoditas }}<span v-if="r.mineral_ikutan" class="block text-[9px] text-stone-500">ikutan: {{ r.mineral_ikutan }}</span></td>
            <td class="p-1.5 text-right">{{ angka(r.target_produksi) }}</td>
            <td class="p-1.5 text-right">{{ angka(r.produksi_aktual) }}</td>
            <td class="p-1.5 text-right">{{ persen(r.recovery) }}</td>
            <td class="p-1.5 text-right">{{ angka(r.kehilangan_material) }}</td>
            <td class="p-1.5 text-right">{{ angka(r.dilusi) }}</td>
            <td class="p-1.5 text-stone-500">{{ r.peninjau || '—' }}<span v-if="r.ditinjauPada" class="block text-[9px]">{{ r.ditinjauPada }}</span></td>
          </tr>
          <tr v-if="!props.records.length"><td colspan="9" class="p-4 text-center text-stone-400">Tidak ada catatan yang disetujui.</td></tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">D. Tindak Lanjut Terbuka</h3>
      <table class="w-full border-collapse text-[10px] mb-8">
        <thead>
          <tr class="border-b border-stone-300 text-left text-stone-500">
            <th class="p-1.5">Tindakan</th><th class="p-1.5">Kategori</th>
            <th class="p-1.5">Penanggung Jawab</th><th class="p-1.5">Target Selesai</th><th class="p-1.5">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in props.tindak" :key="t.id" class="border-b border-stone-100 align-top">
            <td class="p-1.5"><b>{{ t.judul }}</b><span v-if="t.uraian" class="block text-stone-500">{{ t.uraian }}</span></td>
            <td class="p-1.5">{{ t.kategori || '—' }}</td>
            <td class="p-1.5">{{ t.penanggung_jawab || '—' }}</td>
            <td class="p-1.5">{{ t.target_selesai || '—' }}</td>
            <td class="p-1.5" :class="t.terlambat ? 'font-bold' : ''">{{ t.statusLabel }}</td>
          </tr>
          <tr v-if="!props.tindak.length"><td colspan="5" class="p-4 text-center text-stone-400">Tidak ada tindak lanjut terbuka.</td></tr>
        </tbody>
      </table>

      <section class="pt-6 grid gap-8 sm:grid-cols-2 text-center text-[11px]">
        <div>Disusun oleh<div class="h-16"></div><b class="block border-t border-stone-400 pt-1">&nbsp;</b><span class="text-[10px] text-stone-500">Pengawas Konservasi</span></div>
        <div>Disetujui oleh<div class="h-16"></div><b class="block border-t border-stone-400 pt-1">&nbsp;</b><span class="text-[10px] text-stone-500">Kepala Teknik Tambang</span></div>
      </section>
    </div>
  </PrintShell>
</template>
