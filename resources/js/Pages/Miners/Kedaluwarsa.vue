<script setup lang="ts">
/**
 * Monitoring Masa Berlaku — `masa_berlaku/monitoring.blade.php` Safe Track
 * (v02): "Status Masa Berlaku" per modul, "Perlu Tindak Lanjut", lalu
 * sebaran per perusahaan untuk MCU, Induksi, Mine Permit, dan SIMPER.
 *
 * Setiap baris zona pada kartu status adalah tombol saring: mengkliknya
 * menampilkan seluruh dokumen modul itu di zona itu (klik lagi untuk
 * melepas). Zona selalu tampil sebagai ikon + angka + label, tidak
 * pernah warna saja.
 */
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import Kartu from '../../Components/Miners/Kartu.vue';
import SaringBar from '../../Components/Miners/SaringBar.vue';
import RekapPerusahaan from '../../Components/Miners/RekapPerusahaan.vue';
import TabelData from '../../Components/Miners/TabelData.vue';
import LencanaMasaBerlaku from '../../Components/Miners/LencanaMasaBerlaku.vue';
import SelOrang from '../../Components/Miners/SelOrang.vue';
import TombolIkon from '../../Components/Miners/TombolIkon.vue';
import BarSebaran from '../../Components/Miners/BarSebaran.vue';
import Ikon from '../../Components/Miners/Ikon.vue';
import { IKON_ZONA } from '../../Components/Miners/ikon';
import { URUTAN_ZONA, ZONA, type KunciZona, type MedanSaring } from '../../Components/Miners/zona';

const props = propHalaman();

const JENIS = computed<Record<string, string>>(() => (props.JENIS ?? {}) as Record<string, string>);
const KETERANGAN = computed<Record<string, string>>(() => (props.KETERANGAN ?? {}) as Record<string, string>);
const ringkas = computed<Record<string, Record<string, number>>>(() => (props.ringkas ?? {}) as any);
const rekap = computed<Record<string, any[]>>(() => (props.rekap ?? {}) as any);
const daftar = computed<any>(() => props.daftar ?? { data: [], links: [], from: 1, total: 0 });
const manpower = computed<any>(() => props.manpower ?? { total: 0, aktif: 0, nonaktif: 0 });
const perusahaan = computed<any[]>(() => (props.perusahaan ?? []) as any[]);

const saring = ref<Record<string, string>>({ ...(props.saring ?? {}) });

const medanSaring = computed<MedanSaring[]>(() => [
  { kunci: 'cari', label: 'Cari', placeholder: 'Nama, No ID, atau nomor dokumen', lebar: true },
  { kunci: 'perusahaan', label: 'Perusahaan', jenis: 'pilih', semua: 'Semua perusahaan', opsi: perusahaan.value, lebar: true },
  { kunci: 'jenis', label: 'Modul', jenis: 'pilih', semua: 'Semua modul',
    opsi: Object.entries(JENIS.value).map(([nilai, label]) => ({ nilai, label })) },
  { kunci: 'zona', label: 'Zona masa berlaku', jenis: 'pilih', semua: 'Semua zona',
    opsi: URUTAN_ZONA.map((z) => ({ nilai: z, label: ZONA[z].pendek })) },
]);

function kirim(q: Record<string, string>) {
  const isi: Record<string, string> = {};
  for (const [k, v] of Object.entries(q)) if (v) isi[k] = v;
  router.get('/miners/kedaluwarsa', isi, { preserveState: true, preserveScroll: true });
}

const terapkan = () => kirim(saring.value);

function bersihkan() {
  saring.value = { cari: '', perusahaan: '', jenis: '', zona: '' };
  kirim({});
}

/* Klik baris zona pada kartu status: saring modul + zona; klik baris
   yang sedang aktif melepasnya. */
function pilih(jenis: string, zona: KunciZona) {
  const aktif = props.saring?.jenis === jenis && props.saring?.zona === zona;
  saring.value = { ...saring.value, jenis: aktif ? '' : jenis, zona: aktif ? '' : zona };
  terapkan();
}

const aktifZona = (jenis: string, zona: string) => props.saring?.jenis === jenis && props.saring?.zona === zona;
const angka = (n: number | undefined) => (n ?? 0).toLocaleString('id-ID');

const alamatEkspor = computed(() => {
  const q = new URLSearchParams();
  for (const [k, v] of Object.entries(props.saring ?? {})) if (v) q.set(k, String(v));
  const s = q.toString();
  return `/miners/kedaluwarsa/ekspor${s ? `?${s}` : ''}`;
});

const judulDaftar = computed(() => {
  if (!props.disaring) return 'Perlu Tindak Lanjut';
  const bagian = [props.saring?.jenis ? JENIS.value[props.saring.jenis] : 'Semua modul'];
  if (props.saring?.zona) bagian.push(ZONA[props.saring.zona as KunciZona]?.pendek ?? '');
  return `Daftar Dokumen — ${bagian.join(' · ')}`;
});

const kolom = [
  { kunci: 'jenis', label: 'Modul' },
  { kunci: 'nama', label: 'Nama' },
  { kunci: 'perusahaan', label: 'Perusahaan' },
  { kunci: 'nomor', label: 'No Dokumen' },
  { kunci: 'berakhir', label: 'Berakhir' },
  { kunci: 'aksi', label: '#' },
];

const JUDUL_REKAP: Record<string, string> = {
  mcu: 'Manpower MCU per Perusahaan',
  induksi: 'Induksi per Perusahaan',
  permit: 'Mine Permit per Perusahaan',
  simper: 'SIMPER per Perusahaan',
};
</script>

<template>
  <div>
    <KepalaHalaman :judul="props.judul" :remah="[['Home', '/miners/dasbor'], ['Monitoring', null], ['Masa Berlaku', null]]" />

    <Kartu judul="Status Masa Berlaku" keterangan="MCU, Induksi, Mine Permit, SIMPER — klik zona untuk menyaring">
      <div class="mn-status-grid">
        <section v-for="(label, j) in JENIS" :key="j" class="mn-status" :aria-label="`Status masa berlaku ${label}`">
          <header class="mn-status-kepala">
            <h3 class="mn-status-judul">{{ label }}</h3>
            <span class="mn-status-sub"><b>{{ angka(ringkas[j]?.total) }}</b> dokumen · {{ KETERANGAN[j] }}</span>
          </header>
          <BarSebaran :n="ringkas[j] ?? {}" />
          <ul class="mn-status-daftar">
            <li v-for="z in URUTAN_ZONA" :key="z">
              <button type="button" class="mn-status-baris" :class="[`mn-zona-${z}`, { 'mn-aktif': aktifZona(String(j), z) }]"
                      :aria-pressed="aktifZona(String(j), z)" :aria-label="`${label}: ${ZONA[z].label}, ${angka(ringkas[j]?.[z])} dokumen`"
                      @click="pilih(String(j), z)">
                <span class="mn-zona-ikon"><Ikon :d="IKON_ZONA[z]" /></span>
                <span class="mn-status-lbl">{{ ZONA[z].label }}</span>
                <span class="mn-status-num">{{ angka(ringkas[j]?.[z]) }}</span>
              </button>
            </li>
          </ul>
        </section>
      </div>
      <p class="mn-status-catatan">
        Manpower terdaftar: <b>{{ angka(manpower.total) }}</b> orang — <b>{{ angka(manpower.aktif) }}</b> aktif,
        {{ angka(manpower.nonaktif) }} tidak aktif. Angka MCU di atas hanya menghitung karyawan yang masih aktif;
        Mine Permit dan SIMPER yang dinonaktifkan tidak dihitung.
      </p>
    </Kartu>

    <Kartu judul="Saring" keterangan="Cari orang atau dokumen, batasi per perusahaan, modul, dan zona">
      <SaringBar v-model="saring" :medan="medanSaring" @terapkan="terapkan" @bersihkan="bersihkan" />
    </Kartu>

    <TabelData :judul="judulDaftar" :jumlah="props.disaring ? daftar.total : props.mendesakTotal" :kolom="kolom"
               :baris="daftar.data" :nomor-awal="daftar.from ?? 1" :tautan="daftar.links" kunci-baris="kunci"
               :kosong="props.disaring ? 'Tidak ada dokumen di zona ini.' : 'Tidak ada dokumen yang lewat atau kurang dari 30 hari lagi.'">
      <template #alat>
        <p class="mn-status-sub">
          {{ props.disaring ? 'Diurut dari yang paling mendesak.' : 'Sudah lewat atau kurang dari 30 hari — 15 paling mendesak.' }}
        </p>
        <a :href="alamatEkspor" class="mn-btn mn-btn-garis">
          Unduh Excel
        </a>
      </template>
      <template #sel-jenis="{ nilai }"><span class="mn-lencana mn-l-abu">{{ JENIS[nilai] ?? nilai }}</span></template>
      <template #sel-nama="{ baris: b }">
        <SelOrang :nama="b.nama" :sub="b.noid" :href="`/miners/${b.pekerja_id}`" />
        <span v-if="!b.aktif" class="mn-lencana mn-l-abu ml-1">non-aktif</span>
      </template>
      <template #sel-berakhir="{ baris: b }"><LencanaMasaBerlaku :tanggal="b.berakhir" :sisa="b.sisa" /></template>
      <template #sel-aksi="{ baris: b }">
        <div class="mn-aksi mn-aksi-kiri">
          <TombolIkon v-if="b.tautan" ikon="mata" varian="garis" :href="b.tautan" :judul="`Buka ${JENIS[b.jenis] ?? 'dokumen'} ${b.nama}`" />
        </div>
      </template>
    </TabelData>

    <RekapPerusahaan v-for="(judul, j) in JUDUL_REKAP" :key="j" :judul="judul" :baris="rekap[j] ?? []" :pakai-aktif="j === 'mcu'" />
  </div>
</template>
