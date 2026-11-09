<script setup lang="ts">
/**
 * Data Master Induksi — `master_induction_user` (Register: "Induksi
 * Table" dengan "+ Add Induksi") dan `master_induction_admin` (History:
 * rentang tanggal + Export) Safe Track.
 */
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
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
const alamat = computed(() => (register.value ? '/miners/induksi' : '/miners/riwayat/induksi'));

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
  return `/miners/induksi/ekspor?${q.toString()}`;
});

const terbuka = ref(false);
const form = useForm({ tanggal: '', perihal: '', berkas_permohonan: null as File | null });

function tambah() {
  form.reset();
  form.clearErrors();
  form.tanggal = String(props.hariIni ?? '');
  terbuka.value = true;
}

function simpan() {
  form.post('/miners/induksi', { forceFormData: true, preserveScroll: true, onSuccess: () => { terbuka.value = false; } });
}

const hapusForm = useForm({});
async function hapus(b: any) {
  if (!await tanya({ judul: 'Hapus pengajuan induksi', pesan: `Hapus pengajuan ${b.nomor} beserta pesertanya?`, labelAksi: 'Hapus', nada: 'bahaya' })) return;
  hapusForm.delete(`/miners/induksi/${b.id}`, { preserveScroll: true });
}
</script>

<template>
  <div>
    <KepalaHalaman :judul="props.judul" :remah="[['Home', '/miners/dasbor'], [register ? 'Register' : 'History', null], ['Induction', null]]" />

    <div v-if="props.errors?.hapus" class="mn-alert mn-alert-bahaya" role="alert">{{ props.errors.hapus }}</div>

    <Kartu :judul="register ? 'Induksi Table' : undefined">
      <div v-if="register" class="mb-4">
        <button type="button" class="mn-btn mn-btn-gelap" @click="tambah">+ Add Induksi</button>
      </div>

      <div class="mb-3 grid gap-2">
        <div v-if="!register" class="mn-alat">
          <input v-model="dari" type="date" class="mn-isian" title="Dari tanggal" aria-label="Dari tanggal">
          <input v-model="sampai" type="date" class="mn-isian" title="Sampai tanggal" aria-label="Sampai tanggal">
          <a :href="urlEkspor" class="mn-btn mn-btn-bahaya">Export</a>
        </div>
        <form class="mn-alat-cari" @submit.prevent="telusuri">
          <input v-model="cari" class="mn-isian" placeholder="search.." aria-label="Cari nomor atau judul">
          <button type="submit" class="mn-btn mn-btn-utama">search</button>
        </form>
      </div>

      <div class="mn-gulir">
        <table class="mn-tabel">
          <thead>
            <tr>
              <th style="width:42px">No</th>
              <th>Tanggal</th>
              <th>No Registrasi</th>
              <th>Title</th>
              <th>Form Pengajuan</th>
              <th>Status</th>
              <th>Creator</th>
              <th v-if="register">#</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(b, i) in halaman.data" :key="b.id">
              <td>{{ (halaman.from ?? 1) + i }}</td>
              <td>{{ tglId(b.tanggal) }}</td>
              <td><Link :href="`/miners/induksi/${b.id}`" class="mn-tautan">{{ b.nomor || 'tanpa nomor' }}</Link></td>
              <td style="max-width:240px">{{ b.perihal || '—' }}<div class="mn-redup" style="font-size:11px">{{ b.peserta }} peserta</div></td>
              <td>
                <a v-if="b.permohonan?.url" :href="b.permohonan.url" target="_blank" rel="noopener" class="mn-tautan">unduh</a>
                <span v-else class="mn-redup">—</span>
              </td>
              <td><LencanaStatus :status="b.status" /></td>
              <td>{{ b.oleh || '—' }}</td>
              <td v-if="register">
                <TombolIkon v-if="b.bolehHapus" ikon="hapus" varian="bahaya" judul="Hapus pengajuan induksi" @click="hapus(b)" />
              </td>
            </tr>
            <tr v-if="!halaman.data.length">
              <td :colspan="register ? 8 : 7" class="mn-kosong">Tidak ada pengajuan induksi.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Halaman v-if="halaman.links?.length > 3" :tautan="halaman.links" />
    </Kartu>

    <Modal :terbuka="terbuka" judul="Add Induksi" @tutup="terbuka = false">
      <form class="mn-form" @submit.prevent="simpan">
        <Medan label="Tanggal Pengajuan" untuk="i-tgl" wajib :galat="form.errors.tanggal">
          <input id="i-tgl" v-model="form.tanggal" type="date" class="mn-isian" readonly>
        </Medan>
        <Medan label="File Permohonan" untuk="i-berkas" wajib :galat="form.errors.berkas_permohonan">
          <input id="i-berkas" type="file" class="mn-isian" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                 @change="form.berkas_permohonan = ($event.target as HTMLInputElement).files?.[0] ?? null">
        </Medan>
        <Medan label="Title" untuk="i-title" wajib :galat="form.errors.perihal" penuh>
          <input id="i-title" v-model="form.perihal" class="mn-isian" maxlength="500" placeholder="Permohonan Induksi Karyawan Baru" required>
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
