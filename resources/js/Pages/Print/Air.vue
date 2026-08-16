<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok: any; dari: string; sampai: string;
  dasar: Record<string, number>;
  ringkas: Record<string, any>;
  kolam: Array<Record<string, any>>;
  mutu: Record<string, any>;
  catatan: Array<Record<string, any>>;
  tindak: Array<Record<string, any>>;
  kembali?: string;
}>();

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const persen = (v: unknown) => `${angka(v, 1)}%`;
const tanggal = (v: unknown) => v ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(String(v))) : '—';
const label = (v: string) => String(v || '').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const nilai = (v: unknown, d = 2) => v === null || v === undefined ? '—' : angka(v, d);
</script>

<template>
  <Head title="Laporan Pengelolaan Air dan Penirisan Tambang" />

  <PrintShell title="Laporan Pengelolaan Air dan Penirisan Tambang" :kembali="props.kembali">
    <div class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">

      <KopCetak :dok="props.dok" />

      <header class="text-center border-b border-stone-200 pb-4 mb-5">
        <h1 class="font-bold text-[16px] uppercase">Laporan Pengelolaan Air dan Penirisan Tambang</h1>
        <p class="text-[11px] text-stone-500 mt-1">Periode {{ tanggal(props.dari) }} — {{ tanggal(props.sampai) }}</p>
        <p class="text-[10px] text-stone-400 mt-1">{{ props.dok?.divisi }} · {{ props.dok?.departemen }}</p>
      </header>

      <section class="mb-5 rounded border border-stone-300 p-3 text-[10px] print:rounded-none"
               :class="Number(props.dasar?.belumDitinjau) ? 'bg-amber-50' : ''">
        <b class="block mb-1">Dasar laporan</b>
        Disusun dari <b>{{ props.dasar?.disetujui ?? 0 }}</b> catatan harian yang telah ditinjau dan disetujui,
        atas <b>{{ props.dasar?.kolam ?? 0 }}</b> kolam aktif.
        <template v-if="Number(props.dasar?.belumDitinjau)">
          <b>{{ props.dasar.belumDitinjau }}</b> catatan lain masih draf atau menunggu tinjauan dan tidak ikut dihitung.
        </template>
      </section>

      <h3 class="font-bold text-[13px] mb-2">A. Ringkasan Penirisan</h3>
      <table class="w-full border-collapse text-[11px] mb-6"><tbody>
        <tr v-for="b in [
          { k: 'Curah hujan total', v: `${angka(props.ringkas.hujanTotal, 1)} mm`, s: `Terbesar ${angka(props.ringkas.hujanMaks, 1)} mm dalam sehari` },
          { k: 'Air masuk', v: `${angka(props.ringkas.masuk)} m³`, s: '' },
          { k: 'Air dipompa keluar', v: `${angka(props.ringkas.keluar)} m³`, s: `${angka(props.ringkas.jamPompa, 1)} jam pemompaan` },
          { k: 'Debit rata-rata', v: `${angka(props.ringkas.debitPerJam)} m³/jam`, s: 'Per jam pompa berjalan, bukan per jam kalender' },
          { k: 'Energi per m³', v: `${nilai(props.ringkas.energiPerM3, 4)} kWh`, s: '' },
          { k: 'Pompa rusak', v: String(props.ringkas.pompaRusak ?? 0), s: 'Pada seluruh kolam aktif' },
        ]" :key="b.k" class="border-b border-stone-200">
          <td class="p-2 w-[30%]">{{ b.k }}</td>
          <td class="p-2 font-semibold w-[20%]">{{ b.v }}</td>
          <td class="p-2 text-stone-500">{{ b.s }}</td>
        </tr>
      </tbody></table>

      <h3 class="font-bold text-[13px] mb-2">B. Daya Tampung Kolam</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead><tr class="border-b border-stone-300 text-left text-stone-500">
          <th class="p-1.5">Kolam</th><th class="p-1.5">Jenis</th>
          <th class="p-1.5 text-right">Kapasitas m³</th><th class="p-1.5 text-right">Terisi</th>
          <th class="p-1.5 text-right">Tangkapan ha</th><th class="p-1.5 text-right">Sanggup menahan</th>
          <th class="p-1.5 text-right">Pompa siap</th>
        </tr></thead>
        <tbody>
          <tr v-for="s in props.kolam" :key="s.id" class="border-b border-stone-100">
            <td class="p-1.5"><b>{{ s.kode }}</b><span class="block text-stone-500">{{ s.nama }}</span></td>
            <td class="p-1.5">{{ label(s.jenis) }}</td>
            <td class="p-1.5 text-right">{{ angka(s.kapasitas_m3) }}</td>
            <td class="p-1.5 text-right" :class="s.terisiPersen >= 80 ? 'font-bold' : ''">{{ persen(s.terisiPersen) }}</td>
            <td class="p-1.5 text-right">{{ angka(s.luas_tangkapan_ha, 2) }}</td>
            <td class="p-1.5 text-right font-semibold">{{ angka(s.hujanTertampung, 0) }} mm</td>
            <td class="p-1.5 text-right">{{ angka(s.pompaSiap) }} m³/j<span v-if="s.pompaRusak" class="block">({{ s.pompaRusak }} rusak)</span></td>
          </tr>
          <tr v-if="!props.kolam.length"><td colspan="7" class="p-4 text-center text-stone-400">Belum ada kolam aktif.</td></tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">C. Kualitas Air</h3>
      <table class="w-full border-collapse text-[11px] mb-6"><tbody>
        <tr class="border-b border-stone-200"><td class="p-2 w-[30%]">Jumlah sampel</td><td class="p-2 font-semibold" colspan="2">{{ props.mutu?.sampel ?? 0 }}</td></tr>
        <tr v-for="q in [
          { k: 'pH rata-rata', v: nilai(props.mutu?.ph), s: 'Baku mutu 6,0 – 9,0' },
          { k: 'TSS rata-rata', v: `${nilai(props.mutu?.tss)} mg/L`, s: 'Baku mutu maksimum 400 mg/L' },
          { k: 'Besi (Fe) rata-rata', v: `${nilai(props.mutu?.fe, 3)} mg/L`, s: 'Baku mutu maksimum 7 mg/L' },
          { k: 'Mangan (Mn) rata-rata', v: `${nilai(props.mutu?.mn, 3)} mg/L`, s: 'Baku mutu maksimum 4 mg/L' },
        ]" :key="q.k" class="border-b border-stone-200">
          <td class="p-2">{{ q.k }}</td><td class="p-2 font-semibold w-[20%]">{{ q.v }}</td><td class="p-2 text-stone-500">{{ q.s }}</td>
        </tr>
      </tbody></table>

      <h3 class="font-bold text-[13px] mb-2">D. Rincian Catatan Harian</h3>
      <table class="w-full border-collapse text-[10px] mb-6">
        <thead><tr class="border-b border-stone-300 text-left text-stone-500">
          <th class="p-1.5">Tanggal</th><th class="p-1.5">Kolam</th>
          <th class="p-1.5 text-right">Hujan mm</th><th class="p-1.5 text-right">Volume m³</th>
          <th class="p-1.5 text-right">Keluar m³</th><th class="p-1.5 text-right">Jam pompa</th>
          <th class="p-1.5 text-right">pH</th><th class="p-1.5 text-right">TSS</th><th class="p-1.5">Ditinjau</th>
        </tr></thead>
        <tbody>
          <tr v-for="c in props.catatan" :key="c.id" class="border-b border-stone-100">
            <td class="p-1.5">{{ c.tanggalLabel }}</td>
            <td class="p-1.5">{{ c.sump }}</td>
            <td class="p-1.5 text-right">{{ angka(c.curah_hujan_mm, 1) }}</td>
            <td class="p-1.5 text-right">{{ angka(c.volume_m3) }}</td>
            <td class="p-1.5 text-right">{{ angka(c.debit_keluar_m3) }}</td>
            <td class="p-1.5 text-right">{{ angka(c.jam_pompa, 1) }}</td>
            <td class="p-1.5 text-right">{{ nilai(c.ph) }}</td>
            <td class="p-1.5 text-right">{{ nilai(c.tss_mgl) }}</td>
            <td class="p-1.5 text-stone-500">{{ c.alur?.peninjau || '—' }}</td>
          </tr>
          <tr v-if="!props.catatan.length"><td colspan="9" class="p-4 text-center text-stone-400">Tidak ada catatan yang disetujui.</td></tr>
        </tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">E. Tindak Lanjut Terbuka</h3>
      <table class="w-full border-collapse text-[10px] mb-8">
        <thead><tr class="border-b border-stone-300 text-left text-stone-500">
          <th class="p-1.5">Tindakan</th><th class="p-1.5">Penanggung Jawab</th><th class="p-1.5">Target</th><th class="p-1.5">Status</th>
        </tr></thead>
        <tbody>
          <tr v-for="t in props.tindak" :key="t.id" class="border-b border-stone-100 align-top">
            <td class="p-1.5"><b>{{ t.judul }}</b><span v-if="t.uraian" class="block text-stone-500">{{ t.uraian }}</span></td>
            <td class="p-1.5">{{ t.penanggung_jawab || '—' }}</td>
            <td class="p-1.5">{{ t.target_selesai || '—' }}</td>
            <td class="p-1.5" :class="t.terlambat ? 'font-bold' : ''">{{ t.statusLabel }}</td>
          </tr>
          <tr v-if="!props.tindak.length"><td colspan="4" class="p-4 text-center text-stone-400">Tidak ada tindak lanjut terbuka.</td></tr>
        </tbody>
      </table>

      <section class="pt-6 grid gap-8 sm:grid-cols-2 text-center text-[11px]">
        <div>Disusun oleh<div class="h-16"></div><b class="block border-t border-stone-400 pt-1">&nbsp;</b><span class="text-[10px] text-stone-500">Pengawas Penirisan</span></div>
        <div>Disetujui oleh<div class="h-16"></div><b class="block border-t border-stone-400 pt-1">&nbsp;</b><span class="text-[10px] text-stone-500">Kepala Teknik Tambang</span></div>
      </section>
    </div>
  </PrintShell>
</template>
