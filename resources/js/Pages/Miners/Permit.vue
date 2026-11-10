<script setup lang="ts">
/**
 * Mine Permit — `master_permit_users` (Register, v11: "Masa Berlaku Mine
 * Permit Saya" + "+ add Permit") dan `master_permit_admin` (History, v04:
 * rekap per perusahaan, Export, "Sinkronkan dari MCU", masa berlaku dan
 * status per baris) Safe Track.
 *
 * Masa berlaku tiap kartu MENGIKUTI MCU-nya (atau dikunci admin, atau
 * hasil perpanjangan) — sumbernya tercetak di bawah lencananya.
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
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

const props = propHalaman();

/* Penegasan memakai dialog aplikasi, bukan confirm() peramban — di
   webview ponsel dialog bawaan dibungkam dan tindakannya diam-diam batal. */
const { dialog, tanya, batal, lanjut } = useDialog();

const register = computed(() => props.mode === 'register');
const alamat = computed(() => (register.value ? '/miners/permit' : '/miners/riwayat/mine-permit'));
const halaman = computed<any>(() => props.baris ?? { data: [], links: [], from: 1, total: 0 });
const perusahaan = computed<any[]>(() => (props.perusahaan ?? []) as any[]);
const pilihan = computed<any>(() => props.pilihan ?? {});
const calon = computed<any[]>(() => (props.calon ?? []) as any[]);

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
  saring.value = { cari: '', perusahaan: '', zona: '', aktif: '' };
  router.get(alamat.value, {}, { preserveScroll: true });
}

function pilihZona(z: string | null) {
  saring.value = { ...saring.value, zona: z ?? '' };
  terapkan();
}

const kolom = computed(() => [
  { kunci: 'nomor', label: 'No Registrasi' },
  { kunci: 'tanggal', label: 'Tanggal' },
  { kunci: 'noid', label: 'Noid' },
  { kunci: 'nama', label: 'Nama Employee' },
  { kunci: 'jabatan', label: 'Jabatan' },
  { kunci: 'perusahaan', label: 'Perusahaan' },
  { kunci: 'berlaku', label: 'Masa Berlaku' },
  { kunci: 'status', label: 'Status' },
  { kunci: 'aksi', label: '#' },
]);

/* ─────────── ekspor & sinkron ─────────── */

const dari = ref('');
const sampai = ref('');
const urlEkspor = computed(() => {
  const q = new URLSearchParams({ mode: String(props.mode) });
  if (dari.value) q.set('dari', dari.value);
  if (sampai.value) q.set('sampai', sampai.value);
  return `/miners/permit/ekspor?${q.toString()}`;
});

const sinkronForm = useForm({});
function sinkron() {
  sinkronForm.post('/miners/permit/sinkron', { preserveScroll: true });
}

/* ─────────── tambah permit ─────────── */

const tambahTerbuka = ref(false);
const form = useForm({
  induksi_orang_id: '' as string | number, iup_id: '' as string | number, kontraktor_id: '' as string | number,
  jenis_kartu: 'Mine Permit', cakupan_area: 'FULL', kode_warna: '', departemen_id: '' as string | number,
  tanggal_lahir: '', gol_darah: '', telepon: '', telepon_darurat: '',
  foto: null as File | null, ktp: null as File | null, permohonan: null as File | null,
});

const terpilih = computed(() => calon.value.find((c) => c.id === Number(form.induksi_orang_id)) ?? null);

function pilihCalon() {
  const c = terpilih.value;
  if (!c) return;
  form.tanggal_lahir = c.lahir ?? '';
  form.gol_darah = c.darah ?? '';
  form.telepon = c.telepon ?? '';
  form.telepon_darurat = c.darurat ?? '';
  form.departemen_id = c.departemen_id ?? '';
  /* Contractor ditentukan perusahaan karyawannya — diisi otomatis bila
     ia mitra (IUJP), dikosongkan bila ia karyawan IUP sendiri. */
  form.kontraktor_id = c.mitra ? c.company_id : '';
  const k = (pilihan.value.kontraktor ?? []).find((x: any) => x.nilai === c.company_id);
  if (k?.induk) form.iup_id = k.induk;
  else if (!c.mitra && c.company_id) form.iup_id = c.company_id;
}

function bukaTambah() {
  form.reset();
  form.clearErrors();
  if ((pilihan.value.iup ?? []).length === 1) form.iup_id = pilihan.value.iup[0].nilai;
  tambahTerbuka.value = true;
}

function berkas(medan: 'foto' | 'ktp' | 'permohonan', e: Event) {
  form[medan] = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function simpan() {
  form.post('/miners/permit', { forceFormData: true, preserveScroll: true, onSuccess: () => { tambahTerbuka.value = false; } });
}

/* ─────────── masa berlaku & status (admin) ─────────── */

const masaTerbuka = ref<any>(null);
const masaForm = useForm({ sumber: 'mcu', tanggal: '' });
function bukaMasa(b: any) {
  masaTerbuka.value = b;
  masaForm.sumber = b.sumberKode === 'manual' ? 'manual' : 'mcu';
  masaForm.tanggal = b.berlaku ?? '';
  masaForm.clearErrors();
}
function simpanMasa() {
  masaForm.post(`/miners/permit/${masaTerbuka.value.id}/masa-berlaku`, { preserveScroll: true, onSuccess: () => { masaTerbuka.value = null; } });
}

const statusTerbuka = ref<any>(null);
const statusForm = useForm({ aktif: false as boolean, alasan: '' });
function bukaStatus(b: any) {
  statusTerbuka.value = b;
  statusForm.aktif = !b.aktif;
  statusForm.alasan = '';
}
function simpanStatus() {
  statusForm.post(`/miners/permit/${statusTerbuka.value.id}/status`, { preserveScroll: true, onSuccess: () => { statusTerbuka.value = null; } });
}

const hapusForm = useForm({});
async function hapus(b: any) {
  if (!await tanya({ judul: 'Hapus Mine Permit', pesan: `Hapus Mine Permit ${b.nomor} (${b.nama})?`, labelAksi: 'Hapus', nada: 'bahaya' })) return;
  hapusForm.delete(`/miners/permit/${b.id}`, { preserveScroll: true });
}
</script>

<template>
  <div>
    <KepalaHalaman :judul="props.judul" :remah="[['Home', '/miners/dasbor'], [register ? 'Register' : 'History', null], ['Mine Permit', null]]" />

    <ZonaMasaBerlaku :judul="register ? 'Masa Berlaku Mine Permit Saya' : 'Masa Berlaku Mine Permit'" :ringkas="props.ringkas ?? {}"
                     :aktif="props.saring?.zona || null" @pilih="pilihZona">
      <SaringBar v-model="saring" :medan="medanSaring" @terapkan="terapkan" @bersihkan="bersihkan" />
    </ZonaMasaBerlaku>

    <RekapPerusahaan v-if="!register" judul="Mine Permit per Perusahaan" :baris="props.rekap ?? []" />

    <TabelData :judul="register ? 'Daftar Permit' : 'Daftar Mine Permit'" :jumlah="halaman.total" :kolom="kolom" :baris="halaman.data"
               :nomor-awal="halaman.from ?? 1" :tautan="halaman.links">
      <template #alat>
        <div v-if="register">
          <button type="button" class="mn-btn mn-btn-utama" @click="bukaTambah">+ add Permit</button>
        </div>
        <template v-else>
          <div class="mn-alat">
            <input v-model="dari" type="date" class="mn-isian" title="Dari tanggal" aria-label="Dari tanggal">
            <input v-model="sampai" type="date" class="mn-isian" title="Sampai tanggal" aria-label="Sampai tanggal">
            <a :href="urlEkspor" class="mn-btn mn-btn-garis">Export</a>
          </div>
          <button v-if="props.kelola" type="button" class="mn-btn mn-btn-garis-utama" :disabled="sinkronForm.processing" @click="sinkron">
            ⟳ Sinkronkan dari MCU
          </button>
        </template>
      </template>

      <template #sel-nomor="{ baris: b }">
        <Link :href="`/miners/permit/${b.id}`" class="mn-tautan" style="color:inherit">{{ b.nomor || 'tanpa nomor' }}</Link>
      </template>
      <template #sel-tanggal="{ nilai }">{{ tglId(nilai) }}</template>
      <template #sel-nama="{ baris: b }">
        <Link :href="`/miners/permit/${b.id}`" class="mn-tautan">{{ b.nama || '—' }}</Link>
        <span v-if="!b.aktif" class="mn-lencana mn-l-abu ml-1" :title="b.alasanNonaktif || ''">non-aktif</span>
      </template>
      <template #sel-berlaku="{ baris: b }">
        <LencanaMasaBerlaku :tanggal="b.berlaku" :sisa="b.sisa" :sumber="b.sumber" />
      </template>
      <template #sel-status="{ nilai }"><LencanaStatus :status="nilai" /></template>
      <template #sel-aksi="{ baris: b }">
        <div class="mn-aksi" style="justify-content:flex-start">
          <template v-if="register">
            <TombolIkon v-if="b.bolehHapus" ikon="hapus" varian="bahaya" judul="Hapus Mine Permit" @click="hapus(b)" />
            <TombolIkon ikon="pensil" :href="`/miners/permit/${b.id}`" judul="Buka detail Mine Permit" />
          </template>
          <template v-else-if="props.kelola">
            <TombolIkon ikon="kalender" varian="garis" judul="Masa berlaku" @click="bukaMasa(b)" />
            <TombolIkon ikon="orang" varian="garis" :judul="b.aktif ? 'Nonaktifkan kartu' : 'Aktifkan kembali'" @click="bukaStatus(b)" />
          </template>
          <TombolIkon v-else ikon="mata" varian="garis" :href="`/miners/permit/${b.id}`" judul="Buka detail" />
        </div>
      </template>
    </TabelData>

    <!-- ══════════ tambah permit ══════════ -->
    <Modal :terbuka="tambahTerbuka" judul="Add Mine Permit" lebar @tutup="tambahTerbuka = false">
      <form class="mn-form" @submit.prevent="simpan">
        <Medan label="Nama Employee" untuk="p-calon" wajib :galat="form.errors.induksi_orang_id" penuh
               petunjuk="Karyawan yang induksinya sudah disetujui dan belum punya Mine Permit.">
          <select v-if="calon.length" id="p-calon" v-model="form.induksi_orang_id" class="mn-isian" title="Nama employee" @change="pilihCalon">
            <option value="">— pilih —</option>
            <option v-for="c in calon" :key="c.id" :value="c.id">{{ c.noDaftar }} - {{ c.nama }}{{ c.perusahaan ? ` (${c.perusahaan})` : '' }}</option>
          </select>
          <div v-else class="mn-alert mn-alert-peringatan" style="margin:0">
            Belum ada karyawan yang induksinya selesai. Selesaikan dulu induksi karyawannya; sesudah disetujui, namanya muncul di sini.
          </div>
        </Medan>
        <Medan label="Nomor Induk" untuk="p-noid">
          <input id="p-noid" class="mn-isian" value="Otomatis = nomor Mine Permit" readonly>
        </Medan>
        <Medan label="Tanggal Lahir" untuk="p-lahir" wajib :galat="form.errors.tanggal_lahir">
          <input id="p-lahir" v-model="form.tanggal_lahir" type="date" class="mn-isian" required>
        </Medan>
        <Medan label="Golongan Darah" untuk="p-darah" :galat="form.errors.gol_darah">
          <select id="p-darah" v-model="form.gol_darah" class="mn-isian" title="Golongan darah">
            <option value="">—</option>
            <option v-for="g in pilihan.golDarah" :key="g" :value="g">{{ g }}</option>
          </select>
        </Medan>
        <Medan label="No.Telp" untuk="p-telp" :galat="form.errors.telepon">
          <input id="p-telp" v-model="form.telepon" class="mn-isian" maxlength="30" inputmode="tel">
        </Medan>
        <Medan label="No Emergency" untuk="p-telp2" :galat="form.errors.telepon_darurat">
          <input id="p-telp2" v-model="form.telepon_darurat" class="mn-isian" maxlength="30" inputmode="tel">
        </Medan>
        <Medan label="Cakupan Area" untuk="p-area" wajib :galat="form.errors.cakupan_area">
          <select id="p-area" v-model="form.cakupan_area" class="mn-isian" title="Cakupan area">
            <option v-for="c in pilihan.cakupan" :key="c.nilai" :value="c.nilai">{{ c.label }}</option>
          </select>
        </Medan>
        <Medan label="Kode Warna Akses" untuk="p-warna" :galat="form.errors.kode_warna"
               petunjuk="Kosongkan bila warnanya mengikuti unit yang boleh dioperasikan.">
          <select id="p-warna" v-model="form.kode_warna" class="mn-isian" title="Kode warna akses">
            <option value="">Ikuti unit SIMPER (otomatis)</option>
            <option v-for="w in pilihan.warna" :key="w.nilai" :value="w.nilai">{{ w.label }}</option>
          </select>
        </Medan>
        <Medan label="Type Card" untuk="p-jenis" wajib :galat="form.errors.jenis_kartu">
          <select id="p-jenis" v-model="form.jenis_kartu" class="mn-isian" title="Type card">
            <option v-for="j in pilihan.jenisKartu" :key="j" :value="j">{{ j }}</option>
          </select>
        </Medan>
        <Medan label="Departemen" untuk="p-dept" wajib :galat="form.errors.departemen_id">
          <select id="p-dept" v-model="form.departemen_id" class="mn-isian" title="Departemen">
            <option value="">— pilih —</option>
            <option v-for="d in pilihan.departemen" :key="d.nilai" :value="d.nilai">{{ d.label }}</option>
          </select>
        </Medan>
        <Medan label="Perusahaan (IUP)" untuk="p-iup" wajib :galat="form.errors.iup_id" petunjuk="Pemegang izin tambang tempat kartu ini berlaku.">
          <select id="p-iup" v-model="form.iup_id" class="mn-isian" title="Perusahaan (IUP)">
            <option value="">— pilih —</option>
            <option v-for="c in pilihan.iup" :key="c.nilai" :value="c.nilai">{{ c.label }}</option>
          </select>
        </Medan>
        <Medan label="Contractor (IUJP)" untuk="p-kontraktor" :galat="form.errors.kontraktor_id"
               petunjuk="Wajib bila karyawannya dari perusahaan jasa pertambangan; kosongkan bila ia karyawan IUP sendiri.">
          <select id="p-kontraktor" v-model="form.kontraktor_id" class="mn-isian" title="Contractor (IUJP)">
            <option value="">— Karyawan IUP, tanpa kontraktor —</option>
            <option v-for="c in pilihan.kontraktor" :key="c.nilai" :value="c.nilai">{{ c.label }}</option>
          </select>
        </Medan>
        <Medan label="Foto Employee" untuk="p-foto" wajib :galat="form.errors.foto" petunjuk="JPG atau PNG, maksimal 2 MB.">
          <input id="p-foto" type="file" class="mn-isian" accept=".jpg,.jpeg,.png" @change="berkas('foto', $event)">
        </Medan>
        <Medan label="Foto KTP" untuk="p-ktp" wajib :galat="form.errors.ktp">
          <input id="p-ktp" type="file" class="mn-isian" accept=".jpg,.jpeg,.png,.pdf" @change="berkas('ktp', $event)">
        </Medan>
        <Medan label="Permohonan Permit" untuk="p-mohon" wajib :galat="form.errors.permohonan">
          <input id="p-mohon" type="file" class="mn-isian" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" @change="berkas('permohonan', $event)">
        </Medan>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="tambahTerbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="form.processing || !calon.length" @click="simpan">Save changes</button>
      </template>
    </Modal>

    <!-- ══════════ masa berlaku ══════════ -->
    <Modal :terbuka="masaTerbuka !== null" judul="Masa Berlaku Mine Permit" @tutup="masaTerbuka = null">
      <p class="text-[13px] mb-3"><b>{{ masaTerbuka?.nama }}</b> — {{ masaTerbuka?.nomor }}</p>
      <div class="mn-form">
        <Medan label="Sumber" untuk="m-sumber" penuh :galat="masaForm.errors.sumber">
          <select id="m-sumber" v-model="masaForm.sumber" class="mn-isian" title="Sumber masa berlaku">
            <option value="mcu">Ikuti masa berlaku MCU</option>
            <option value="manual">Kunci tanggal manual</option>
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

    <!-- ══════════ status kartu ══════════ -->
    <Modal :terbuka="statusTerbuka !== null" :judul="statusForm.aktif ? 'Aktifkan kembali' : 'Nonaktifkan Mine Permit'" @tutup="statusTerbuka = null">
      <p class="text-[13px] mb-3"><b>{{ statusTerbuka?.nama }}</b> —
        <template v-if="statusForm.aktif">kartunya kembali dihitung sebagai dokumen aktif.</template>
        <template v-else>kartu ditutup (dicabut, hilang, dsb.). Datanya tidak dihapus, tetapi tidak lagi dihitung pada zona masa berlaku.</template>
      </p>
      <Medan v-if="!statusForm.aktif" label="Alasan" untuk="st-alasan" :galat="statusForm.errors.alasan">
        <input id="st-alasan" v-model="statusForm.alasan" class="mn-isian" maxlength="200" placeholder="mis. kartu hilang, mutasi">
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
