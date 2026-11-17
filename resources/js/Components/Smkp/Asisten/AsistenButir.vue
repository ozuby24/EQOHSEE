<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import TombolAsisten from './TombolAsisten.vue';
import PanelAsisten from './PanelAsisten.vue';
import BagianAsisten from './BagianAsisten.vue';
import UsulanIsian from './UsulanIsian.vue';
import { gunakanAsisten } from './api';
import type { SaranButir } from '../../../smkp/types';

/*
 * Asisten untuk satu butir kriteria pada formulir penilaian SMKP (Smkp/Nilai).
 * Mengirim isian butir saat ini — termasuk yang belum disimpan — dan
 * menerapkan saran ke kolom nilai (v) atau catatan auditor (ket).
 */
const props = defineProps<{
  auditId: number;
  butir: { kode: string; nama: string; maks: number };
  isian: { v: string; ket: string; bukti: string };
}>();
const emit = defineEmits<{ terapkan: [kolom: 'v' | 'ket', nilai: string] }>();

const MAKS_KET = 2000;   // batas kolom catatan pada simpanNilai()

const page = usePage<any>();
const buka = ref(false);
const { hasil, memuat, galat, minta } = gunakanAsisten<SaranButir>(
  () => `/asisten/smkp/${props.auditId}/butir`,
  () => ({ kode: props.butir.kode, v: props.isian.v, ket: props.isian.ket, bukti: props.isian.bukti }),
);

function bukaPanel() {
  buka.value = true;
  if (page.props.asisten?.aktif && !hasil.value && !memuat.value) minta();
}

const labelNilai = (v: string) => (v === '' ? 'belum dinilai' : v === 'N/A' ? 'N/A' : `${v} dari ${props.butir.maks}`);
const daftar = (judul: string, isi: string[]) => [judul, ...isi.map((x) => `- ${x}`)].join('\n');

const teksBukti = computed(() => hasil.value ? daftar('Verifikasi:', hasil.value.bukti.map((b) => `${b.label}: ${b.uraian}`)) : '');
const teksTanya = computed(() => hasil.value ? daftar('Pertanyaan untuk auditi:', hasil.value.pertanyaan) : '');
const pakai = (kolom: 'v' | 'ket') => (nilai: string) => emit('terapkan', kolom, nilai);
</script>

<template>
  <span class="inline-flex">
    <TombolAsisten @click="bukaPanel" />
    <PanelAsisten v-model:buka="buka" judul="Saran penilaian butir" :sub="`${butir.kode} · ${butir.nama}`"
                  :memuat="memuat" :galat="galat" :ada-hasil="!!hasil" @ulang="minta">
      <template v-if="hasil">
        <BagianAsisten judul="Saran nilai" :teks="hasil.nilai.alasan">
          <template #lencana><span class="lencana bg-slate-100 text-slate-600">Keyakinan {{ hasil.nilai.keyakinan }}</span></template>
          <p class="tabular-nums text-[22px] font-semibold leading-none text-slate-900">
            {{ hasil.nilai.na ? 'N/A' : hasil.nilai.saran }}<span v-if="!hasil.nilai.na" class="text-[14px] text-slate-400">/{{ hasil.maks }}</span>
          </p>
          <p>{{ hasil.nilai.alasan }}</p>
          <template #aksi>
            <UsulanIsian label="Nilai butir" mode="nilai" :usulan="hasil.nilai.v" :sekarang="isian.v"
                         :tampil="labelNilai(hasil.nilai.v)" :tampil-sekarang="labelNilai(isian.v)" @pakai="pakai('v')" />
          </template>
        </BagianAsisten>

        <BagianAsisten v-if="hasil.bukti.length" judul="Bukti yang perlu diverifikasi" :teks="teksBukti">
          <ul class="space-y-2">
            <li v-for="(b, i) in hasil.bukti" :key="i" class="flex items-start gap-2">
              <span class="lencana mt-0.5 bg-slate-100 text-slate-600">{{ b.label }}</span><span>{{ b.uraian }}</span>
            </li>
          </ul>
          <template #aksi><UsulanIsian label="Catatan auditor" mode="tambah" :usulan="teksBukti" :sekarang="isian.ket" :maks="MAKS_KET" @pakai="pakai('ket')" /></template>
        </BagianAsisten>

        <BagianAsisten v-if="hasil.pertanyaan.length" judul="Pertanyaan untuk auditi" :teks="teksTanya">
          <ol class="list-decimal space-y-1.5 pl-5"><li v-for="(q, i) in hasil.pertanyaan" :key="i">{{ q }}</li></ol>
          <template #aksi><UsulanIsian label="Catatan auditor" mode="tambah" :usulan="teksTanya" :sekarang="isian.ket" :maks="MAKS_KET" @pakai="pakai('ket')" /></template>
        </BagianAsisten>

        <BagianAsisten v-if="hasil.draf_temuan" judul="Draf pernyataan temuan" :teks="hasil.draf_temuan">
          <p>{{ hasil.draf_temuan }}</p>
          <template #aksi><UsulanIsian label="Catatan auditor" mode="tambah" :usulan="`Draf temuan: ${hasil.draf_temuan}`" :sekarang="isian.ket" :maks="MAKS_KET" @pakai="pakai('ket')" /></template>
        </BagianAsisten>

        <BagianAsisten v-if="hasil.catatan" judul="Ringkasan untuk catatan" :teks="hasil.catatan">
          <p>{{ hasil.catatan }}</p>
          <template #aksi><UsulanIsian label="Catatan auditor" mode="tambah" :usulan="hasil.catatan" :sekarang="isian.ket" :maks="MAKS_KET" @pakai="pakai('ket')" /></template>
        </BagianAsisten>
      </template>
    </PanelAsisten>
  </span>
</template>
