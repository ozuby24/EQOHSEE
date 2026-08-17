<script setup lang="ts">
/**
 * Lembar Mine Permit — yang dibawa orangnya ke gerbang.
 *
 * DASARNYA IKUT TERCETAK, bukan hanya masa berlaku kartunya. Ketiganya
 * — MCU, induksi, dan kartunya sendiri — harus berlaku bersamaan, dan
 * lembar yang hanya menyebut satu di antaranya menyembunyikan dua sebab
 * lain seseorang dapat ditahan. Petugas gerbang yang memegang lembar
 * ini harus dapat memeriksanya sendiri tanpa membuka sistem.
 *
 * Tanggal kadaluarsa yang paling dekat ditandai, sebab itulah yang
 * sesungguhnya menentukan sampai kapan lembar ini berlaku — bukan
 * tanggal pada kartunya.
 */
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok: any;
  orang: Record<string, any>;
  kartu: Record<string, any>;
  dasar: { mcu: Record<string, any> | null; induksi: Record<string, any> | null };
  kembali?: string;
}>();

const tanggal = (v: unknown) => v
  ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
      .format(new Date(String(v)))
  : '—';

/**
 * Yang paling dekat habis di antara ketiganya.
 *
 * Inilah tanggal yang sesungguhnya membatasi lembar ini. Menyebut hanya
 * tanggal kartunya membuat orang mengira izinnya masih lama padahal
 * MCU-nya habis pekan depan.
 */
const berlakuSampai = computed(() => {
  const tgl = [
    props.kartu?.tglExpired,
    props.dasar?.mcu?.tglExpired,
    props.dasar?.induksi?.tglExpired,
  ].filter(Boolean).map((t) => String(t)).sort();

  return tgl[0] ?? null;
});

const dibatasiOleh = computed(() => {
  const s = berlakuSampai.value;
  if (!s) return null;

  if (s === props.dasar?.mcu?.tglExpired)     return 'MCU';
  if (s === props.dasar?.induksi?.tglExpired) return 'induksi';

  return null;
});
</script>

<template>
  <Head title="Mine Permit" />

  <PrintShell title="Mine Permit" :kembali="props.kembali">
    <div class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">

      <KopCetak :dok="props.dok" />

      <header class="text-center border-b border-stone-200 pb-4 mb-5">
        <h1 class="font-bold text-[16px] uppercase">Mine Permit</h1>
        <p class="text-[11px] text-stone-500 mt-1">Izin Masuk Area Pertambangan</p>
        <p v-if="props.kartu?.nomor" class="text-[11px] font-semibold mt-1">
          No. {{ props.kartu.nomor }}
        </p>
      </header>

      <!-- identitas -->
      <section class="mb-5">
        <table class="w-full text-[11px]">
          <tbody>
            <tr v-for="r in [
                  ['Nama', props.orang?.nama],
                  ['NIK', props.orang?.nik],
                  ['No. register', props.orang?.register],
                  ['Jabatan', props.orang?.jabatan],
                  ['Departemen', props.orang?.departemen],
                  ['Klasifikasi', props.orang?.klasifikasi],
                  ['Perusahaan', props.orang?.perusahaan],
                ]" :key="r[0] as string" class="align-top">
              <td class="py-1 pr-3 w-36 text-stone-500">{{ r[0] }}</td>
              <td class="py-1 font-semibold">{{ r[1] || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- masa berlaku, dengan pembatas yang sesungguhnya -->
      <section class="mb-5 rounded border border-stone-300 p-3 text-[11px] print:rounded-none">
        <div class="flex flex-wrap justify-between gap-3">
          <div>
            <b class="block mb-1">Berlaku sampai</b>
            <span class="text-[14px] font-bold">{{ tanggal(berlakuSampai) }}</span>
          </div>
          <div class="text-right">
            <b class="block mb-1">Terbit</b>
            {{ tanggal(props.kartu?.tglTerbit) }}
            <span v-if="props.kartu?.sebab && props.kartu.sebab !== 'Terbit'">
              · {{ props.kartu.sebab }}
            </span>
          </div>
        </div>

        <p v-if="dibatasiOleh" class="mt-2 pt-2 border-t border-stone-200">
          Dibatasi oleh masa berlaku <b>{{ dibatasiOleh }}</b>, bukan oleh kartunya
          (kartu berlaku sampai {{ tanggal(props.kartu?.tglExpired) }}).
          Izin ini gugur bersama yang mana pun habis lebih dulu.
        </p>

        <p v-if="props.kartu?.area" class="mt-2">
          <b>Area:</b> {{ props.kartu.area }}
        </p>
      </section>

      <!-- dasar penerbitan -->
      <section class="mb-6">
        <h2 class="font-bold text-[12px] uppercase mb-2">Dasar penerbitan</h2>

        <table class="w-full text-[10.5px] border border-stone-300 print:rounded-none">
          <thead>
            <tr class="bg-stone-100 text-left">
              <th class="px-2 py-1.5 border-b border-stone-300">Syarat</th>
              <th class="px-2 py-1.5 border-b border-stone-300">Tanggal</th>
              <th class="px-2 py-1.5 border-b border-stone-300">Hasil</th>
              <th class="px-2 py-1.5 border-b border-stone-300">Berlaku sampai</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="px-2 py-1.5 border-b border-stone-200 font-semibold">
                Pemeriksaan kesehatan (MCU)
              </td>
              <td class="px-2 py-1.5 border-b border-stone-200">{{ tanggal(props.dasar?.mcu?.tanggal) }}</td>
              <td class="px-2 py-1.5 border-b border-stone-200">{{ props.dasar?.mcu?.hasil || '—' }}</td>
              <td class="px-2 py-1.5 border-b border-stone-200">{{ tanggal(props.dasar?.mcu?.tglExpired) }}</td>
            </tr>
            <tr>
              <td class="px-2 py-1.5 font-semibold">Induksi keselamatan</td>
              <td class="px-2 py-1.5">{{ tanggal(props.dasar?.induksi?.tanggal) }}</td>
              <td class="px-2 py-1.5">{{ props.dasar?.induksi?.jenis || '—' }}</td>
              <td class="px-2 py-1.5">{{ tanggal(props.dasar?.induksi?.tglExpired) }}</td>
            </tr>
          </tbody>
        </table>

        <p v-if="props.dasar?.mcu?.penyelenggara" class="text-[10px] text-stone-500 mt-1.5">
          MCU oleh {{ props.dasar.mcu.penyelenggara }}<span
            v-if="props.dasar?.induksi?.pemberi">; induksi oleh {{ props.dasar.induksi.pemberi }}</span>.
        </p>
      </section>

      <!-- pengesahan -->
      <section class="grid grid-cols-2 gap-8 text-[11px] mt-8">
        <div class="text-center">
          <p class="mb-16">Pemegang izin,</p>
          <p class="font-bold border-t border-stone-400 pt-1">{{ props.orang?.nama }}</p>
          <p class="text-[10px] text-stone-500">{{ props.orang?.nik || '—' }}</p>
        </div>
        <div class="text-center">
          <p class="mb-16">Diverifikasi dan disahkan,</p>
          <p class="font-bold border-t border-stone-400 pt-1">
            {{ props.kartu?.peninjau || 'OHSE' }}
          </p>
          <p class="text-[10px] text-stone-500">
            OHSE<span v-if="props.kartu?.ditinjauPada"> · {{ tanggal(props.kartu.ditinjauPada) }}</span>
          </p>
        </div>
      </section>

      <p class="text-[9.5px] text-stone-500 mt-6 pt-3 border-t border-stone-200">
        Izin ini wajib dibawa selama berada di area pertambangan dan ditunjukkan bila diminta.
        Izin gugur dengan sendirinya apabila MCU atau induksi pemegangnya habis masa berlakunya,
        sekalipun tanggal pada kartu belum terlampaui.
      </p>
    </div>
  </PrintShell>
</template>
