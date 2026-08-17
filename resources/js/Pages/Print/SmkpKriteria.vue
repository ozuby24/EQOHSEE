<script setup lang="ts">
/**
 * Keluaran 1 — Formulir Kriteria Audit.
 *
 * Lembar kerja auditor: dibawa ke lapangan, diisi tangan bila perlu,
 * lalu menjadi lampiran laporan. Berbeda dari Laporan Audit yang
 * meringkas per elemen, formulir ini menampilkan SELURUH butir.
 *
 * YANG DIKECUALIKAN TETAP DICETAK, dan itu disengaja. Butir yang hilang
 * dari lembar tidak dapat dibedakan antara "tidak berlaku" dan
 * "terlewat dinilai" — dan pembedaan itu justru yang ditanyakan
 * inspektur. Nilainya ditulis "N/A", bukan nol: nol berarti dinilai dan
 * gagal, N/A berarti tidak berlaku, dan menyamakan keduanya menurunkan
 * skor perusahaan atas butir yang memang tidak dapat berlaku baginya.
 */
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  audit: any;
  baris: Array<Record<string, any>>;
  rekap?: any;
  meta?: any;
  dok?: any;
  ekspor?: string;
  kembali?: string;
}>();

/* Dipecah per lembar di sisi klien, bukan diserahkan ke pemenggalan
   halaman peramban: kop dokumen terkendali harus terulang di TIAP
   lembar, dan itu hanya mungkin bila lembarnya memang elemen
   tersendiri. */
const PER_LEMBAR = 22;

const lembar = computed(() => {
  const out: any[][] = [];
  const rows = props.baris ?? [];

  for (let i = 0; i < rows.length; i += PER_LEMBAR) out.push(rows.slice(i, i + PER_LEMBAR));

  return out.length ? out : [[]];
});

/** Elemen hanya ditulis pada baris pertamanya — sisanya pengulangan. */
function elemenBaru(bagian: any[], i: number): boolean {
  return i === 0 || bagian[i].elemen !== bagian[i - 1].elemen;
}
</script>

<template>
  <Head title="Formulir Kriteria Audit SMKP" />

  <PrintShell title="Formulir Kriteria Audit SMKP" :kembali="props.kembali">
    <div class="mb-4 print:hidden">
      <a v-if="props.ekspor" :href="props.ekspor" class="eq-btn-lain !py-2 !text-[12px]">
        Unduh Excel (CSV)
      </a>
    </div>

    <section v-for="(bagian, hal) in lembar" :key="hal"
             class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0"
             :class="Number(hal) < lembar.length - 1 ? 'lembar-putus' : ''">

      <KopCetak :dok="props.dok" :halaman="Number(hal) + 1" :dari="lembar.length" />

      <template v-if="Number(hal) === 0">
        <h1 class="text-center font-bold text-[16px] uppercase border-b border-stone-200 pb-4 mb-4">
          Formulir Kriteria Audit SMKP
        </h1>
        <p class="text-center text-[11px] text-stone-500 -mt-2 mb-4">
          {{ props.meta?.basis || 'Sistem Manajemen Keselamatan Pertambangan Minerba' }}
        </p>

        <table class="w-full text-[11px] border border-stone-300 mb-4">
          <tbody>
            <tr>
              <td class="p-2 border-b border-r border-stone-200 w-36 text-stone-500">Perusahaan</td>
              <td class="p-2 border-b border-r border-stone-200 font-semibold">
                {{ props.audit?.company?.name || '—' }}
              </td>
              <td class="p-2 border-b border-r border-stone-200 w-28 text-stone-500">Tahun</td>
              <td class="p-2 border-b border-stone-200 font-semibold">{{ props.audit?.tahun }}</td>
            </tr>
            <tr>
              <td class="p-2 border-r border-stone-200 text-stone-500">Nilai akhir</td>
              <td class="p-2 border-r border-stone-200 font-bold">{{ props.rekap?.skor ?? 0 }}</td>
              <td class="p-2 border-r border-stone-200 text-stone-500">Tingkat</td>
              <td class="p-2">{{ props.rekap?.tingkat?.label || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </template>

      <table class="w-full text-[10px] border border-stone-300">
        <thead>
          <tr class="bg-stone-100 text-left">
            <th class="p-1.5 border-b border-stone-300 w-14">Kode</th>
            <th class="p-1.5 border-b border-stone-300">Uraian kriteria</th>
            <th class="p-1.5 border-b border-stone-300 w-24">Acuan</th>
            <th class="p-1.5 border-b border-stone-300 w-12 text-right">Maks</th>
            <th class="p-1.5 border-b border-stone-300 w-12 text-right">Nilai</th>
            <th class="p-1.5 border-b border-stone-300 w-32">Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(b, i) in bagian" :key="b.kode">
            <tr v-if="elemenBaru(bagian, Number(i))" class="bg-stone-50">
              <td colspan="6" class="p-1.5 font-bold border-b border-stone-200">{{ b.elemen }}</td>
            </tr>
            <tr class="border-b border-stone-100 align-top">
              <td class="p-1.5 font-semibold">{{ b.kode }}</td>
              <td class="p-1.5">{{ b.uraian }}</td>
              <td class="p-1.5 text-stone-500">{{ b.acuan || '—' }}</td>
              <td class="p-1.5 text-right">{{ b.maks }}</td>
              <td class="p-1.5 text-right font-semibold">
                {{ b.nilai === '' ? '·' : b.nilai }}
              </td>
              <td class="p-1.5 text-stone-500">{{ b.keterangan || '' }}</td>
            </tr>
          </template>

          <tr v-if="!bagian.length">
            <td colspan="6" class="p-8 text-center text-stone-400">
              Kriteria audit belum tersedia.
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Arti tandanya ditulis di lembar, bukan dihafal pembacanya. -->
      <p v-if="Number(hal) === lembar.length - 1" class="text-[9.5px] text-stone-500 mt-3">
        <b>·</b> belum dinilai · <b>N/A</b> tidak berlaku bagi perusahaan ini (tidak menurunkan skor)
        · <b>0</b> dinilai dan tidak memenuhi.
      </p>
    </section>
  </PrintShell>
</template>
