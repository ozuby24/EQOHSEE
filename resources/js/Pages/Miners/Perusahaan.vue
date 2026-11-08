<script setup lang="ts">
/**
 * Master Data → Perusahaan — `master_company` Safe Track: IUP beserta
 * mitra (IUJP)-nya, dan identitas yang dicetak pada kartu dan judul kolom
 * persetujuan.
 */
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import TabelData from '../../Components/Miners/TabelData.vue';
import TombolIkon from '../../Components/Miners/TombolIkon.vue';
import Modal from '../../Components/Miners/Modal.vue';
import Medan from '../../Components/Miners/Medan.vue';

const props = propHalaman();

const baris = computed<any[]>(() => (props.baris ?? []) as any[]);
const iup = computed<any[]>(() => (props.iup ?? []) as any[]);

const kolom = [
  { kunci: 'nama', label: 'Perusahaan' },
  { kunci: 'kode', label: 'Kode' },
  { kunci: 'nama_pendek', label: 'Nama Pendek' },
  { kunci: 'jenis', label: 'Jenis' },
  { kunci: 'induk', label: 'IUP' },
  { kunci: 'ktt', label: 'KTT' },
  { kunci: 'aksi', label: '#' },
];

const diubah = ref<any>(null);
const form = useForm<Record<string, any>>({
  nama_pendek: '', code: '', parent_id: '', ktt: '', jabatan_ktt: '', no_izin: '', channel_darurat: '', theme_color: '',
});

function ubah(b: any) {
  diubah.value = b;
  form.defaults(Object.fromEntries(Object.entries(b.nilai).map(([k, v]) => [k, v ?? ''])));
  form.reset();
  form.clearErrors();
}

function simpan() {
  form.put(`/miners/master/perusahaan/${diubah.value.id}`, {
    preserveScroll: true,
    onSuccess: () => { diubah.value = null; },
  });
}
</script>

<template>
  <div>
    <KepalaHalaman judul="Data Perusahaan" :remah="[['Home', '/miners/dasbor'], ['Master Data', null], ['Perusahaan', null]]" />

    <TabelData judul="Perusahaan Table" :jumlah="baris.length" :kolom="kolom" :baris="baris">
      <template #sel-nama="{ baris: b }">
        <span :style="b.jenis === 'IUJP' ? 'padding-left:14px' : 'font-weight:600'">{{ b.nama }}</span>
      </template>
      <template #sel-jenis="{ nilai }">
        <span class="mn-lencana" :class="nilai === 'IUP' ? 'mn-l-utama' : 'mn-l-terang'">{{ nilai }}</span>
      </template>
      <template #sel-induk="{ baris: b }">
        <span v-if="b.jenis === 'IUJP' && !b.induk" class="mn-lencana mn-l-kuning">belum dipasangkan</span>
        <span v-else>{{ b.induk || '—' }}</span>
      </template>
      <template #sel-aksi="{ baris: b }">
        <div class="mn-aksi"><TombolIkon ikon="pensil" judul="Ubah identitas perusahaan" @click="ubah(b)" /></div>
      </template>
    </TabelData>

    <Modal :terbuka="diubah !== null" :judul="`Ubah ${diubah?.nama ?? ''}`" lebar @tutup="diubah = null">
      <form class="mn-form" @submit.prevent="simpan">
        <Medan label="Kode" untuk="pr-kode" :galat="form.errors.code" petunjuk="Dipakai pada nomor registrasi Mine Permit.">
          <input id="pr-kode" v-model="form.code" class="mn-isian" maxlength="20">
        </Medan>
        <Medan label="Nama pendek" untuk="pr-pendek" :galat="form.errors.nama_pendek" petunjuk="Judul kolom persetujuan, mis. HSE PT. ABC.">
          <input id="pr-pendek" v-model="form.nama_pendek" class="mn-isian" maxlength="60">
        </Medan>
        <Medan label="IUP induk (bagi IUJP)" untuk="pr-induk" :galat="form.errors.parent_id" penuh>
          <select id="pr-induk" v-model="form.parent_id" class="mn-isian" title="IUP induk">
            <option value="">— IUP (tanpa induk) —</option>
            <option v-for="o in iup" :key="o.nilai" :value="o.nilai" :disabled="o.nilai === diubah?.id">{{ o.label }}</option>
          </select>
        </Medan>
        <Medan label="Nama KTT" untuk="pr-ktt" :galat="form.errors.ktt">
          <input id="pr-ktt" v-model="form.ktt" class="mn-isian" maxlength="150">
        </Medan>
        <Medan label="Jabatan KTT" untuk="pr-jabktt" :galat="form.errors.jabatan_ktt">
          <input id="pr-jabktt" v-model="form.jabatan_ktt" class="mn-isian" maxlength="120">
        </Medan>
        <Medan label="No izin" untuk="pr-izin" :galat="form.errors.no_izin">
          <input id="pr-izin" v-model="form.no_izin" class="mn-isian" maxlength="120">
        </Medan>
        <Medan label="Channel darurat" untuk="pr-darurat" :galat="form.errors.channel_darurat">
          <input id="pr-darurat" v-model="form.channel_darurat" class="mn-isian" maxlength="120" placeholder="mis. Radio CH 7">
        </Medan>
        <Medan label="Warna kartu" untuk="pr-warna" :galat="form.errors.theme_color">
          <input id="pr-warna" v-model="form.theme_color" class="mn-isian" maxlength="7" placeholder="#1F4E79">
        </Medan>
      </form>
      <template #kaki>
        <button type="button" class="mn-btn mn-btn-garis" @click="diubah = null">Close</button>
        <button type="button" class="mn-btn mn-btn-utama" :disabled="form.processing" @click="simpan">Save</button>
      </template>
    </Modal>
  </div>
</template>
