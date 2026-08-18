<script setup lang="ts">
/**
 * Keluaran 8 — Ketidaksesuaian dan Tindak Lanjutnya.
 *
 * Satu lembar per temuan, dengan foto sebelum dan sesudah. Inilah
 * berkas yang ditunjukkan saat penutupan temuan diperiksa.
 *
 * KEDUA FOTO BERDAMPINGAN PADA LEMBAR YANG SAMA. Memisahkannya ke dua
 * lembar membuat pembacanya harus mengingat yang pertama sambil
 * melihat yang kedua — dan yang diingat orang setelah membalik halaman
 * bukanlah keadaan sebuah lereng.
 *
 * Kotak foto tetap digambar walau fotonya belum ada, dengan tulisan
 * yang menyebut mana yang kurang. Kotak yang hilang membuat lembar
 * temuan tanpa bukti terlihat sama lengkapnya dengan yang berbukti.
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

/* Alamat bukti sudah jadi dari server — halaman ini tidak lagi
   menyusunnya sendiri. Menyusunnya di sini berarti menebak di disk mana
   berkasnya berada, dan tebakan itulah yang dulu membuat foto bukti
   dilayani sebagai berkas statis, tanpa melewati penjagaan apa pun. */
const berkas = (p: unknown) => (p ? String(p) : null);
</script>

<template>
  <Head title="Ketidaksesuaian dan Tindak Lanjut" />

  <PrintShell title="Ketidaksesuaian dan Tindak Lanjut" :kembali="props.kembali">

    <section v-for="(t, i) in props.temuan" :key="t.id"
             class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0"
             :class="Number(i) < props.temuan.length - 1 ? 'lembar-putus' : ''">

      <KopCetak :dok="props.dok" :halaman="Number(i) + 1" :dari="props.temuan.length" />

      <h1 class="text-center font-bold text-[15px] uppercase border-b border-stone-200 pb-3 mb-4">
        Ketidaksesuaian dan Tindak Lanjut
      </h1>

      <table class="w-full text-[11px] border border-stone-300 mb-4">
        <tbody>
          <tr>
            <td class="p-2 border-b border-r border-stone-200 w-32 text-stone-500">Perusahaan</td>
            <td class="p-2 border-b border-r border-stone-200 font-semibold">
              {{ props.audit?.company?.name || '—' }}
            </td>
            <td class="p-2 border-b border-r border-stone-200 w-24 text-stone-500">Kriteria</td>
            <td class="p-2 border-b border-stone-200 font-semibold">{{ t.kode_kriteria }}</td>
          </tr>
          <tr>
            <td class="p-2 border-r border-stone-200 text-stone-500">Jenis</td>
            <td class="p-2 border-r border-stone-200 uppercase font-semibold">{{ t.jenis }}</td>
            <td class="p-2 border-r border-stone-200 text-stone-500">Status</td>
            <td class="p-2 font-semibold">{{ t.status }}</td>
          </tr>
        </tbody>
      </table>

      <table class="w-full text-[11px] border border-stone-300 mb-4">
        <tbody>
          <tr>
            <td class="p-2 border-b border-r border-stone-200 w-32 text-stone-500 align-top">
              Uraian ketidaksesuaian
            </td>
            <td class="p-2 border-b border-stone-200">{{ t.uraian }}</td>
          </tr>
          <tr>
            <td class="p-2 border-b border-r border-stone-200 text-stone-500 align-top">Akar masalah</td>
            <td class="p-2 border-b border-stone-200">
              <template v-if="t.akar_masalah">{{ t.akar_masalah }}</template>
              <span v-else class="block h-4 border-b border-dashed border-stone-300"></span>
            </td>
          </tr>
          <tr>
            <td class="p-2 border-b border-r border-stone-200 text-stone-500 align-top">Tindakan perbaikan</td>
            <td class="p-2 border-b border-stone-200">
              <template v-if="t.tindakan">{{ t.tindakan }}</template>
              <span v-else class="block h-4 border-b border-dashed border-stone-300"></span>
            </td>
          </tr>
          <tr>
            <td class="p-2 border-r border-stone-200 text-stone-500">Penanggung jawab</td>
            <td class="p-2">
              {{ t.penanggung_jawab || '—' }}
              <span class="text-stone-500">
                · tenggat {{ tanggal(t.target_selesai) }}
                <template v-if="t.tanggal_selesai">· selesai {{ tanggal(t.tanggal_selesai) }}</template>
              </span>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- bukti: berdampingan, selalu dua kotak -->
      <h3 class="font-bold text-[12px] mb-2">Bukti</h3>
      <div class="grid grid-cols-2 gap-3 mb-4">
        <figure v-for="f in [
                  ['Sebelum (open)', t.foto_open],
                  ['Sesudah (closed)', t.foto_closed],
                ]" :key="f[0] as string"
                class="border border-stone-300 rounded overflow-hidden">
          <figcaption class="bg-stone-50 border-b border-stone-200 px-2 py-1 text-[10px] font-semibold">
            {{ f[0] }}
          </figcaption>
          <div class="h-44 grid place-items-center bg-stone-50">
            <img v-if="f[1]" :src="berkas(f[1]) as string" alt=""
                 class="max-h-44 max-w-full object-contain">
            <span v-else class="text-[10px] text-stone-400">Foto belum dilampirkan</span>
          </div>
        </figure>
      </div>

      <table class="w-full text-[11px] border border-stone-300">
        <tbody>
          <tr>
            <td class="p-2 border-b border-r border-stone-200 w-32 text-stone-500 align-top">
              Respon manajemen
            </td>
            <td class="p-2 border-b border-stone-200">
              <b v-if="t.respon_diterima === true">Diterima.</b>
              <b v-else-if="t.respon_diterima === false">Ditolak.</b>
              <span v-else class="text-stone-400">Belum dijawab.</span>
              {{ t.respon_manajemen }}
            </td>
          </tr>
          <tr>
            <td class="p-2 border-r border-stone-200 text-stone-500 align-top">Verifikasi penutupan</td>
            <td class="p-2">
              <template v-if="t.verifikasi">{{ t.verifikasi }}</template>
              <span v-else class="block h-4 border-b border-dashed border-stone-300"></span>
              <p class="text-stone-500 mt-1">
                Diverifikasi oleh {{ t.verifikasi_oleh || '………………………' }}
                · {{ tanggal(t.verifikasi_pada) }}
              </p>
            </td>
          </tr>
        </tbody>
      </table>

      <section class="grid grid-cols-3 gap-6 text-[10.5px] mt-6">
        <div class="text-center">
          <p class="mb-12">Auditor,</p>
          <p class="border-t border-stone-400 pt-1">………………………</p>
        </div>
        <div class="text-center">
          <p class="mb-12">Penanggung jawab tindakan,</p>
          <p class="border-t border-stone-400 pt-1">{{ t.penanggung_jawab || '………………………' }}</p>
        </div>
        <div class="text-center">
          <p class="mb-12">Verifikator,</p>
          <p class="border-t border-stone-400 pt-1">{{ t.verifikasi_oleh || '………………………' }}</p>
        </div>
      </section>
    </section>

    <section v-if="!props.temuan?.length"
             class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0">
      <KopCetak :dok="props.dok" :halaman="1" :dari="1" />
      <p class="p-10 text-center text-stone-400 text-[12px]">
        Tidak ada ketidaksesuaian tercatat pada periode audit ini.
      </p>
    </section>
  </PrintShell>
</template>
