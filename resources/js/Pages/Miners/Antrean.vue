<script setup lang="ts">
/**
 * Antrean persetujuan — layar "Data Outstanding …" Safe Track.
 *
 * Bawaannya "Perlu Approval Saya" (dokumen yang langkahnya sedang
 * menunggu pembuka halaman ini — angka yang sama dengan lonceng
 * tugasnya); "Semua" memperlihatkan seluruh yang masih berjalan. Kolom
 * "Menunggu" menyebut posisinya dengan kalimat yang sama dengan tabel
 * persetujuan di halaman detail.
 *
 * Daftar rujukan dan kartu siap cetak memakai halaman yang sama tanpa
 * saklar Saya/Semua.
 */
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import TabelData from '../../Components/Miners/TabelData.vue';
import LencanaStatus from '../../Components/Miners/LencanaStatus.vue';
import PilihSayaSemua from '../../Components/Miners/PilihSayaSemua.vue';
import { tglId } from '../../Components/Miners/zona';

const props = propHalaman();

const baris = computed<any[]>(() => (props.baris ?? []) as any[]);
const pilih = computed<any>(() => props.pilih ?? null);
const alamat = computed(() => `/miners/daftar/${props.jenis}`);

const kolom = [
  { kunci: 'nomor', label: 'No Registrasi' },
  { kunci: 'tanggal', label: 'Tanggal' },
  { kunci: 'judul', label: 'Title' },
  { kunci: 'perusahaan', label: 'Perusahaan' },
  { kunci: 'status', label: 'Status' },
  { kunci: 'oleh', label: 'Creator' },
  { kunci: 'posisi', label: 'Menunggu' },
];

const remah = computed<[string, string | null][]>(() => [
  ['Home', '/miners/dasbor'],
  [pilih.value ? 'Outstanding' : 'Data', null],
  [String(props.judul ?? '').replace('Data Outstanding ', ''), null],
]);
</script>

<template>
  <div>
    <KepalaHalaman :judul="props.judul" :remah="remah" />

    <TabelData :judul="props.tabel" :jumlah="baris.length" :kolom="kolom" :baris="baris"
               :kosong="pilih?.aktif === 'saya' ? 'Tidak ada dokumen yang menunggu persetujuan Anda.' : 'Tidak ada dokumen.'">
      <template v-if="pilih" #kanan>
        <PilihSayaSemua :saya="pilih.saya" :semua="pilih.semua" :aktif="pilih.aktif"
                        :href-saya="alamat" :href-semua="`${alamat}?semua=1`" />
      </template>

      <template #sel-nomor="{ baris: b }">
        <Link v-if="b.url" :href="b.url" class="mn-tautan">{{ b.nomor || 'tanpa nomor' }}</Link>
        <span v-else>{{ b.nomor || '—' }}</span>
      </template>
      <template #sel-tanggal="{ nilai }">{{ tglId(nilai) }}</template>
      <template #sel-status="{ nilai }"><LencanaStatus :status="nilai" /></template>
      <template #sel-posisi="{ nilai }"><span class="mn-redup text-[12px]">{{ nilai || '—' }}</span></template>
    </TabelData>
  </div>
</template>
