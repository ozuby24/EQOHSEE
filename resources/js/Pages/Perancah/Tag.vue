<script setup lang="ts">
/*
  Kartu scafftag siap cetak.

  Yang dicetak hanya kartunya: kepala halaman, menu, dan tombol tidak
  ikut ke kertas. Ukurannya ±90 mm supaya muat di holder tag standar.
*/
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import KartuTag from '../../Components/Perancah/KartuTag.vue';
import { tag as warnaTag, waktu } from '../../perancah';

const props = propHalaman();
const t = computed(() => props.tag ?? {});
const w = computed(() => warnaTag(t.value.kedaluwarsa ? 'kedaluwarsa' : t.value.warna));
const cetak = () => window.print();
</script>

<template>
  <Head :title="`Scafftag ${t.kode}`" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-2 tg-layar">
      <Link :href="`/perancah/${props.unit.id}`" class="text-[12.5px] font-semibold text-stone-500 hover:underline">← {{ props.unit.kode }} · {{ props.unit.judul }}</Link>
      <div class="flex gap-2">
        <button type="button" class="eq-btn-utama inline-flex items-center gap-2" @click="cetak"><Ikon nama="printer" :ukuran="16" />Cetak kartu</button>
        <a :href="t.verifikasi" target="_blank" rel="noopener" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="qr-code" :ukuran="16" />Halaman verifikasi</a>
      </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[400px_minmax(0,1fr)] items-start">
      <div class="tg-cetak"><KartuTag :tag="t" :inspektur="props.inspeksi?.inspektur" :sertifikat="props.inspeksi?.inspektur_sertifikat" /></div>
      <div class="space-y-4 tg-layar">
        <Kartu :judul="t.aktif ? 'Tag yang berlaku' : 'Tag ini sudah tidak berlaku'" :ket="t.label" ikon="tag" :warna="t.warna === 'hijau' ? 'hijau' : t.warna === 'kuning' ? 'amber' : 'merah'">
          <dl class="grid grid-cols-[140px_1fr] gap-y-1.5 text-[12.5px]">
            <dt class="text-stone-500">Status</dt><dd><span class="pr-pil" :class="w.kelas">{{ w.label }}</span></dd>
            <dt class="text-stone-500">Diterbitkan</dt><dd class="font-semibold">{{ waktu(t.terbit_at) }} · {{ t.oleh }}</dd>
            <dt class="text-stone-500">Berlaku sampai</dt><dd class="font-semibold">{{ t.warna === 'merah' ? 'Tidak berlaku sampai diganti' : waktu(t.kedaluwarsa_at) }}</dd>
            <template v-if="props.inspeksi">
              <dt class="text-stone-500">Hasil inspeksi</dt><dd class="font-semibold">{{ props.inspeksi.lulus }} lulus · {{ props.inspeksi.gagal_kritis }} gagal kritis · {{ props.inspeksi.gagal_minor }} gagal minor</dd>
            </template>
            <dt class="text-stone-500">Verifikasi QR</dt><dd class="font-semibold break-all text-[11.5px]">{{ t.verifikasi }}</dd>
          </dl>
        </Kartu>
        <Kartu judul="Cara memasang" ikon="info" warna="biru">
          <ol class="list-decimal pl-5 space-y-1 text-[12.5px] text-stone-600">
            <li>Cetak kartu ini dan masukkan ke holder tag di kaki tangga akses.</li>
            <li>Lepas kartu lama — tag lama sudah tidak berlaku di sistem.</li>
            <li>Pekerja memindai QR sebelum menggunakan perancah: halaman verifikasi selalu menampilkan tag yang BERLAKU saat itu.</li>
            <li v-if="t.warna !== 'merah'">Inspeksi ulang sebelum {{ waktu(t.kedaluwarsa_at) }}; sesudah itu tag otomatis menjadi merah.</li>
          </ol>
        </Kartu>
      </div>
    </section>
  </div>
</template>

<style scoped>
@media print {
  .tg-layar { display: none !important; }
  .tg-cetak { margin: 0 auto; }
}
</style>
