<script setup lang="ts">
/**
 * Perpanjangan Mine Permit — `masterdata-users` / `masterdata-admin` dan
 * `forminput` Safe Track.
 *
 * Daftar pengajuan dengan saklar "Perlu Approval Saya / Semua", dan
 * formulir pengajuan: kartu yang diperpanjang (terbit atau expired,
 * aktif, tanpa perpanjangan yang berjalan), tanggal periksa MCU baru,
 * masa berlaku baru, form pengajuan, dan sertifikat MCU. Keadaan kartu
 * saat ini ditampilkan begitu dipilih — pengaju tinggal memperbarui yang
 * memang berubah.
 */
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import TabelData from '../../Components/Miners/TabelData.vue';
import LencanaStatus from '../../Components/Miners/LencanaStatus.vue';
import PilihSayaSemua from '../../Components/Miners/PilihSayaSemua.vue';
import Modal from '../../Components/Miners/Modal.vue';
import Medan from '../../Components/Miners/Medan.vue';
import { tglId } from '../../Components/Miners/zona';

const props = propHalaman();

const baris = computed<any[]>(() => (props.baris ?? []) as any[]);
const calon = computed<any[]>(() => (props.calon ?? []) as any[]);
const pilih = computed<any>(() => props.pilih ?? null);

const kolom = [
  { kunci: 'tanggal', label: 'Tanggal Pengajuan' },
  { kunci: 'nomor', label: 'Detail' },
  { kunci: 'nama', label: 'Nama Employee' },
  { kunci: 'noid', label: 'Noid' },
  { kunci: 'perusahaan', label: 'Perusahaan' },
  { kunci: 'berlakuBaru', label: 'Masa Berlaku Baru' },
  { kunci: 'status', label: 'Status' },
  { kunci: 'posisi', label: 'Menunggu' },
];

const terbuka = ref(false);
const form = useForm({
  permit_id: '' as string | number, tanggal: '', berlaku_baru: '', tanggal_mcu: '', catatan: '',
  berkas_form: null as File | null, berkas_mcu: null as File | null,
});

const kartu = computed(() => calon.value.find((c) => c.id === Number(form.permit_id)) ?? null);

function buka() {
  form.reset();
  form.clearErrors();
  form.tanggal = String(props.hariIni ?? '');
  terbuka.value = true;
}

function simpan() {
  form.post('/miners/perpanjangan-permit', { forceFormData: true, preserveScroll: true, onSuccess: () => { terbuka.value = false; } });
}
</script>

<template>
  <div>
    <KepalaHalaman judul="Perpanjangan Mine Permit" :remah="[['Home', '/miners/dasbor'], ['SIMPER Lanjutan', null], ['Perpanjangan Permit', null]]" />

    <TabelData judul="Data Perpanjangan" :jumlah="baris.length" :kolom="kolom" :baris="baris"
               :kosong="pilih?.aktif === 'saya' ? 'Tidak ada perpanjangan yang menunggu persetujuan Anda.' : 'Belum ada pengajuan perpanjangan.'">
      <template #kanan>
        <PilihSayaSemua v-if="pilih" :saya="pilih.saya" :semua="pilih.semua" :aktif="pilih.aktif"
                        href-saya="/miners/perpanjangan-permit" href-semua="/miners/perpanjangan-permit?semua=1" />
      </template>
      <template #alat>
        <button type="button" class="mn-btn mn-btn-gelap" @click="buka">+ Form Perpanjangan</button>
      </template>

      <template #sel-tanggal="{ nilai }">{{ tglId(nilai) }}</template>
      <template #sel-nomor="{ baris: b }"><Link :href="`/miners/perpanjangan-permit/${b.id}`" class="mn-tautan">{{ b.nomor || 'detail' }}</Link></template>
      <template #sel-berlakuBaru="{ nilai }">{{ tglId(nilai) }}</template>
      <template #sel-status="{ nilai }"><LencanaStatus :status="nilai" /></template>
      <template #sel-posisi="{ nilai }"><span class="mn-redup text-[12px]">{{ nilai || '—' }}</span></template>
    </TabelData>

    <Modal :terbuka="terbuka" judul="Form Perpanjangan" lebar @tutup="terbuka = false">
      <form class="mn-form" @submit.prevent="simpan">
        <Medan label="Card (Mine Permit)" untuk="pp-permit" wajib :galat="form.errors.permit_id" penuh>
          <select v-if="calon.length" id="pp-permit" v-model="form.permit_id" class="mn-isian" title="Mine Permit yang diperpanjang">
            <option value="">— pilih —</option>
            <option v-for="c in calon" :key="c.id" :value="c.id">{{ c.label }}</option>
          </select>
          <div v-else class="mn-alert mn-alert-peringatan" style="margin:0">
            Tidak ada Mine Permit yang dapat diperpanjang: kartunya harus terbit atau expired, aktif, dan tidak sedang punya perpanjangan berjalan.
          </div>
        </Medan>
        <div v-if="kartu" class="mn-penuh mn-alert mn-alert-info" style="margin:0">
          <div>
            <div>Masa berlaku: <b>{{ tglId(kartu.berlaku) }}</b></div>
            <div>Masa berlaku MCU: <b>{{ tglId(kartu.mcu) }}</b> (periksa {{ tglId(kartu.tglMcu) }})</div>
            <div v-if="kartu.nik">Nomor KTP: <b>{{ kartu.nik }}</b></div>
            <div v-if="kartu.cakupan">Cakupan area: <b>{{ kartu.cakupan }}</b></div>
            <div>Sudah diperpanjang: <b>{{ kartu.jumlah }} kali</b></div>
          </div>
        </div>
        <Medan label="Tanggal Pengajuan" untuk="pp-tgl" wajib :galat="form.errors.tanggal">
          <input id="pp-tgl" v-model="form.tanggal" type="date" class="mn-isian">
        </Medan>
        <Medan label="Masa Berlaku Baru" untuk="pp-baru" wajib :galat="form.errors.berlaku_baru">
          <input id="pp-baru" v-model="form.berlaku_baru" type="date" class="mn-isian">
        </Medan>
        <Medan label="Tanggal Periksa MCU" untuk="pp-mcu" wajib :galat="form.errors.tanggal_mcu"
               petunjuk="Ditulis balik ke register MCU begitu dokter menyetujui.">
          <input id="pp-mcu" v-model="form.tanggal_mcu" type="date" class="mn-isian">
        </Medan>
        <Medan label="Form Pengajuan" untuk="pp-form" wajib :galat="form.errors.berkas_form">
          <input id="pp-form" type="file" class="mn-isian" @change="form.berkas_form = ($event.target as HTMLInputElement).files?.[0] ?? null">
        </Medan>
        <Medan label="Sertifikat MCU" untuk="pp-sert" wajib :galat="form.errors.berkas_mcu">
          <input id="pp-sert" type="file" class="mn-isian" @change="form.berkas_mcu = ($event.target as HTMLInputElement).files?.[0] ?? null">
        </Medan>
        <Medan label="Catatan" untuk="pp-catatan" penuh>
          <textarea id="pp-catatan" v-model="form.catatan" class="mn-isian" rows="2" />
        </Medan>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="terbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="form.processing || !calon.length" @click="simpan">Submit</button>
      </template>
    </Modal>
  </div>
</template>
