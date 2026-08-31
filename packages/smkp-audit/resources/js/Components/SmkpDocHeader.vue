<script setup lang="ts">
/*
  Kop dokumen terkendali, satu per lembar.

  Kop dicetak ulang pada TIAP lembar karena nomor halamannya berubah, dan
  peramban tidak dapat menghitungnya sendiri saat mencetak — `counter(page)`
  tidak didukung pada konteks ini. Karena itu pemenggalan halaman ditentukan
  oleh berkas cetaknya, bukan diserahkan ke peramban, sehingga "Halaman 2
  dari 3" pada kop selalu benar.

  Bentuk kop mengikuti berkas audit nyata: blok logo, judul formulir, dan
  tabel identitas dokumen di kanan, lalu divisi dan departemen sebagai baris
  bawah.
*/
const props = defineProps<{
  dok?: Record<string, any> | null;
  title?: string;
  halaman?: number | string;
  dari?: number | string;
}>();

/* Tanggal datang sebagai 'Y-m-d' dari server; kop menampilkannya dalam
   bentuk Indonesia. Nilai yang tidak terbaca ditulis sebagai em dash, bukan
   'Invalid Date'. */
const tanggal = (nilai: unknown) => {
  if (!nilai) return '—';
  const t = new Date(String(nilai));
  return Number.isNaN(t.getTime())
    ? '—'
    : t.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
};

const baris = () => [
  ['No. Dokumen', props.dok?.nomor ?? '—'],
  ['Tgl Penerbitan', tanggal(props.dok?.terbit)],
  ['Tgl Persetujuan', tanggal(props.dok?.setuju)],
  ['No. Revisi', props.dok?.revisi ?? '00'],
  ['Halaman', `${props.halaman ?? 1} dari ${props.dari ?? 1}`],
];
</script>

<template>
  <table class="w-full border-collapse text-[10.5px] mb-5">
    <tbody>
      <tr>
        <td class="border border-stone-300 p-2 w-[16%] text-center align-middle">
          <img v-if="props.dok?.logo" :src="props.dok.logo" alt="" class="h-10 w-auto max-w-full object-contain mx-auto">
          <div v-else class="text-[12px] font-black leading-tight">{{ props.dok?.perusahaan ?? '—' }}</div>
        </td>

        <td class="border border-stone-300 p-2 text-center align-middle">
          <div class="font-bold uppercase tracking-wide text-stone-500 text-[9.5px]">{{ props.dok?.jenis ?? 'DOKUMEN' }}</div>
          <div class="font-bold uppercase leading-snug mt-1 text-[11px]">{{ props.dok?.judul ?? props.title }}</div>
        </td>

        <td class="border border-stone-300 p-2 w-[34%] align-middle">
          <table class="w-full">
            <tbody>
              <tr v-for="[kunci, nilai] in baris()" :key="kunci">
                <td class="text-stone-500 whitespace-nowrap py-px">{{ kunci }}</td>
                <td class="text-stone-400 px-1 py-px">:</td>
                <td class="font-semibold py-px">{{ nilai }}</td>
              </tr>
            </tbody>
          </table>
        </td>
      </tr>

      <tr>
        <td colspan="3" class="border border-stone-300 px-2 py-1.5">
          <div><span class="text-stone-500 inline-block w-24">Divisi</span><span class="text-stone-400">:</span> {{ props.dok?.divisi ?? '—' }}</div>
          <div><span class="text-stone-500 inline-block w-24">Departemen</span><span class="text-stone-400">:</span> {{ props.dok?.departemen ?? '—' }}</div>
        </td>
      </tr>
    </tbody>
  </table>
</template>
