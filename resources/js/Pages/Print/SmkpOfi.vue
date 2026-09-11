<script setup lang="ts">
/**
 * Lembar Peluang Perbaikan (OFI), siap cetak dan ditandatangani.
 *
 * Berdiri sendiri dari Rekapitulasi Ketidaksesuaian, dan pemisahannya
 * disengaja: lembar ini menyatakan apa yang SUDAH memenuhi seluruh
 * kriteria. Digabung ke dalam satu lembar temuan, pembacanya harus
 * memilah sendiri baris mana yang menuntut perbaikan wajib dan mana
 * yang sekadar saran — dan yang membacanya sedang menyusun rencana
 * tindak lanjut dengan tenggat.
 */
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  audit: any;
  baris: any[];
  rekap: any;
  dok?: Record<string, any> | null;
  ekspor?: string;
  kembali?: string;
}>();

const baris = computed<any[]>(() => props.baris ?? []);
</script>

<template>
  <Head title="Lembar Peluang Perbaikan (OFI)" />

  <PrintShell title="Lembar Peluang Perbaikan (OFI)" :kembali="props.kembali">
    <section class="bg-white p-5 print:p-0">
      <KopCetak :dok="props.dok" />

      <header class="border-b-[3px] border-cam-lime-deep pb-3 mb-4">
        <div class="flex justify-between items-end">
          <div>
            <h1 class="text-lg font-bold">Peluang Perbaikan — Opportunity For Improvement</h1>
            <p class="text-[11px] text-stone-500">
              Audit SMKP {{ props.audit?.tahun }}
              <span v-if="props.audit?.company?.name"> · {{ props.audit.company.name }}</span>
            </p>
          </div>

          <div class="text-right text-[10px] text-stone-500">
            Dicetak {{ new Date().toLocaleString('id-ID') }}<br>
            Nilai akhir <b>{{ props.rekap?.skor ?? 0 }}%</b> · {{ props.rekap?.tingkat?.label ?? '—' }}<br>
            Total <b>{{ baris.length }}</b> peluang
          </div>
        </div>

        <p class="text-[10px] text-stone-500 mt-2 leading-relaxed">
          Seluruh butir di bawah ini <b>telah memenuhi kriteria sepenuhnya</b> (capaian 100%).
          Catatan pada lembar ini adalah peluang peningkatan, <b>bukan ketidaksesuaian</b>, dan
          tidak menurunkan nilai audit maupun menuntut tindakan perbaikan wajib.
          Ketidaksesuaian dilaporkan terpisah pada Formulir Rekapitulasi Ketidaksesuaian.
        </p>
      </header>

      <div class="overflow-x-auto">
        <table class="w-full text-[11px] min-w-[760px]">
          <thead>
            <tr class="bg-stone-100 text-left text-[9px] uppercase text-stone-500">
              <th class="p-2 w-8">No</th>
              <th class="p-2">Elemen</th>
              <th class="p-2">Sub-elemen</th>
              <th class="p-2 w-16">Kode</th>
              <th class="p-2">Peluang perbaikan</th>
              <th class="p-2">Saran auditor</th>
              <th class="p-2 w-24">PIC</th>
              <th class="p-2 w-20">Target</th>
              <th class="p-2 w-20">Status</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="b in baris" :key="b.id" class="border-b border-stone-100 align-top">
              <td class="p-2">{{ b.no }}</td>
              <td class="p-2">{{ b.elemen }}</td>
              <td class="p-2">{{ b.sub }}</td>
              <td class="p-2 font-bold">
                {{ b.kode }}
                <small class="block font-normal text-stone-500">{{ b.lingkup_label }}</small>
                <small v-if="b.gugur" class="block font-normal text-stone-500">tak lagi 100%</small>
              </td>
              <td class="p-2">{{ b.uraian }}</td>
              <td class="p-2">{{ b.saran || '—' }}</td>
              <td class="p-2">{{ b.penanggung_jawab || '—' }}</td>
              <td class="p-2">{{ b.target || '—' }}</td>
              <td class="p-2">{{ b.status_label }}</td>
            </tr>

            <tr v-if="!baris.length">
              <td colspan="9" class="p-8 text-center text-stone-400">
                Belum ada peluang perbaikan yang dicatat pada audit ini.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <section class="pt-10 grid gap-8 sm:grid-cols-2 text-center text-[11px]">
        <div>
          Ketua Tim Auditor
          <div class="h-14"></div>
          <b class="block border-t border-stone-400 pt-1">
            {{ props.audit?.ketua_auditor || '&nbsp;' }}
          </b>
        </div>
        <div>
          Kepala Teknik Tambang
          <div class="h-14"></div>
          <b class="block border-t border-stone-400 pt-1">&nbsp;</b>
        </div>
      </section>
    </section>
  </PrintShell>
</template>
