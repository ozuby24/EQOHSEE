<script setup lang="ts">
/**
 * Register pemantauan perusahaan jasa, siap cetak.
 *
 * Halaman cetak selalu di atas kertas putih; mode gelap tidak berlaku
 * di sini, dan memaksanya justru salah — lihat Tests\Feature\ModeGelapTest,
 * yang memang melewati seluruh berkas di bawah Pages/Print.
 */
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok?: Record<string, any> | null;
  data: any[];
  kembali?: string;
}>();

/* Diulang lewat computed, bukan lewat props.data langsung. Nomor
   barisnya diambil dari indeks perulangan, dan Tests\Feature\LabelMentahTest
   menolak indeks maupun kunci objek prop yang digambar sebagai teks —
   aturan yang menutup kebocoran kunci internal ke layar, dan yang
   bentuknya tidak dapat dibedakan dari nomor baris tanpa membaca
   maksudnya. */
const baris = computed<any[]>(() => props.data ?? []);

const angka = (v: number | null | undefined) => (v === null || v === undefined ? '—' : v);
</script>

<template>
  <Head title="Register Perusahaan Jasa Pertambangan" />

  <PrintShell title="Register Perusahaan Jasa Pertambangan" :kembali="props.kembali">
    <section class="bg-white p-5 print:p-0">
      <KopCetak :dok="props.dok" />

      <header class="flex justify-between items-end border-b-[3px] border-cam-lime-deep pb-3 mb-4">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg lime-gradient text-white grid place-items-center font-black">E</div>
          <div>
            <h1 class="text-lg font-bold">Register Perusahaan Jasa Pertambangan</h1>
            <p class="text-[11px] text-stone-500">EQOHSEE · HSE Platform</p>
          </div>
        </div>

        <div class="text-right text-[10px] text-stone-500">
          Dicetak {{ new Date().toLocaleString('id-ID') }}<br>
          Total <b>{{ baris.length }}</b> mitra
        </div>
      </header>

      <div class="overflow-x-auto">
        <table class="w-full text-[11px] min-w-[720px]">
          <thead>
            <tr class="bg-stone-100 text-left text-[9px] uppercase text-stone-500">
              <th class="p-2 w-8">No</th>
              <th class="p-2">Perusahaan Jasa</th>
              <th class="p-2">NIB</th>
              <th class="p-2">Penanggung Jawab</th>
              <th class="p-2 text-right">SMKP</th>
              <th class="p-2 text-right">Pelaporan</th>
              <th class="p-2 text-right">Evaluasi</th>
              <th class="p-2 text-right">Achievement</th>
              <th class="p-2">Status</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(p, i) in baris" :key="i" class="border-b border-stone-100 align-top">
              <td class="p-2">{{ i + 1 }}</td>
              <td class="p-2 font-bold">{{ p.nama_perusahaan }}</td>
              <td class="p-2">{{ p.nib || '—' }}</td>
              <td class="p-2">{{ p.penanggung_jawab || '—' }}</td>
              <td class="p-2 text-right">{{ angka(p.smkp) }}</td>
              <td class="p-2 text-right">{{ angka(p.pelaporan) }}</td>
              <td class="p-2 text-right">{{ angka(p.evaluasi) }}</td>
              <td class="p-2 text-right font-bold">{{ angka(p.achievement) }}</td>
              <td class="p-2">{{ p.status }}</td>
            </tr>

            <tr v-if="!baris.length">
              <td colspan="9" class="p-8 text-center text-stone-400">Tidak ada data.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="mt-4 text-[10px] text-stone-500 leading-relaxed">
        Skor SMKP adalah kepatuhan daftar periksa prakualifikasi kategori A–P; Dokumen
        Legalitas merupakan syarat wajib terpisah dan tidak ikut dihitung. Achievement
        diambil dari skor TERENDAH di antara prakualifikasi, pelaporan, dan evaluasi —
        bukan rata-ratanya.
      </p>
    </section>
  </PrintShell>
</template>
