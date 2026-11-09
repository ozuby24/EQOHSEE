<script setup lang="ts">
/**
 * Data MCU — `master_mcu_user` (Register) dan `master_mcu_admin`
 * (History) Safe Track.
 *
 * Tiga kartu ringkas (Manpower · Rujukan | Outstanding · Open | Total),
 * tombol "+ add MCU" (Register), rentang tanggal + Export, kotak cari,
 * lalu tabel surat MCU. Register hanya surat buatan sendiri dengan
 * tombol ubah dan hapus; History seluruh surat dalam jangkauan, hanya baca.
 */
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import KartuInfo from '../../Components/Miners/KartuInfo.vue';
import Kartu from '../../Components/Miners/Kartu.vue';
import Halaman from '../../Components/Miners/Halaman.vue';
import LencanaStatus from '../../Components/Miners/LencanaStatus.vue';
import TombolIkon from '../../Components/Miners/TombolIkon.vue';
import Modal from '../../Components/Miners/Modal.vue';
import Medan from '../../Components/Miners/Medan.vue';
import { tglId } from '../../Components/Miners/zona';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

const props = propHalaman();

/* Penegasan memakai dialog aplikasi, bukan confirm() peramban — di
   webview ponsel dialog bawaan dibungkam dan tindakannya diam-diam batal. */
const { dialog, tanya, batal, lanjut } = useDialog();

const register = computed(() => props.mode === 'register');
const halaman = computed<any>(() => props.baris ?? { data: [], links: [], from: 1, total: 0 });
const perusahaan = computed<any[]>(() => (props.perusahaan ?? []) as any[]);
const alamat = computed(() => (register.value ? '/miners/mcu' : '/miners/riwayat/mcu'));

const cari = ref(String(props.saring?.cari ?? ''));
const dari = ref('');
const sampai = ref('');

function telusuri() {
  router.get(alamat.value, cari.value ? { cari: cari.value } : {}, { preserveState: true, preserveScroll: true });
}

const urlEkspor = computed(() => {
  const q = new URLSearchParams({ mode: String(props.mode) });
  if (dari.value) q.set('dari', dari.value);
  if (sampai.value) q.set('sampai', sampai.value);
  return `/miners/mcu/ekspor?${q.toString()}`;
});

/* ─────────── surat baru / ubah ─────────── */

const terbuka = ref(false);
const diubah = ref<any>(null);

const form = useForm({ tanggal: '', kepada: '', perihal: '', catatan: '', company_id: '' as string | number });

function tambah() {
  diubah.value = null;
  form.defaults({
    tanggal: String(props.hariIni ?? ''), kepada: String(props.dokterSkema ?? ''),
    perihal: 'Permohonan Sertifikat MCU', catatan: '',
    company_id: perusahaan.value.length === 1 ? perusahaan.value[0].nilai : '',
  });
  form.reset();
  form.clearErrors();
  terbuka.value = true;
}

function ubah(b: any) {
  diubah.value = b;
  form.defaults({ tanggal: b.tanggal ?? '', kepada: b.kepada ?? '', perihal: b.perihal ?? '', catatan: b.catatan ?? '', company_id: b.company_id ?? '' });
  form.reset();
  form.clearErrors();
  terbuka.value = true;
}

function simpan() {
  const opsi = { preserveScroll: true, onSuccess: () => { terbuka.value = false; } };
  if (diubah.value) form.put(`/miners/mcu/${diubah.value.id}`, opsi);
  else form.post('/miners/mcu', opsi);
}

const hapusForm = useForm({});

async function hapus(b: any) {
  if (!await tanya({
    judul: 'Hapus surat MCU',
    pesan: `Hapus surat ${b.nomor} beserta seluruh manpower di dalamnya? Surat yang namanya sudah menjadi dasar induksi atau Mine Permit tidak dapat dihapus.`,
    labelAksi: 'Hapus', nada: 'bahaya',
  })) return;
  hapusForm.delete(`/miners/mcu/${b.id}`, { preserveScroll: true });
}
</script>

<template>
  <div>
    <KepalaHalaman :judul="props.judul" :remah="[['Home', '/miners/dasbor'], [register ? 'Register' : 'History', null], ['MCU', null]]" />

    <div v-if="props.errors?.hapus" class="mn-alert mn-alert-bahaya" role="alert">{{ props.errors.hapus }}</div>

    <div class="mn-info-grid">
      <KartuInfo judul="Manpower" :angka="props.kartu?.manpower" />
      <KartuInfo judul="Rujukan" keterangan="Outstanding" :angka="props.kartu?.rujukan" warna="hijau" />
      <KartuInfo judul="Open" keterangan="Total" :angka="props.kartu?.open" warna="oranye" />
    </div>

    <Kartu>
      <div v-if="register" class="mb-4">
        <button type="button" class="mn-btn mn-btn-gelap" @click="tambah">+ add MCU</button>
      </div>

      <div class="mb-3 grid gap-2">
        <div class="mn-alat">
          <input v-model="dari" type="date" class="mn-isian" title="Dari tanggal" aria-label="Dari tanggal">
          <input v-model="sampai" type="date" class="mn-isian" title="Sampai tanggal" aria-label="Sampai tanggal">
          <a :href="urlEkspor" class="mn-btn mn-btn-bahaya">Export</a>
        </div>
        <form class="mn-alat-cari" @submit.prevent="telusuri">
          <input v-model="cari" class="mn-isian" placeholder="search.." aria-label="Cari nomor, judul, atau tujuan">
          <button type="submit" class="mn-btn mn-btn-utama">search</button>
        </form>
      </div>

      <div class="mn-gulir">
        <table class="mn-tabel">
          <thead>
            <tr>
              <th style="width:42px">No</th>
              <th>Date MCU</th>
              <th>No Registrasi</th>
              <th>Title</th>
              <th>Perusahaan</th>
              <th>Status</th>
              <th>Creator</th>
              <th v-if="register">#</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(b, i) in halaman.data" :key="b.id">
              <td>{{ (halaman.from ?? 1) + i }}</td>
              <td>{{ tglId(b.tanggal) }}</td>
              <td><Link :href="`/miners/mcu/${b.id}`" class="mn-tautan">{{ b.nomor || 'tanpa nomor' }}</Link></td>
              <td>{{ b.perihal || '—' }}</td>
              <td>{{ b.perusahaan || '—' }}</td>
              <td><LencanaStatus :status="b.status" :label="props.STATUS?.[b.status]" /></td>
              <td>{{ b.oleh || '—' }}</td>
              <td v-if="register">
                <div class="mn-aksi" style="justify-content:flex-start">
                  <TombolIkon v-if="b.bolehUbah" ikon="pensil" judul="Ubah surat MCU" @click="ubah(b)" />
                  <TombolIkon v-if="b.bolehHapus" ikon="hapus" varian="bahaya" judul="Hapus surat MCU" @click="hapus(b)" />
                </div>
              </td>
            </tr>
            <tr v-if="!halaman.data.length">
              <td :colspan="register ? 8 : 7" class="mn-kosong">
                {{ register ? 'Belum ada surat MCU. Tekan "+ add MCU" untuk membuat yang pertama.' : 'Tidak ada surat MCU.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Halaman v-if="halaman.links?.length > 3" :tautan="halaman.links" />
    </Kartu>

    <Modal :terbuka="terbuka" :judul="diubah ? 'Edit MCU' : 'Add MCU'" @tutup="terbuka = false">
      <form class="mn-form" @submit.prevent="simpan">
        <Medan label="Tanggal MCU" untuk="mcu-tgl" wajib :galat="form.errors.tanggal">
          <input id="mcu-tgl" v-model="form.tanggal" type="date" class="mn-isian" :readonly="!diubah" required>
        </Medan>
        <Medan label="Kepada" untuk="mcu-kepada" wajib :galat="form.errors.kepada"
               :petunjuk="!diubah && props.dokterSkema ? 'Dokter pemeriksa pada alur persetujuan IUP Anda.' : null">
          <input id="mcu-kepada" v-model="form.kepada" class="mn-isian" maxlength="200" required>
        </Medan>
        <Medan label="Title" untuk="mcu-title" :galat="form.errors.perihal" penuh>
          <input id="mcu-title" v-model="form.perihal" class="mn-isian" maxlength="500">
        </Medan>
        <Medan label="Perusahaan" untuk="mcu-pt" wajib :galat="form.errors.company_id" penuh
               petunjuk="Perusahaan tempat karyawannya bekerja.">
          <select id="mcu-pt" v-model="form.company_id" class="mn-isian" title="Perusahaan" :disabled="perusahaan.length === 1">
            <option v-if="perusahaan.length !== 1" value="">— pilih perusahaan —</option>
            <option v-for="p in perusahaan" :key="p.nilai" :value="p.nilai">{{ p.label }}</option>
          </select>
        </Medan>
        <Medan v-if="diubah" label="Catatan" untuk="mcu-catatan" :galat="form.errors.catatan" penuh>
          <textarea id="mcu-catatan" v-model="form.catatan" class="mn-isian" rows="2" />
        </Medan>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="terbuka = false">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="form.processing" @click="simpan">Save</button>
      </template>
    </Modal>

    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>
