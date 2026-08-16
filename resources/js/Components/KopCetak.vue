<script setup lang="ts">
/**
 * Kop dokumen terkendali — satu bentuk untuk seluruh laporan.
 *
 * Sebelum komponen ini ada, kopnya disalin ke tiap halaman cetak, dan
 * hasilnya persis yang selalu terjadi pada markup yang disalin: dari
 * lima belas halaman, sepuluh punya kop penuh, tiga hanya punya
 * separuhnya — nama perusahaan tanpa nomor dokumen, tanggal, maupun
 * revisi — dan dua tidak punya kop sama sekali. Tidak satu pun
 * perbedaan itu disengaja; tidak satu pun menimbulkan galat.
 *
 * Logonya juga tidak pernah tergambar di mana pun. `KopDokumen` sudah
 * menghitungnya sejak lama dan mengirimkannya ke peramban pada tiap
 * laporan, lalu setiap halaman membuangnya — kop hanya menampilkan
 * NAMA perusahaan. Berkas yang diserahkan kepada auditor karena itu
 * tidak pernah membawa lambang siapa pun.
 *
 * Nomor dokumen boleh KOSONG. Perusahaan yang belum menetapkan
 * prefiksnya mencetak lembar tanpa nomor, dan itu disengaja: nomor
 * yang dikarang dari nama perusahaan terbaca seperti nomor sungguhan
 * lalu bertabrakan dengan penomoran mereka sendiri di daftar induk.
 * Kolomnya tetap ada supaya terlihat bahwa ia memang belum diisi.
 */
const props = defineProps<{
  dok?: Record<string, any> | null;
  /** Nomor halaman, untuk laporan berlembar-lembar. */
  halaman?: number;
  dari?: number;
}>();

const tanggal = (v: unknown) =>
  v ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
        .format(new Date(String(v)))
    : '—';
</script>

<template>
  <table class="w-full border-collapse text-[10px] mb-5">
    <tbody>
      <tr>
        <td class="border border-stone-300 p-2 w-[18%] text-center align-middle">
          <img v-if="props.dok?.logo" :src="props.dok.logo" alt=""
               class="mx-auto max-h-10 max-w-[90%] object-contain">
          <div class="font-bold" :class="props.dok?.logo ? 'mt-1 text-[9px]' : ''">
            {{ props.dok?.perusahaan || 'EQOHSEE' }}
          </div>
        </td>

        <td class="border border-stone-300 p-2 text-center align-middle">
          <div v-if="props.dok?.jenis" class="text-[9px] tracking-wide text-stone-500">
            {{ props.dok.jenis }}
          </div>
          <div class="font-bold">{{ props.dok?.judul }}</div>
          <div v-if="props.halaman" class="text-[9px] text-stone-500 mt-0.5">
            Halaman {{ props.halaman }} dari {{ props.dari }}
          </div>
        </td>

        <td class="border border-stone-300 p-0 w-[30%]">
          <table class="w-full border-collapse">
            <tbody>
              <tr>
                <td class="border-b border-r border-stone-300 p-1">No. Dokumen</td>
                <!-- Kosong dibiarkan kosong, bukan diisi tanda hubung:
                     tanda hubung terbaca sebagai "tidak berlaku",
                     sedangkan yang benar adalah "belum ditetapkan". -->
                <td class="border-b border-stone-300 p-1 font-semibold">{{ props.dok?.nomor }}</td>
              </tr>
              <tr>
                <td class="border-b border-r border-stone-300 p-1">Tgl Penerbitan</td>
                <td class="border-b border-stone-300 p-1">{{ tanggal(props.dok?.terbit) }}</td>
              </tr>
              <tr>
                <td class="border-b border-r border-stone-300 p-1">Tgl Persetujuan</td>
                <td class="border-b border-stone-300 p-1">{{ tanggal(props.dok?.setuju) }}</td>
              </tr>
              <tr>
                <td class="border-r border-stone-300 p-1">No. Revisi</td>
                <td class="p-1">{{ props.dok?.revisi }}</td>
              </tr>
            </tbody>
          </table>
        </td>
      </tr>
    </tbody>
  </table>
</template>
