<script setup lang="ts">
/**
 * Keluaran 3 — Respon Manajemen atas Ketidaksesuaian.
 *
 * TERPISAH DARI TINDAKAN, dan itu bukan pemisahan administratif:
 * tindakan adalah apa yang akan dikerjakan, respon adalah apakah
 * temuannya diterima. Menyatukan keduanya menghapus kemungkinan
 * manajemen MENOLAK sebuah temuan — dan penolakan itu justru yang
 * paling perlu tercatat, sebab ia yang dibawa ke tingkat berikutnya.
 *
 * Karena itu tiap baris punya tiga keadaan, bukan dua: menerima,
 * menolak, dan belum menjawab. Yang belum menjawab digambar sebagai
 * kotak kosong untuk diisi tangan — lembar ini memang beredar sebagai
 * kertas sebelum jawabannya masuk ke sistem.
 */
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  audit: any;
  temuan: any[];
  meta?: any;
  dok?: any;
  kembali?: string;
}>();

const tanggal = (v: unknown) =>
  v ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
        .format(new Date(String(v)))
    : '—';
</script>

<template>
  <Head title="Respon Manajemen" />

  <PrintShell title="Respon Manajemen" :kembali="props.kembali">
    <section class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0">

      <KopCetak :dok="props.dok" :halaman="1" :dari="1" />

      <h1 class="text-center font-bold text-[16px] uppercase border-b border-stone-200 pb-4 mb-4">
        Respon Manajemen atas Ketidaksesuaian
      </h1>

      <p class="text-[11px] text-stone-600 mb-4">
        Diisi oleh pihak yang diaudit. Untuk tiap ketidaksesuaian, nyatakan apakah temuan
        <b>diterima</b> atau <b>ditolak</b> beserta alasannya. Pernyataan ini terpisah dari
        rencana tindak lanjut — temuan yang diterima tetap memerlukan tindakan, dan temuan
        yang ditolak dibawa ke pembahasan berikutnya.
      </p>

      <table class="w-full text-[11px] border border-stone-300 mb-5">
        <tbody>
          <tr>
            <td class="p-2 border-b border-r border-stone-200 w-36 text-stone-500">Perusahaan</td>
            <td class="p-2 border-b border-r border-stone-200 font-semibold">
              {{ props.audit?.company?.name || '—' }}
            </td>
            <td class="p-2 border-b border-r border-stone-200 w-28 text-stone-500">Tahun audit</td>
            <td class="p-2 border-b border-stone-200 font-semibold">{{ props.audit?.tahun }}</td>
          </tr>
        </tbody>
      </table>

      <article v-for="t in props.temuan" :key="t.id"
               class="border border-stone-300 rounded mb-3 break-inside-avoid">
        <div class="bg-stone-50 border-b border-stone-200 px-3 py-2 flex flex-wrap gap-x-3 text-[10.5px]">
          <b>{{ t.urut }}.</b>
          <b>{{ t.kode_kriteria }}</b>
          <span class="uppercase font-semibold">{{ t.jenis }}</span>
          <span class="ml-auto">{{ t.status }}</span>
        </div>

        <div class="p-3 text-[11px]">
          <p class="mb-2">{{ t.uraian }}</p>

          <table class="w-full border border-stone-200 text-[10.5px]">
            <tbody>
              <tr>
                <td class="p-2 border-b border-r border-stone-200 w-32 text-stone-500 align-top">
                  Tanggapan
                </td>
                <td class="p-2 border-b border-stone-200">
                  <!-- Tiga keadaan. Kotak dicentang bila sudah dijawab di
                       sistem; bila belum, keduanya kosong untuk diisi
                       tangan — lembar ini memang beredar sebagai kertas
                       sebelum jawabannya masuk. -->
                  <span class="inline-flex items-center gap-1.5 mr-5">
                    <span class="inline-block w-3 h-3 border border-stone-500 text-center leading-[11px] text-[9px]">
                      {{ t.respon_diterima === true ? '✓' : '' }}
                    </span>
                    Diterima
                  </span>
                  <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 border border-stone-500 text-center leading-[11px] text-[9px]">
                      {{ t.respon_diterima === false ? '✓' : '' }}
                    </span>
                    Ditolak
                  </span>
                </td>
              </tr>
              <tr>
                <td class="p-2 border-b border-r border-stone-200 text-stone-500 align-top">
                  Alasan / keterangan
                </td>
                <td class="p-2 border-b border-stone-200">
                  <template v-if="t.respon_manajemen">{{ t.respon_manajemen }}</template>
                  <template v-else>
                    <span class="block h-4 border-b border-dashed border-stone-300"></span>
                    <span class="block h-4 border-b border-dashed border-stone-300 mt-1"></span>
                  </template>
                </td>
              </tr>
              <tr>
                <td class="p-2 border-r border-stone-200 text-stone-500">Dinyatakan oleh</td>
                <td class="p-2">
                  {{ t.respon_oleh || '……………………………' }}
                  <span class="text-stone-500"> · {{ tanggal(t.respon_pada) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>

      <p v-if="!props.temuan?.length" class="p-8 text-center text-stone-400 text-[12px]">
        Tidak ada ketidaksesuaian yang memerlukan respon.
      </p>

      <section class="grid grid-cols-2 gap-8 text-[11px] mt-8">
        <div class="text-center">
          <p class="mb-14">Wakil manajemen auditi,</p>
          <p class="border-t border-stone-400 pt-1">………………………………</p>
        </div>
        <div class="text-center">
          <p class="mb-14">Ketua tim auditor,</p>
          <p class="font-bold border-t border-stone-400 pt-1">
            {{ props.audit?.ketua_auditor || '………………………………' }}
          </p>
        </div>
      </section>
    </section>
  </PrintShell>
</template>
