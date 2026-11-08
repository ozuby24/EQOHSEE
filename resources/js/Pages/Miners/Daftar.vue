<script setup lang="ts">
/**
 * Manpower MCU — layar `manpower_mcu_admin` Safe Track.
 *
 * Empat bagian, sama urutannya dengan Safe Track:
 *
 *   1. Masa Berlaku MCU — kartu zona yang sekaligus saringan, ringkasan
 *      manpower aktif/tidak aktif, dan baris saring.
 *   2. Manpower per Perusahaan — rekap zona dengan bilah sebaran.
 *   3. Daftar Manpower — berhalaman, dengan lencana masa berlaku, status
 *      karyawan, dan status pengajuan MCU terakhirnya.
 *   4. Formulir tambah/ubah manpower dan saklar aktif/tidak aktif.
 *
 * Penyaringan dikerjakan server; layar hanya mengirim saringannya.
 */
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import ZonaMasaBerlaku from '../../Components/Miners/ZonaMasaBerlaku.vue';
import SaringBar from '../../Components/Miners/SaringBar.vue';
import RekapPerusahaan from '../../Components/Miners/RekapPerusahaan.vue';
import TabelData from '../../Components/Miners/TabelData.vue';
import LencanaMasaBerlaku from '../../Components/Miners/LencanaMasaBerlaku.vue';
import LencanaStatus from '../../Components/Miners/LencanaStatus.vue';
import TombolIkon from '../../Components/Miners/TombolIkon.vue';
import Modal from '../../Components/Miners/Modal.vue';
import Medan from '../../Components/Miners/Medan.vue';
import { tglId, URUTAN_ZONA, ZONA, type MedanSaring } from '../../Components/Miners/zona';

const props = propHalaman();

const halaman = computed<any>(() => props.baris ?? { data: [], links: [], from: 1, total: 0 });
const pilihan = computed<any>(() => props.pilihan ?? {});
const perusahaan = computed<any[]>(() => (props.perusahaan ?? []) as any[]);

/* ─────────── saringan ─────────── */

const saring = ref<Record<string, string>>({ ...(props.saring ?? {}) });

const medanSaring = computed<MedanSaring[]>(() => [
  { kunci: 'cari', label: 'Cari', placeholder: 'Nama, No ID, atau nomor dokumen', lebar: true },
  { kunci: 'perusahaan', label: 'Perusahaan', jenis: 'pilih', semua: 'Semua perusahaan', opsi: perusahaan.value, lebar: true },
  { kunci: 'aktif', label: 'Status karyawan', jenis: 'pilih', opsi: [{ nilai: '1', label: 'Aktif' }, { nilai: '0', label: 'Tidak aktif' }] },
  { kunci: 'zona', label: 'Zona masa berlaku', jenis: 'pilih', semua: 'Semua zona',
    opsi: URUTAN_ZONA.map((z) => ({ nilai: z, label: ZONA[z].pendek })) },
]);

function terapkan() {
  const q: Record<string, string> = {};
  for (const [k, v] of Object.entries(saring.value)) if (v) q[k] = v;
  router.get('/miners', q, { preserveState: true, preserveScroll: true });
}

function bersihkan() {
  saring.value = { cari: '', perusahaan: '', aktif: '', zona: '' };
  router.get('/miners', {}, { preserveScroll: true });
}

function pilihZona(z: string | null) {
  saring.value = { ...saring.value, zona: z ?? '' };
  terapkan();
}

/* ─────────── tabel ─────────── */

const kolom = [
  { kunci: 'noMcu', label: 'No Registrasi' },
  { kunci: 'nama', label: 'Nama Employee' },
  { kunci: 'noid', label: 'Noid' },
  { kunci: 'perusahaan', label: 'Perusahaan' },
  { kunci: 'departemen', label: 'Departemen' },
  { kunci: 'hasil', label: 'Hasil MCU' },
  { kunci: 'berlaku', label: 'Masa Berlaku MCU' },
  { kunci: 'karyawan', label: 'Karyawan' },
  { kunci: 'pengajuan', label: 'Status Pengajuan' },
  { kunci: 'aksi', label: '#' },
];

/* ─────────── formulir manpower ─────────── */

const formTerbuka = ref(false);
const diubah = ref<any>(null);

const kosong = {
  company_id: '' as string | number, nama: '', nik: '', no_induk: '', tanggal_lahir: '', gol_darah: '',
  telepon: '', telepon_darurat: '', departemen_id: '' as string | number, jabatan_id: '' as string | number,
  subkontraktor_id: '' as string | number, status_kerja: '', catatan: '',
};

const form = useForm({ ...kosong });

function tambah() {
  diubah.value = null;
  form.defaults({ ...kosong, company_id: perusahaan.value.length === 1 ? perusahaan.value[0].nilai : '' });
  form.reset();
  form.clearErrors();
  formTerbuka.value = true;
}

function ubah(b: any) {
  diubah.value = b;
  form.clearErrors();
  form.defaults({ ...kosong, ...b.isian });
  form.reset();
  formTerbuka.value = true;
}

function simpan() {
  const opsi = { preserveScroll: true, onSuccess: () => { formTerbuka.value = false; } };
  if (diubah.value) form.put(`/miners/${diubah.value.id}`, opsi);
  else form.post('/miners', opsi);
}

/* ─────────── status karyawan ─────────── */

const statusTerbuka = ref<any>(null);
const statusForm = useForm({ aktif: false as boolean, tanggal: '', alasan: '' });

function bukaStatus(b: any) {
  statusTerbuka.value = b;
  statusForm.aktif = !b.aktif;
  statusForm.tanggal = new Date().toISOString().slice(0, 10);
  statusForm.alasan = '';
}

function simpanStatus() {
  statusForm.post(`/miners/${statusTerbuka.value.id}/status`, {
    preserveScroll: true,
    onSuccess: () => { statusTerbuka.value = null; },
  });
}
</script>

<template>
  <div>
    <KepalaHalaman judul="Manpower MCU" :remah="[['Home', '/miners/dasbor'], ['Data MCU', null], ['Manpower', null]]" />

    <ZonaMasaBerlaku judul="Masa Berlaku MCU" :ringkas="props.ringkas ?? {}" :aktif="props.saring?.zona || null"
                     :manpower="props.manpower" @pilih="pilihZona">
      <SaringBar v-model="saring" :medan="medanSaring" @terapkan="terapkan" @bersihkan="bersihkan" />
    </ZonaMasaBerlaku>

    <RekapPerusahaan judul="Manpower per Perusahaan" :baris="props.rekap ?? []" pakai-aktif />

    <TabelData judul="Daftar Manpower" :jumlah="halaman.total" :kolom="kolom" :baris="halaman.data"
               :nomor-awal="halaman.from ?? 1" :tautan="halaman.links">
      <template #kanan>
        <button type="button" class="mn-btn mn-btn-gelap" @click="tambah">+ Tambah Manpower</button>
      </template>

      <template #sel-noMcu="{ baris: b }">
        <Link v-if="b.mcu_id" :href="`/miners/mcu/${b.mcu_id}`" class="mn-tautan">{{ b.noMcu || 'tanpa nomor' }}</Link>
        <span v-else class="mn-redup">—</span>
      </template>
      <template #sel-nama="{ baris: b }">
        <Link :href="`/miners/${b.id}`" class="mn-tautan" style="color:inherit">{{ b.nama }}</Link>
      </template>
      <template #sel-hasil="{ nilai }"><span class="mn-redup">{{ nilai || 'Belum Dinilai' }}</span></template>
      <template #sel-berlaku="{ baris: b }"><LencanaMasaBerlaku :tanggal="b.berlaku" :sisa="b.sisa" /></template>
      <template #sel-karyawan="{ baris: b }">
        <LencanaStatus :status="b.aktif ? 'aktif' : 'nonaktif'" />
        <span v-if="!b.aktif && b.nonaktifSejak" class="mn-sisa">sejak {{ tglId(b.nonaktifSejak) }}</span>
      </template>
      <template #sel-pengajuan="{ nilai }">
        <LencanaStatus v-if="nilai" :status="nilai" :label="props.MCU_STATUS?.[nilai]" />
        <span v-else class="mn-redup">—</span>
      </template>
      <template #sel-aksi="{ baris: b }">
        <div class="mn-aksi">
          <TombolIkon ikon="pensil" judul="Ubah data manpower" @click="ubah(b)" />
          <TombolIkon v-if="props.kelola" ikon="orang" varian="garis"
                      :judul="b.aktif ? 'Nonaktifkan karyawan' : 'Aktifkan kembali'" @click="bukaStatus(b)" />
        </div>
      </template>
    </TabelData>

    <!-- ══════════ formulir manpower ══════════ -->
    <Modal :terbuka="formTerbuka" :judul="diubah ? 'Ubah Manpower' : 'Tambah Manpower'" lebar @tutup="formTerbuka = false">
      <form class="mn-form" @submit.prevent="simpan">
        <Medan label="Nama Employee" untuk="mp-nama" wajib :galat="form.errors.nama" penuh>
          <input id="mp-nama" v-model="form.nama" class="mn-isian" maxlength="150" required>
        </Medan>
        <Medan label="Perusahaan" untuk="mp-perusahaan" :galat="form.errors.company_id">
          <select id="mp-perusahaan" title="Perusahaan" v-model="form.company_id" class="mn-isian">
            <option value="">— perusahaan saya —</option>
            <option v-for="p in perusahaan" :key="p.nilai" :value="p.nilai">{{ p.label }}</option>
          </select>
        </Medan>
        <Medan label="NIK" untuk="mp-nik" :galat="form.errors.nik">
          <input id="mp-nik" v-model="form.nik" class="mn-isian" maxlength="40" inputmode="numeric">
        </Medan>
        <Medan label="No ID (Noid)" untuk="mp-noid" :galat="form.errors.no_induk" petunjuk="Diganti nomor Mine Permit begitu kartunya terbit.">
          <input id="mp-noid" v-model="form.no_induk" class="mn-isian" maxlength="40">
        </Medan>
        <Medan label="Tanggal lahir" untuk="mp-lahir" :galat="form.errors.tanggal_lahir">
          <input id="mp-lahir" title="Tanggal lahir" v-model="form.tanggal_lahir" type="date" class="mn-isian">
        </Medan>
        <Medan label="Departemen" untuk="mp-dept" :galat="form.errors.departemen_id">
          <select id="mp-dept" title="Departemen" v-model="form.departemen_id" class="mn-isian">
            <option value="">—</option>
            <option v-for="(nama, id) in pilihan.departemen" :key="id" :value="Number(id)">{{ nama }}</option>
          </select>
        </Medan>
        <Medan label="Jabatan" untuk="mp-jabatan" :galat="form.errors.jabatan_id">
          <select id="mp-jabatan" title="Jabatan" v-model="form.jabatan_id" class="mn-isian">
            <option value="">—</option>
            <option v-for="(nama, id) in pilihan.jabatan" :key="id" :value="Number(id)">{{ nama }}</option>
          </select>
        </Medan>
        <Medan label="Golongan darah" untuk="mp-darah" :galat="form.errors.gol_darah">
          <select id="mp-darah" title="Golongan darah" v-model="form.gol_darah" class="mn-isian">
            <option value="">—</option>
            <option v-for="g in pilihan.golDarah" :key="g" :value="g">{{ g }}</option>
          </select>
        </Medan>
        <Medan label="Status kerja" untuk="mp-statkerja" :galat="form.errors.status_kerja">
          <select id="mp-statkerja" title="Status kerja" v-model="form.status_kerja" class="mn-isian">
            <option value="">—</option>
            <option v-for="(label, k) in pilihan.statusKerja" :key="k" :value="k">{{ label }}</option>
          </select>
        </Medan>
        <Medan label="No telepon" untuk="mp-telp" :galat="form.errors.telepon">
          <input id="mp-telp" v-model="form.telepon" class="mn-isian" maxlength="30" inputmode="tel">
        </Medan>
        <Medan label="No telepon darurat" untuk="mp-telp2" :galat="form.errors.telepon_darurat">
          <input id="mp-telp2" v-model="form.telepon_darurat" class="mn-isian" maxlength="30" inputmode="tel">
        </Medan>
        <Medan label="Subkontraktor" untuk="mp-subkon" :galat="form.errors.subkontraktor_id">
          <select id="mp-subkon" title="Subkontraktor" v-model="form.subkontraktor_id" class="mn-isian">
            <option value="">—</option>
            <option v-for="(nama, id) in pilihan.subkontraktor" :key="id" :value="Number(id)">{{ nama }}</option>
          </select>
        </Medan>
        <Medan label="Catatan" untuk="mp-catatan" :galat="form.errors.catatan" penuh>
          <textarea id="mp-catatan" v-model="form.catatan" class="mn-isian" rows="2" />
        </Medan>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="formTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="form.processing" @click="simpan">Save</button>
      </template>
    </Modal>

    <!-- ══════════ status karyawan ══════════ -->
    <Modal :terbuka="statusTerbuka !== null" :judul="statusForm.aktif ? 'Aktifkan kembali' : 'Nonaktifkan karyawan'"
           @tutup="statusTerbuka = null">
      <p class="text-[13px] mb-3">
        <b>{{ statusTerbuka?.nama }}</b> —
        <template v-if="statusForm.aktif">kembali dihitung pada seluruh layar masa berlaku.</template>
        <template v-else>tidak lagi dihitung sebagai manpower aktif. Riwayat MCU, permit, SIMPER, dan HR-nya tetap tersimpan.</template>
      </p>
      <div v-if="!statusForm.aktif" class="mn-form">
        <Medan label="Tanggal non-aktif" untuk="st-tgl" :galat="statusForm.errors.tanggal">
          <input id="st-tgl" title="Tanggal non-aktif" v-model="statusForm.tanggal" type="date" class="mn-isian">
        </Medan>
        <Medan label="Alasan" untuk="st-alasan" :galat="statusForm.errors.alasan">
          <input id="st-alasan" v-model="statusForm.alasan" class="mn-isian" maxlength="200" placeholder="mis. resign, mutasi">
        </Medan>
      </div>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="statusTerbuka = null">Close</button>
        <button type="button" class="mn-btn" :class="statusForm.aktif ? 'mn-btn-sukses' : 'mn-btn-bahaya'"
                :disabled="statusForm.processing" @click="simpanStatus">
          {{ statusForm.aktif ? 'Aktifkan' : 'Nonaktifkan' }}
        </button>
      </template>
    </Modal>
  </div>
</template>
