<script setup lang="ts">
/**
 * Upload Data Lama — `data_lama/index.blade.php` Safe Track (Master Data).
 *
 * Dua templat: Mine Permit (satu baris = satu karyawan; dibuatkan MCU,
 * induksi, dan permit terbit) dan SIMPER (satu baris = satu unit).
 * "Pratinjau" menjalankan pemeriksaan yang sama tanpa menyimpan apa pun;
 * "Unggah & simpan" menyimpan baris yang lolos. Hasilnya: ringkasan, lalu
 * daftar baris gagal beserta sebabnya, per nomor baris Excel.
 */
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import Kartu from '../../Components/Miners/Kartu.vue';
import Medan from '../../Components/Miners/Medan.vue';
import TabelData from '../../Components/Miners/TabelData.vue';
import KeadaanKosong from '../../Components/Miners/KeadaanKosong.vue';

const props = propHalaman();

const JENIS = computed<Record<string, string>>(() => (props.JENIS ?? {}) as Record<string, string>);
const KOLOM = computed<Record<string, string[]>>(() => (props.KOLOM ?? {}) as Record<string, string[]>);
const hasil = computed<any>(() => props.hasil ?? null);

const URAIAN: Record<string, string> = {
  permit: 'Satu baris satu karyawan yang Mine Permit-nya sudah terbit di lapangan. Dibuatkan data manpower, MCU (Fit, berlaku sampai "Masa Berlaku"), induksi lulus, dan Mine Permit terbit. NIK yang sudah ada dilewati. Foto diisi belakangan dari detail Mine Permit.',
  simper: 'Satu baris satu unit. NIK harus sudah punya Mine Permit (unggah templat Mine Permit lebih dulu). SIMPER terbitnya dipakai atau dibuatkan; Unit dan Class diisi berdua atau dikosongkan berdua. Class: F, R, P, atau I.',
};

const form = {
  permit: useForm({ berkas: null as File | null, pratinjau: false as boolean }),
  simper: useForm({ berkas: null as File | null, pratinjau: false as boolean }),
};

function pilih(jenis: 'permit' | 'simper', e: Event) {
  form[jenis].berkas = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function kirim(jenis: 'permit' | 'simper', pratinjau: boolean) {
  form[jenis].pratinjau = pratinjau;
  form[jenis].post(`/miners/data-lama/${jenis}`, { forceFormData: true, preserveScroll: true });
}

const kolomGagal = [
  { kunci: 'baris', label: 'Baris Excel' },
  { kunci: 'sebab', label: 'Sebab' },
];
const kolomBerhasil = [
  { kunci: 'baris', label: 'Baris Excel' },
  { kunci: 'nik', label: 'NIK' },
  { kunci: 'nama', label: 'Nama' },
  { kunci: 'keterangan', label: 'Yang dibuat' },
];
</script>

<template>
  <div>
    <KepalaHalaman :judul="props.judul" :remah="[['Home', '/miners/dasbor'], ['Master Data', null], ['Upload Data Lama', null]]" />

    <div class="mn-dua-kolom">
      <Kartu v-for="(label, j) in JENIS" :key="j" :judul="`Data Lama ${label}`" keterangan="templat Excel">
        <p class="mn-teks-redup mb-3">{{ URAIAN[j] }}</p>
        <p class="mn-sub-bagian">Kolom templat</p>
        <ul class="mn-chip-daftar mb-4" :aria-label="`Kolom templat ${label}`">
          <li v-for="k in KOLOM[j]" :key="k" class="mn-lencana mn-l-abu">{{ k }}</li>
        </ul>
        <a :href="`/miners/data-lama/templat/${j}`" class="mn-btn mn-btn-garis mb-4">Unduh templat {{ label }}</a>

        <form class="mn-form" @submit.prevent="kirim(j as 'permit' | 'simper', false)">
          <Medan :label="`Berkas Excel ${label}`" :untuk="`dl-${j}`" wajib penuh :galat="form[j as 'permit' | 'simper'].errors.berkas"
                 petunjuk="Format .xlsx sesuai templat, maksimal 10 MB. Baris contoh berhuruf miring boleh dibiarkan — dilewati.">
            <input :id="`dl-${j}`" type="file" class="mn-isian" accept=".xlsx,.xls,.csv" @change="pilih(j as 'permit' | 'simper', $event)">
          </Medan>
          <div class="flex flex-wrap gap-2">
            <button type="button" class="mn-btn mn-btn-garis" :disabled="form[j as 'permit' | 'simper'].processing || !form[j as 'permit' | 'simper'].berkas"
                    @click="kirim(j as 'permit' | 'simper', true)">Pratinjau (tanpa menyimpan)</button>
            <button type="submit" class="mn-btn mn-btn-utama" :disabled="form[j as 'permit' | 'simper'].processing || !form[j as 'permit' | 'simper'].berkas">Unggah &amp; simpan</button>
          </div>
        </form>
      </Kartu>
    </div>

    <template v-if="hasil">
      <Kartu :judul="hasil.pratinjau ? 'Pratinjau — belum disimpan' : 'Hasil unggah'" :keterangan="`${JENIS[hasil.jenis] ?? ''} · ${hasil.namaBerkas ?? ''}`">
        <div class="mn-alert" :class="hasil.pratinjau ? 'mn-alert-peringatan' : 'mn-alert-sukses'" role="status">
          <template v-if="hasil.pratinjau">Pratinjau tidak menyimpan apa pun. {{ hasil.berhasil.length }} dari {{ hasil.total }} baris akan disimpan bila diunggah; {{ hasil.gagal.length }} baris akan dilewati.</template>
          <template v-else>{{ hasil.berhasil.length }} dari {{ hasil.total }} baris tersimpan sebagai data lama; {{ hasil.gagal.length }} baris dilewati.</template>
        </div>
        <div class="mn-angka-ringkas">
          <div><b>{{ hasil.total }}</b><span>baris dibaca</span></div>
          <div class="mn-ok"><b>{{ hasil.berhasil.length }}</b><span>{{ hasil.pratinjau ? 'lolos pemeriksaan' : 'tersimpan' }}</span></div>
          <div class="mn-gagal"><b>{{ hasil.gagal.length }}</b><span>gagal / dilewati</span></div>
        </div>
      </Kartu>

      <TabelData v-if="hasil.gagal.length" judul="Baris gagal" :jumlah="hasil.gagal.length" :kolom="kolomGagal" :baris="hasil.gagal" />
      <TabelData v-if="hasil.berhasil.length" :judul="hasil.pratinjau ? 'Baris yang akan disimpan' : 'Baris tersimpan'"
                 :jumlah="hasil.berhasil.length" :kolom="kolomBerhasil" :baris="hasil.berhasil" />
      <Kartu v-if="!hasil.total" judul="Tidak ada baris"><KeadaanKosong teks="Berkas tidak berisi baris data selain judul dan contoh. Isi templat lalu unggah lagi." /></Kartu>
    </template>
  </div>
</template>
