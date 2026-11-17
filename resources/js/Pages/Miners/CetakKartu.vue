<script setup lang="ts">
/**
 * Cetak Kartu — `kartu` (pilih.blade) Safe Track: daftar Mine Permit terbit
 * dan aktif, pilih sungguhan (centang / pilih semua), lalu cetak satu kartu
 * CR80 atau lembar A4 menurut tata cetak. Tata cetak disimpan per
 * perusahaan oleh admin/HSE IUP. Kartu tanpa foto ditandai dan dilewati.
 */
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import Kartu from '../../Components/Miners/Kartu.vue';
import TabelData from '../../Components/Miners/TabelData.vue';
import SelOrang from '../../Components/Miners/SelOrang.vue';
import LencanaMasaBerlaku from '../../Components/Miners/LencanaMasaBerlaku.vue';
import TombolIkon from '../../Components/Miners/TombolIkon.vue';
import KeadaanKosong from '../../Components/Miners/KeadaanKosong.vue';
import Medan from '../../Components/Miners/Medan.vue';

const props = propHalaman();

const baris = computed<any[]>(() => (props.baris ?? []) as any[]);
const cari = ref(String(props.cari ?? ''));
const pilih = ref<number[]>([]);

const bisa = computed(() => baris.value.filter((b) => b.foto).map((b) => b.id as number));
const semua = computed({
  get: () => bisa.value.length > 0 && bisa.value.every((id) => pilih.value.includes(id)),
  set: (v: boolean) => { pilih.value = v ? [...bisa.value] : []; },
});

function terapkan() {
  router.get('/miners/daftar/cetak-kartu', cari.value ? { cari: cari.value } : {}, { preserveState: true, preserveScroll: true });
}

const awal = (props.skema ?? {}) as Record<string, any>;
const tata = useForm({
  nama: 'Bawaan', skema: String(awal.skema ?? 'lipat'), kertas: String(awal.kertas ?? 'a4'), orientasi: String(awal.orientasi ?? 'landscape'),
  sela: Number(awal.sela ?? 8), sela_lipat: Number(awal.sela_lipat ?? 4), margin: Number(awal.margin ?? 10), garis_potong: Boolean(awal.garis_potong ?? true),
});

const urlLembar = computed(() => {
  const q = new URLSearchParams();
  pilih.value.forEach((id) => q.append('id[]', String(id)));
  for (const k of ['skema', 'kertas', 'orientasi', 'sela', 'sela_lipat', 'margin']) q.set(k, String(tata[k as 'skema']));
  q.set('garis_potong', tata.garis_potong ? '1' : '0');
  return `/miners/kartu/lembar?${q.toString()}`;
});

function simpanTata() {
  tata.post('/miners/kartu/tata-cetak', { preserveScroll: true });
}

const kolom = [
  { kunci: 'pilih', label: '' },
  { kunci: 'nama', label: 'Nama Employee' },
  { kunci: 'jabatan', label: 'Jabatan' },
  { kunci: 'perusahaan', label: 'Perusahaan' },
  { kunci: 'jenis', label: 'Type Card' },
  { kunci: 'berlaku', label: 'Masa Berlaku' },
  { kunci: 'aksi', label: '#' },
];
</script>

<template>
  <div>
    <KepalaHalaman judul="Cetak Kartu" :remah="[['Home', '/miners/dasbor'], ['Monitoring', null], ['Cetak Kartu', null]]" />

    <Kartu judul="Tata Cetak" keterangan="Kartu CR80 54 × 86 mm pada lembar kertas">
      <div class="mn-form">
        <Medan label="Skema" untuk="t-skema">
          <select id="t-skema" v-model="tata.skema" class="mn-isian" title="Skema">
            <option v-for="(l, k) in props.pilihanSkema" :key="k" :value="k">{{ l }}</option>
          </select>
        </Medan>
        <Medan label="Kertas" untuk="t-kertas">
          <select id="t-kertas" v-model="tata.kertas" class="mn-isian" title="Kertas">
            <option value="a4">A4</option><option value="letter">Letter</option><option value="f4">F4</option>
          </select>
        </Medan>
        <Medan label="Orientasi" untuk="t-orien">
          <select id="t-orien" v-model="tata.orientasi" class="mn-isian" title="Orientasi">
            <option value="landscape">Mendatar</option><option value="portrait">Tegak</option>
          </select>
        </Medan>
        <Medan label="Sela antarkartu (mm)" untuk="t-sela"><input id="t-sela" v-model="tata.sela" type="number" step="0.5" min="0" max="25" class="mn-isian"></Medan>
        <Medan label="Sela lipat (mm)" untuk="t-lipat"><input id="t-lipat" v-model="tata.sela_lipat" type="number" step="0.5" min="0" max="12" class="mn-isian"></Medan>
        <Medan label="Margin (mm)" untuk="t-margin"><input id="t-margin" v-model="tata.margin" type="number" step="0.5" min="3" max="30" class="mn-isian"></Medan>
        <Medan label="Garis potong" untuk="t-garis">
          <label class="mn-cek"><input id="t-garis" v-model="tata.garis_potong" type="checkbox"> Cetak garis potong</label>
        </Medan>
      </div>
      <div class="mt-3 flex flex-wrap gap-2">
        <a :href="pilih.length ? urlLembar : undefined" target="_blank" rel="noopener" class="mn-btn mn-btn-utama"
           :class="{ 'mn-nonaktif': !pilih.length }" :aria-disabled="!pilih.length">Cetak {{ pilih.length }} kartu terpilih</a>
        <button v-if="props.kelola" type="button" class="mn-btn mn-btn-garis" :disabled="tata.processing" @click="simpanTata">Simpan tata cetak</button>
      </div>
    </Kartu>

    <KeadaanKosong v-if="!baris.length && !cari" judul="Belum ada kartu siap cetak"
                   :teks="props.adaPermit ? 'Kartu muncul di sini sesudah Mine Permit disetujui KTT dan masih aktif.' : 'Belum ada Mine Permit. Buat lewat Register → Mine Permit.'" />

    <TabelData v-else judul="Kartu Siap Cetak" :jumlah="baris.length" :kolom="kolom" :baris="baris">
      <template #alat>
        <label class="mn-cek"><input v-model="semua" type="checkbox"> Pilih semua yang berfoto</label>
        <form class="mn-alat" role="search" @submit.prevent="terapkan">
          <input v-model="cari" class="mn-isian" placeholder="Nama, nomor, perusahaan" aria-label="Cari">
          <button type="submit" class="mn-btn mn-btn-garis">Cari</button>
        </form>
      </template>
      <template #sel-pilih="{ baris: b }">
        <input v-model="pilih" type="checkbox" :value="b.id" :disabled="!b.foto" :aria-label="`Pilih ${b.nama}`">
      </template>
      <template #sel-nama="{ baris: b }">
        <SelOrang :nama="b.nama" :sub="b.nomor" :href="`/miners/permit/${b.id}`" />
        <span v-if="!b.foto" class="mn-lencana mn-l-abu ml-1">tanpa foto</span>
      </template>
      <template #sel-berlaku="{ nilai }"><LencanaMasaBerlaku :tanggal="nilai" /></template>
      <template #sel-aksi="{ baris: b }">
        <TombolIkon v-if="b.foto" ikon="cetak" varian="garis" :href="`/miners/kartu/${b.id}`" judul="Cetak kartu ini" />
      </template>
    </TabelData>
  </div>
</template>
