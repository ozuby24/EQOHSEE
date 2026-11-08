<script setup lang="ts">
/**
 * Master Data Miners — layar "Data Master Unit" Safe Track (14-masterdata-unit):
 * tabel sederhana dengan tombol "+ add …" gelap dan pensil per baris.
 *
 * Satu halaman untuk delapan daftar; medannya dikirim server. Baris
 * yang masih dipakai tidak dihapus, melainkan dinonaktifkan — lihat
 * MasterDataController.
 */
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import TabelData from '../../Components/Miners/TabelData.vue';
import TombolIkon from '../../Components/Miners/TombolIkon.vue';
import Modal from '../../Components/Miners/Modal.vue';
import Medan from '../../Components/Miners/Medan.vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const medan = computed<any[]>(() => (props.medan ?? []) as any[]);
const baris = computed<any[]>(() => ((props.baris ?? []) as any[]).map((b) => ({ ...b, ...b.tampil })));

const kolom = computed(() => [
  ...medan.value.map((m) => ({ kunci: m.kunci, label: m.label })),
  { kunci: 'milik', label: 'Daftar' },
  { kunci: 'dipakai', label: 'Dipakai', kelas: 'mn-angka' },
  ...(props.pakaiAktif ? [{ kunci: 'status', label: 'Status' }] : []),
  { kunci: 'aksi', label: '#' },
]);

const alamat = computed(() => `/miners/master/${props.jenis}`);

const terbuka = ref(false);
const diubah = ref<any>(null);
const form = useForm<Record<string, any>>({});

function isianKosong(): Record<string, any> {
  const isi: Record<string, any> = {};
  for (const m of medan.value) isi[m.kunci] = m.jenis === 'centang' ? false : '';
  return isi;
}

function tambah() {
  diubah.value = null;
  form.defaults(isianKosong());
  form.reset();
  form.clearErrors();
  terbuka.value = true;
}

function ubah(b: any) {
  diubah.value = b;
  const isi = isianKosong();
  for (const m of medan.value) isi[m.kunci] = b.nilai[m.kunci] ?? (m.jenis === 'centang' ? false : '');
  form.defaults(isi);
  form.reset();
  form.clearErrors();
  terbuka.value = true;
}

function simpan() {
  const opsi = { preserveScroll: true, onSuccess: () => { terbuka.value = false; } };
  if (diubah.value) form.put(`${alamat.value}/${diubah.value.id}`, opsi);
  else form.post(alamat.value, opsi);
}

function aktifkan(b: any) {
  const isi: Record<string, any> = { ...b.nilai, aktif: !b.aktif };
  router.put(`${alamat.value}/${b.id}`, isi, { preserveScroll: true });
}

/* Baris yang masih dipakai dinonaktifkan server, bukan dihapus — dan
   penegasannya mengatakan itu lebih dulu. */
async function hapus(b: any) {
  const ok = await tanya({
    judul: `Hapus ${props.remah}`,
    pesan: b.dipakai
      ? `"${b.nama}" masih dipakai ${b.dipakai} data. Ia akan dinonaktifkan, tidak dihapus — dokumen lama tetap membaca namanya.`
      : `"${b.nama}" akan dihapus.`,
    labelAksi: b.dipakai ? 'Nonaktifkan' : 'Hapus',
    nada: 'bahaya',
  });

  if (ok) router.delete(`${alamat.value}/${b.id}`, { preserveScroll: true });
}
</script>

<template>
  <div>
    <KepalaHalaman :judul="props.judul" :remah="[['Home', '/miners/dasbor'], ['Master Data', null], [props.remah, null]]" />

    <TabelData :judul="props.tabel" :jumlah="baris.length" :kolom="kolom" :baris="baris" kosong="Belum ada data.">
      <template #alat>
        <button type="button" class="mn-btn mn-btn-gelap" @click="tambah">+ {{ props.tombol }}</button>
      </template>

      <template #sel-milik="{ baris: b }">
        <span class="mn-lencana" :class="b.bersama ? 'mn-l-info' : 'mn-l-terang'">{{ b.milik }}</span>
      </template>
      <template #sel-dipakai="{ nilai }">{{ nilai || 0 }}</template>
      <template #sel-status="{ baris: b }">
        <span class="mn-lencana" :class="b.aktif ? 'mn-l-sukses' : 'mn-l-abu'">{{ b.aktif ? 'Active' : 'Non-aktif' }}</span>
      </template>
      <template #sel-aksi="{ baris: b }">
        <div v-if="b.boleh" class="mn-aksi">
          <TombolIkon ikon="pensil" judul="Ubah" @click="ubah(b)" />
          <TombolIkon v-if="props.pakaiAktif && !b.aktif" ikon="cek" varian="sukses" judul="Aktifkan kembali" @click="aktifkan(b)" />
          <TombolIkon v-else ikon="hapus" varian="garis" judul="Hapus atau nonaktifkan" @click="hapus(b)" />
        </div>
        <span v-else class="mn-redup text-[11.5px]" title="Daftar bersama hanya diubah administrator.">bersama</span>
      </template>
    </TabelData>

    <Modal :terbuka="terbuka" :judul="(diubah ? 'Ubah ' : 'Tambah ') + props.remah" @tutup="terbuka = false">
      <form class="mn-form" @submit.prevent="simpan">
        <Medan v-for="m in medan" :key="m.kunci" :label="m.label" :untuk="`m-${m.kunci}`" :wajib="m.wajib"
               :galat="form.errors[m.kunci]" :penuh="m.jenis === 'centang' || medan.length === 1">
          <select v-if="m.jenis === 'pilih' || m.jenis === 'induk'" :id="`m-${m.kunci}`" v-model="form[m.kunci]" class="mn-isian" :title="m.label">
            <option value="">—</option>
            <option v-for="o in m.opsi" :key="o.nilai" :value="o.nilai">{{ o.label }}</option>
          </select>
          <label v-else-if="m.jenis === 'centang'" class="flex items-center gap-2 text-[13px]">
            <input :id="`m-${m.kunci}`" v-model="form[m.kunci]" type="checkbox"> Ya
          </label>
          <input v-else :id="`m-${m.kunci}`" v-model="form[m.kunci]" class="mn-isian"
                 :type="m.jenis === 'angka' ? 'number' : m.jenis === 'surel' ? 'email' : 'text'"
                 :min="m.jenis === 'angka' ? 0 : undefined">
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
