<script setup lang="ts">
/**
 * SIMPER Lanjutan — "Simper Penambahan Unit Table" (37), "Upgrade
 * Authority Table" (38), dan Perpanjangan SIMPER Safe Track: "+ add",
 * pencarian, lalu tabel No/Tanggal/Nama Employee/Noid/Jabatan/
 * Departemen/Jenis SIM/Status/Creator/#.
 */
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import TabelData from '../../Components/Miners/TabelData.vue';
import LencanaStatus from '../../Components/Miners/LencanaStatus.vue';
import TombolIkon from '../../Components/Miners/TombolIkon.vue';
import SelOrang from '../../Components/Miners/SelOrang.vue';
import Modal from '../../Components/Miners/Modal.vue';
import Medan from '../../Components/Miners/Medan.vue';
import PetunjukSim from '../../Components/Miners/PetunjukSim.vue';
import { tglId } from '../../Components/Miners/zona';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const jenis = computed(() => String(props.jenis));
const baris = computed<any[]>(() => (props.baris ?? []) as any[]);
const calon = computed<any[]>(() => (props.calon ?? []) as any[]);
const cari = ref(String(props.cari ?? ''));

function terapkan() {
  router.get(window.location.pathname, cari.value ? { cari: cari.value } : {}, { preserveState: true, preserveScroll: true });
}

const kolom = [
  { kunci: 'tanggal', label: 'Tanggal' },
  { kunci: 'nama', label: 'Nama Employee' },
  { kunci: 'noid', label: 'Noid' },
  { kunci: 'jabatan', label: 'Jabatan' },
  { kunci: 'departemen', label: 'Departemen' },
  { kunci: 'jenisSim', label: 'Jenis SIM' },
  { kunci: 'status', label: 'Status' },
  { kunci: 'pembuat', label: 'Creator' },
  { kunci: 'aksi', label: '#' },
];

const tambahTerbuka = ref(false);
const form = useForm({
  jenis: '', simper_id: '' as string | number, tanggal: '',
  berkas_evaluasi: null as File | null, berkas_pengalaman: null as File | null,
  no_simpol_baru: '', simpol_berlaku_sampai: '', simpol_sumber: 'manual', tanggal_mcu: '',
  berkas_simpol: null as File | null, berkas_mcu: null as File | null, berkas_form: null as File | null,
});

const terpilih = computed(() => calon.value.find((c) => c.id === Number(form.simper_id)) ?? null);

function bukaTambah() {
  form.reset();
  form.clearErrors();
  form.jenis = jenis.value;
  form.tanggal = new Date().toISOString().slice(0, 10);
  tambahTerbuka.value = true;
}

function pilihSimper() {
  /* Perpanjangan diisi awal dari SIMPER-nya (Riwayat::awalSimper). */
  if (jenis.value === 'perpanjangan' && terpilih.value && !form.no_simpol_baru) form.no_simpol_baru = terpilih.value.simpol ?? '';
}

type Berkas = 'berkas_evaluasi' | 'berkas_pengalaman' | 'berkas_mcu' | 'berkas_form';
function berkas(m: Berkas, e: Event) {
  form[m] = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function simpan() {
  form.post('/miners/simper-lanjutan', { forceFormData: true, preserveScroll: true, onSuccess: () => { tambahTerbuka.value = false; } });
}

const hapusForm = useForm({});
async function hapus(b: any) {
  if (!await tanya({ judul: 'Hapus pengajuan', pesan: `Hapus ${props.judul} ${b.nomor} (${b.nama})?`, labelAksi: 'Hapus', nada: 'bahaya' })) return;
  hapusForm.delete(`/miners/simper-lanjutan/${b.id}`, { preserveScroll: true });
}
</script>

<template>
  <div>
    <KepalaHalaman :judul="props.judul" :remah="[['Home', '/miners/dasbor'], ['SIMPER Lanjutan', null], [props.judul, null]]" />

    <TabelData :judul="props.tabel" :jumlah="baris.length" :kolom="kolom" :baris="baris">
      <template #alat>
        <button type="button" class="mn-btn mn-btn-utama" @click="bukaTambah">+ add</button>
        <form class="mn-alat" role="search" @submit.prevent="terapkan">
          <input v-model="cari" class="mn-isian" placeholder="search.." aria-label="Cari">
          <button type="submit" class="mn-btn mn-btn-garis">search</button>
        </form>
      </template>
      <template #sel-tanggal="{ nilai }">{{ tglId(nilai) }}</template>
      <template #sel-nama="{ baris: b }"><SelOrang :nama="b.nama" :sub="b.nomor" :href="`/miners/simper-lanjutan/${b.id}`" /></template>
      <template #sel-status="{ nilai }"><LencanaStatus :status="nilai" /></template>
      <template #sel-aksi="{ baris: b }">
        <div class="mn-aksi mn-aksi-kiri">
          <TombolIkon v-if="b.bolehHapus" ikon="hapus" varian="bahaya" judul="Hapus pengajuan" @click="hapus(b)" />
          <TombolIkon ikon="pensil" :href="`/miners/simper-lanjutan/${b.id}`" judul="Buka detail" />
        </div>
      </template>
    </TabelData>

    <Modal :terbuka="tambahTerbuka" :judul="`Add ${props.judul}`" lebar @tutup="tambahTerbuka = false">
      <form class="mn-form" @submit.prevent="simpan">
        <Medan label="Tanggal" untuk="l-tgl"><input id="l-tgl" v-model="form.tanggal" type="date" class="mn-isian"></Medan>
        <Medan label="SIMPER" untuk="l-simper" wajib :galat="form.errors.simper_id"
               :petunjuk="jenis === 'perpanjangan' ? 'SIMPER disetujui dan aktif, tanpa perpanjangan yang masih berjalan.' : 'SIMPER yang sudah disetujui dan aktif.'">
          <select v-if="calon.length" id="l-simper" v-model="form.simper_id" class="mn-isian" title="SIMPER" @change="pilihSimper">
            <option value="">— pilih —</option>
            <option v-for="c in calon" :key="c.id" :value="c.id">{{ c.nama }} — {{ c.nomor }}</option>
          </select>
          <div v-else class="mn-alert mn-alert-peringatan mn-tanpa-jarak">Belum ada SIMPER yang disetujui dan aktif untuk diajukan.</div>
        </Medan>

        <template v-if="jenis === 'penambahan'">
          <Medan label="Evaluasi" untuk="l-eval" wajib :galat="form.errors.berkas_evaluasi">
            <input id="l-eval" type="file" class="mn-isian" @change="berkas('berkas_evaluasi', $event)">
          </Medan>
          <Medan label="Pengalaman" untuk="l-alaman" wajib :galat="form.errors.berkas_pengalaman">
            <input id="l-alaman" type="file" class="mn-isian" @change="berkas('berkas_pengalaman', $event)">
          </Medan>
          <p class="mn-petunjuk mn-form-penuh">Unit yang sudah ada ikut tersalin; unit baru ditambahkan di halaman detail sesudah disimpan.</p>
        </template>

        <template v-else-if="jenis === 'upgrade'">
          <p class="mn-petunjuk mn-form-penuh">Sesudah disimpan, pilih unit SIMPER yang di-upgrade beserta authority baru dan tiga berkasnya di halaman detail.</p>
        </template>

        <template v-else>
          <Medan label="No. SIM Pol baru" untuk="l-sim" wajib :galat="form.errors.no_simpol_baru">
            <input id="l-sim" v-model="form.no_simpol_baru" class="mn-isian" maxlength="40">
          </Medan>
          <Medan label="Tanggal MCU" untuk="l-mcu" wajib :galat="form.errors.tanggal_mcu">
            <input id="l-mcu" v-model="form.tanggal_mcu" type="date" class="mn-isian">
          </Medan>
          <PetunjukSim v-model:tanggal="form.simpol_berlaku_sampai" v-model:sumber="form.simpol_sumber" label="Berkas SIM Pol baru"
                       :galat-berkas="form.errors.berkas_simpol" :galat-tanggal="form.errors.simpol_berlaku_sampai" @berkas="form.berkas_simpol = $event" />
          <Medan label="Sertifikat MCU" untuk="l-smcu" wajib :galat="form.errors.berkas_mcu">
            <input id="l-smcu" type="file" class="mn-isian" @change="berkas('berkas_mcu', $event)">
          </Medan>
          <Medan label="Form Perpanjangan SIMPER" untuk="l-form" wajib :galat="form.errors.berkas_form">
            <input id="l-form" type="file" class="mn-isian" @change="berkas('berkas_form', $event)">
          </Medan>
        </template>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="tambahTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="form.processing || !calon.length" @click="simpan">Save Data</button>
      </template>
    </Modal>
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>
