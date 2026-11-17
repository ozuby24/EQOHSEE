<script setup lang="ts">
/**
 * SIMPER — `master_simper_user` (Register, v09: "Masa Berlaku SIM Saya" +
 * "+ add") dan `master_simper_admin` (History, v05: rekap per perusahaan,
 * masa berlaku dan status per baris) Safe Track.
 *
 * Masa berlaku SIMPER = TANGGAL SIM/SIMPOL-nya; sumbernya (terbaca
 * otomatis / diisi manual / perpanjangan) tercetak di bawah lencananya.
 * Formulir tambah membaca tanggal SIM dari berkasnya (v14) sebagai
 * USULAN — tanggal yang sudah diketik tidak pernah ditimpa.
 */
import { router, useForm } from '@inertiajs/vue3';
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
import SelOrang from '../../Components/Miners/SelOrang.vue';
import Modal from '../../Components/Miners/Modal.vue';
import Medan from '../../Components/Miners/Medan.vue';
import PetunjukSim from '../../Components/Miners/PetunjukSim.vue';
import { URUTAN_ZONA, ZONA, type MedanSaring } from '../../Components/Miners/zona';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const register = computed(() => props.mode === 'register');
const alamat = computed(() => (register.value ? '/miners/simper' : '/miners/riwayat/mine-license'));
const halaman = computed<any>(() => props.baris ?? { data: [], links: [], from: 1, total: 0 });
const perusahaan = computed<any[]>(() => (props.perusahaan ?? []) as any[]);
const calon = computed<any[]>(() => (props.calon ?? []) as any[]);
const jenisSim = computed<string[]>(() => (props.jenisSim ?? []) as string[]);

/* ─────────── saringan ─────────── */

const saring = ref<Record<string, string>>({ ...(props.saring ?? {}) });

const medanSaring = computed<MedanSaring[]>(() => [
  { kunci: 'cari', label: 'Cari', placeholder: 'Nama, No ID, atau nomor dokumen', lebar: true },
  { kunci: 'perusahaan', label: 'Perusahaan', jenis: 'pilih', semua: 'Semua perusahaan', opsi: perusahaan.value, lebar: true },
  { kunci: 'zona', label: 'Zona masa berlaku', jenis: 'pilih', semua: 'Semua zona',
    opsi: URUTAN_ZONA.map((z) => ({ nilai: z, label: ZONA[z].pendek })) },
]);

function terapkan() {
  const q: Record<string, string> = {};
  for (const [k, v] of Object.entries(saring.value)) if (v) q[k] = v;
  router.get(alamat.value, q, { preserveState: true, preserveScroll: true });
}

function bersihkan() {
  saring.value = { cari: '', perusahaan: '', zona: '' };
  router.get(alamat.value, {}, { preserveScroll: true });
}

function pilihZona(z: string | null) {
  saring.value = { ...saring.value, zona: z ?? '' };
  terapkan();
}

/* Unduhan mengikuti mode dan saringan yang sedang tampil. */
const urlEkspor = computed(() => {
  const q = new URLSearchParams({ mode: String(props.mode) });
  for (const [k, v] of Object.entries(props.saring ?? {})) if (v) q.set(k, String(v));
  return `/miners/simper/ekspor?${q.toString()}`;
});

const kolom = [
  { kunci: 'nama', label: 'Nama Employee' },
  { kunci: 'noid', label: 'Noid' },
  { kunci: 'jabatan', label: 'Jabatan' },
  { kunci: 'departemen', label: 'Departemen' },
  { kunci: 'jenisSim', label: 'Jenis SIM' },
  { kunci: 'berlaku', label: 'Masa Berlaku SIM' },
  { kunci: 'status', label: 'Status' },
  { kunci: 'pembuat', label: 'Creator' },
  { kunci: 'aksi', label: '#' },
];

/* ─────────── tambah SIMPER ─────────── */

const tambahTerbuka = ref(false);
const form = useForm({
  permit_id: '' as string | number, tanggal: '', email_atasan: '', no_simpol: '', jenis_simpol: '',
  simpol_berlaku_sampai: '', simpol_sumber: 'manual',
  berkas_simpol: null as File | null, berkas_ddt: null as File | null,
  berkas_permohonan: null as File | null, berkas_pengalaman: null as File | null,
});

function bukaTambah() {
  form.reset();
  form.clearErrors();
  form.tanggal = new Date().toISOString().slice(0, 10);
  tambahTerbuka.value = true;
}

type Berkas = 'berkas_simpol' | 'berkas_ddt' | 'berkas_permohonan' | 'berkas_pengalaman';
function berkas(medan: Berkas, e: Event) {
  form[medan] = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function simpan() {
  form.post('/miners/simper', { forceFormData: true, preserveScroll: true, onSuccess: () => { tambahTerbuka.value = false; } });
}

/* ─────────── masa berlaku & status (admin) ─────────── */

const masaTerbuka = ref<any>(null);
const masaForm = useForm({ sumber: 'manual', tanggal: '' });
function bukaMasa(b: any) {
  masaTerbuka.value = b;
  masaForm.sumber = 'manual';
  masaForm.tanggal = b.berlaku ?? '';
  masaForm.clearErrors();
}
function simpanMasa() {
  masaForm.post(`/miners/simper/${masaTerbuka.value.id}/masa-berlaku`, { preserveScroll: true, onSuccess: () => { masaTerbuka.value = null; } });
}

const statusTerbuka = ref<any>(null);
const statusForm = useForm({ aktif: false as boolean, alasan: '' });
function bukaStatus(b: any) {
  statusTerbuka.value = b;
  statusForm.aktif = !b.aktif;
  statusForm.alasan = '';
  statusForm.clearErrors();
}
function simpanStatus() {
  statusForm.post(`/miners/simper/${statusTerbuka.value.id}/status`, { preserveScroll: true, onSuccess: () => { statusTerbuka.value = null; } });
}

const hapusForm = useForm({});
async function hapus(b: any) {
  if (!await tanya({ judul: 'Hapus SIMPER', pesan: `Hapus SIMPER ${b.nomor} (${b.nama})?`, labelAksi: 'Hapus', nada: 'bahaya' })) return;
  hapusForm.delete(`/miners/simper/${b.id}`, { preserveScroll: true });
}
</script>

<template>
  <div>
    <KepalaHalaman :judul="props.judul" :remah="[['Home', '/miners/dasbor'], [register ? 'Register' : 'History', null], ['SIMPER', null]]" />

    <ZonaMasaBerlaku :judul="register ? 'Masa Berlaku SIM Saya' : 'Masa Berlaku SIMPER'" :ringkas="props.ringkas ?? {}"
                     :aktif="props.saring?.zona || null" @pilih="pilihZona">
      <SaringBar v-model="saring" :medan="medanSaring" @terapkan="terapkan" @bersihkan="bersihkan" />
    </ZonaMasaBerlaku>

    <RekapPerusahaan v-if="!register" judul="SIMPER per Perusahaan" :baris="props.rekap ?? []" />

    <TabelData judul="Daftar SIMPER" :jumlah="halaman.total" :kolom="kolom" :baris="halaman.data"
               :nomor-awal="halaman.from ?? 1" :tautan="halaman.links">
      <template #alat>
        <a :href="urlEkspor" class="mn-btn mn-btn-garis">Unduh Excel</a>
        <div v-if="register">
          <button type="button" class="mn-btn mn-btn-utama" @click="bukaTambah">+ add</button>
        </div>
      </template>

      <template #sel-nama="{ baris: b }">
        <SelOrang :nama="b.nama" :sub="b.nomor" :href="`/miners/simper/${b.id}`" />
        <span v-if="!b.aktif" class="mn-lencana mn-l-abu ml-1" :title="b.alasanNonaktif || ''">non-aktif</span>
      </template>
      <template #sel-berlaku="{ baris: b }">
        <LencanaMasaBerlaku :tanggal="b.berlaku" :sisa="b.sisa" :sumber="b.sumber" />
      </template>
      <template #sel-status="{ nilai }"><LencanaStatus :status="nilai" /></template>
      <template #sel-aksi="{ baris: b }">
        <div class="mn-aksi mn-aksi-kiri">
          <template v-if="register">
            <TombolIkon v-if="b.bolehHapus" ikon="hapus" varian="bahaya" judul="Hapus SIMPER" @click="hapus(b)" />
            <TombolIkon ikon="pensil" :href="`/miners/simper/${b.id}`" judul="Buka detail SIMPER" />
          </template>
          <template v-else-if="props.kelola">
            <TombolIkon ikon="kalender" varian="garis" judul="Masa berlaku SIM" @click="bukaMasa(b)" />
            <TombolIkon ikon="orang" varian="garis" :judul="b.aktif ? 'Nonaktifkan SIMPER' : 'Aktifkan kembali'" @click="bukaStatus(b)" />
            <TombolIkon ikon="mata" varian="garis" :href="`/miners/simper/${b.id}`" judul="Buka detail" />
          </template>
          <TombolIkon v-else ikon="mata" varian="garis" :href="`/miners/simper/${b.id}`" judul="Buka detail" />
        </div>
      </template>
    </TabelData>

    <!-- ══════════ tambah SIMPER (v14) ══════════ -->
    <Modal :terbuka="tambahTerbuka" judul="Add Manpower Simper" lebar @tutup="tambahTerbuka = false">
      <form class="mn-form" @submit.prevent="simpan">
        <Medan label="Tanggal" untuk="s-tgl" wajib :galat="form.errors.tanggal">
          <input id="s-tgl" v-model="form.tanggal" type="date" class="mn-isian">
        </Medan>
        <Medan label="Employee" untuk="s-calon" wajib :galat="form.errors.permit_id"
               petunjuk="Pemegang Mine Permit yang sudah disetujui dan belum punya SIMPER.">
          <select v-if="calon.length" id="s-calon" v-model="form.permit_id" class="mn-isian" title="Employee">
            <option value="">— pilih —</option>
            <option v-for="c in calon" :key="c.id" :value="c.id">{{ c.nama }}{{ c.perusahaan ? ` (${c.perusahaan})` : '' }}</option>
          </select>
          <div v-else class="mn-alert mn-alert-peringatan mn-tanpa-jarak">
            Belum ada pemegang Mine Permit yang dapat dibuatkan SIMPER. Mine Permit-nya harus sudah disetujui; tiap karyawan hanya satu SIMPER.
          </div>
        </Medan>
        <Medan label="Email Atasan" untuk="s-email" :galat="form.errors.email_atasan">
          <input id="s-email" v-model="form.email_atasan" type="email" class="mn-isian" maxlength="150">
        </Medan>
        <Medan label="Simpol" untuk="s-simpol" wajib :galat="form.errors.no_simpol" petunjuk="Nomor SIM kepolisian (atau nomor SIO).">
          <input id="s-simpol" v-model="form.no_simpol" class="mn-isian" maxlength="40">
        </Medan>
        <Medan label="Jenis SIM" untuk="s-jenis" wajib :galat="form.errors.jenis_simpol">
          <select id="s-jenis" v-model="form.jenis_simpol" class="mn-isian" title="Jenis SIM">
            <option value="">— pilih —</option>
            <option v-for="j in jenisSim" :key="j" :value="j">{{ j }}</option>
          </select>
        </Medan>
        <Medan label="File DDT" untuk="s-ddt" wajib :galat="form.errors.berkas_ddt">
          <input id="s-ddt" type="file" class="mn-isian" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" @change="berkas('berkas_ddt', $event)">
        </Medan>
        <Medan label="Permohonan Simper" untuk="s-mohon" wajib :galat="form.errors.berkas_permohonan">
          <input id="s-mohon" type="file" class="mn-isian" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" @change="berkas('berkas_permohonan', $event)">
        </Medan>
        <PetunjukSim v-model:tanggal="form.simpol_berlaku_sampai" v-model:sumber="form.simpol_sumber" :berkas="form.berkas_simpol"
                     :galat-berkas="form.errors.berkas_simpol" :galat-tanggal="form.errors.simpol_berlaku_sampai"
                     @berkas="form.berkas_simpol = $event" />
        <Medan label="Pengalaman Kerja" untuk="s-alaman" wajib :galat="form.errors.berkas_pengalaman">
          <input id="s-alaman" type="file" class="mn-isian" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" @change="berkas('berkas_pengalaman', $event)">
        </Medan>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="tambahTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="form.processing || !calon.length" @click="simpan">Save Manpower</button>
      </template>
    </Modal>

    <!-- ══════════ masa berlaku ══════════ -->
    <Modal :terbuka="masaTerbuka !== null" judul="Masa Berlaku SIMPER" @tutup="masaTerbuka = null">
      <p class="text-[13px] mb-3"><b>{{ masaTerbuka?.nama }}</b> — {{ masaTerbuka?.nomor }}</p>
      <div class="mn-form">
        <Medan label="Sumber" untuk="m-sumber" penuh :galat="masaForm.errors.sumber">
          <select id="m-sumber" v-model="masaForm.sumber" class="mn-isian" title="Sumber masa berlaku">
            <option value="manual">Isi tanggal SIM manual</option>
            <option value="auto">Baca ulang dari berkas SIM</option>
          </select>
        </Medan>
        <Medan v-if="masaForm.sumber === 'manual'" label="Berlaku sampai" untuk="m-tgl" wajib penuh :galat="masaForm.errors.tanggal">
          <input id="m-tgl" v-model="masaForm.tanggal" type="date" class="mn-isian">
        </Medan>
      </div>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="masaTerbuka = null">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="masaForm.processing" @click="simpanMasa">Simpan</button>
      </template>
    </Modal>

    <!-- ══════════ status ══════════ -->
    <Modal :terbuka="statusTerbuka !== null" :judul="statusForm.aktif ? 'Aktifkan kembali' : 'Nonaktifkan SIMPER'" @tutup="statusTerbuka = null">
      <p class="text-[13px] mb-3"><b>{{ statusTerbuka?.nama }}</b> —
        <template v-if="statusForm.aktif">SIMPER kembali dihitung sebagai dokumen aktif.</template>
        <template v-else>SIMPER ditutup. Datanya tidak dihapus, tetapi tidak lagi dihitung pada zona masa berlaku.</template>
      </p>
      <Medan v-if="!statusForm.aktif" label="Alasan" untuk="st-alasan" wajib :galat="statusForm.errors.alasan">
        <input id="st-alasan" v-model="statusForm.alasan" class="mn-isian" maxlength="200" placeholder="mis. mutasi, SIM dicabut">
      </Medan>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="statusTerbuka = null">Close</button>
        <button type="button" class="mn-btn" :class="statusForm.aktif ? 'mn-btn-sukses' : 'mn-btn-bahaya'" :disabled="statusForm.processing" @click="simpanStatus">
          {{ statusForm.aktif ? 'Aktifkan' : 'Nonaktifkan' }}
        </button>
      </template>
    </Modal>
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>
