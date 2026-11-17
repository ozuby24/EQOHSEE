<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import CetakLayout from '../../../Components/Smkp/CetakLayout.vue';
import Lembar from '../../../Components/Smkp/Lembar.vue';
import FormIdentitas from '../../../Components/Smkp/FormIdentitas.vue';
import FormTtd from '../../../Components/Smkp/FormTtd.vue';
import type { KopDok, RingkasAudit, TtdForm } from '../../../smkp/types';
import BlankLayout from '../../../Layouts/BlankLayout.vue';

/* Lembar cetak di luar kerangka aplikasi (konvensi EQOHSEE: Pages/Print + BlankLayout). */
defineOptions({ layout: BlankLayout });

interface Baris { elemen: string; elemenNama: string; sub: string; subNama: string; subsub: string; subsubNama: string; isi: string }
const props = defineProps<{ audit: RingkasAudit; dok: KopDok; ttd: TtdForm; kembali: string; baris: Baris[] }>();

// Tiga blok per lembar seperti formulir asli; formulir kosong tetap tercetak tiga blok.
const halaman = computed(() => {
  const isi: Array<Baris | null> = props.baris.length ? [...props.baris] : [null, null, null];
  const out: Array<Array<Baris | null>> = [];
  for (let i = 0; i < isi.length; i += 3) out.push(isi.slice(i, i + 3));
  return out;
});
</script>

<template>
  <Head title="Formulir Rekomendasi & OFI" />
  <CetakLayout judul="Formulir Rekomendasi dan Peluang untuk Perbaikan" :kembali="kembali">
    <Lembar v-for="(h, i) in halaman" :key="i" :dok="dok" :halaman="i + 1" :dari="halaman.length">
      <p class="form-judul">Formulir Rekomendasi dan Peluang untuk Perbaikan<br>Penerapan Sistem Manajemen Keselamatan Pertambangan *)</p>
      <FormIdentitas :baris="[['Nama Perusahaan Auditi', audit.perusahaan.nama], ['Tanggal Audit', audit.periode]]" />
      <p class="font-bold text-slate-900 mb-2">Uraian Rekomendasi dan Peluang untuk Perbaikan</p>
      <table v-for="(b, j) in h" :key="j" class="tabel-form hindari-putus mb-4">
        <tbody>
          <tr><td class="label-sel">Elemen</td><td>{{ b ? `${b.elemen} — ${b.elemenNama}` : '' }}</td></tr>
          <tr><td class="label-sel">Sub Elemen</td><td>{{ b?.sub ? `${b.sub} — ${b.subNama}` : '' }}</td></tr>
          <tr v-if="!b || b.subsub"><td class="label-sel">Sub-sub Elemen</td><td>{{ b?.subsub ? `${b.subsub} — ${b.subsubNama}` : '' }}</td></tr>
          <tr><td class="label-sel">Rekomendasi dan Peluang untuk Perbaikan</td><td class="h-16 whitespace-pre-line text-justify">{{ b?.isi ?? '' }}</td></tr>
        </tbody>
      </table>
      <FormTtd v-if="i === halaman.length - 1" :pihak="[{ peran: 'Nama Auditor', kunci: 'ketua', nama: ttd.ketua }]" :ttd="ttd" bagian="rekomendasi" />
      <p v-if="i === halaman.length - 1" class="mt-4 text-[10.5px] font-bold text-slate-700">*) tidak wajib diisi oleh setiap Auditor</p>
    </Lembar>
  </CetakLayout>
</template>
