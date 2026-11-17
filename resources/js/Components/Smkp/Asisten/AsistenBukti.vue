<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Ikon from '../Ikon.vue';
import PanelAsisten from './PanelAsisten.vue';
import BagianAsisten from './BagianAsisten.vue';
import UsulanIsian from './UsulanIsian.vue';
import { gunakanAsisten } from './api';
import type { AsistenBersama } from '../../../smkp/types';

/*
 * Analisis AI atas berkas bukti yang diunggah untuk satu butir (Smkp/Nilai).
 * Server mengirim berkas butir yang tidak ditolak verifikator ke model,
 * lalu mengembalikan kesesuaian per berkas, saran nilai, kekurangan, dan
 * rekomendasi. Dipasang di dalam formulir penilaian: semua tombol
 * type="button" dan penerapan hanya mengubah isian di layar.
 */
type Kesesuaian = 'sesuai' | 'sebagian' | 'tidak_sesuai' | 'tidak_terbaca';

interface HasilAnalisisBukti {
  tugas: 'bukti';
  kode: string;
  nilai: { saran: number | null; tidakBerlaku: boolean; maks: number };
  keyakinan: 'rendah' | 'sedang' | 'tinggi';
  ringkasan: string;
  berkas: {
    id: number; nama: string; status: string; relevan: boolean;
    kesesuaian: Kesesuaian; label: string; ringkasan: string; catatan: string;
  }[];
  terpenuhi: string[];
  kekurangan: string[];
  rekomendasi: string[];
  dokumenTambahan: string[];
  catatan: string;
  dilewati: string[];
  tidakTerbaca: string[];
  model: string;
  isian: { v: string; ket: string };
}

const props = defineProps<{
  auditId: number;
  butir: { kode: string; nama: string; maks: number };
  isian: { v: string; ket: string };
  jumlahBerkas: number;
}>();
const emit = defineEmits<{ terapkan: [kolom: 'v' | 'ket', nilai: string] }>();

const MAKS_KET = 2000;   // batas kolom catatan pada simpanNilai()

const KESESUAIAN: Record<Kesesuaian, string> = {
  sesuai: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
  sebagian: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
  tidak_sesuai: 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
  tidak_terbaca: 'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
};
const STATUS: Record<string, string> = { menunggu: 'Menunggu verifikasi', diterima: 'Diterima', ditolak: 'Perlu perbaikan' };

const page = usePage<any>();
const asisten = computed<AsistenBersama>(() => page.props.asisten ?? { aktif: false, model: '' });
const buka = ref(false);
const { hasil, memuat, galat, minta } = gunakanAsisten<HasilAnalisisBukti>(
  () => `/asisten/smkp/${props.auditId}/bukti`,
  () => ({ kode: props.butir.kode, v: props.isian.v, ket: props.isian.ket }),
);

const tanpaBerkas = computed(() => props.jumlahBerkas === 0);
const judulTombol = computed(() => tanpaBerkas.value
  ? 'Belum ada berkas bukti untuk dianalisis — unggah bukti terlebih dahulu'
  : asisten.value.aktif ? 'Analisis berkas bukti butir ini dengan Asisten AI' : 'Asisten AI belum diaktifkan administrator');

function bukaPanel() {
  if (tanpaBerkas.value) return;
  buka.value = true;
  if (asisten.value.aktif && !hasil.value && !memuat.value) minta();
}

const labelNilai = (v: string) => (v === '' ? 'belum dinilai' : v === 'N/A' ? 'N/A' : `${v} dari ${props.butir.maks}`);
const daftar = (judul: string, isi: string[]) => [judul, ...isi.map((x) => `- ${x}`)].join('\n');

const teksTerpenuhi = computed(() => hasil.value ? daftar('Terpenuhi:', hasil.value.terpenuhi) : '');
const teksKurang = computed(() => hasil.value ? daftar('Kekurangan bukti:', hasil.value.kekurangan) : '');
const teksRekom = computed(() => hasil.value ? daftar('Rekomendasi:', hasil.value.rekomendasi) : '');
const teksMinta = computed(() => hasil.value ? daftar('Dokumen yang perlu diminta:', hasil.value.dokumenTambahan) : '');
const teksBerkas = computed(() => hasil.value
  ? hasil.value.berkas.map((b) => `${b.nama} — ${b.label}${b.relevan ? '' : ' (tidak relevan)'}: ${[b.ringkasan, b.catatan].filter(Boolean).join(' ')}`).join('\n')
  : '');
const pakai = (kolom: 'v' | 'ket') => (nilai: string) => emit('terapkan', kolom, nilai);
</script>

<template>
  <span class="inline-flex" :title="judulTombol">
    <button type="button" class="btn-garis btn-kecil disabled:cursor-not-allowed disabled:opacity-50"
            :class="{ 'opacity-60': !asisten.aktif && !tanpaBerkas }" :disabled="tanpaBerkas" :aria-label="judulTombol" @click="bukaPanel">
      <Ikon nama="kilau" class="h-3.5 w-3.5" /> Analisis AI
    </button>
    <PanelAsisten v-model:buka="buka" judul="Analisis berkas bukti" :sub="`${butir.kode} · ${butir.nama}`" lama="20–90 detik"
                  :memuat="memuat" :galat="galat" :ada-hasil="!!hasil" @ulang="minta">
      <template v-if="hasil">
        <BagianAsisten judul="Ringkasan" :teks="hasil.ringkasan">
          <template #lencana><span class="lencana bg-slate-100 text-slate-600">Keyakinan {{ hasil.keyakinan }}</span></template>
          <p>{{ hasil.ringkasan || 'Asisten tidak memberikan ringkasan.' }}</p>
        </BagianAsisten>

        <BagianAsisten judul="Saran nilai">
          <p class="tabular-nums text-[22px] font-semibold leading-none text-slate-900">
            {{ hasil.nilai.tidakBerlaku ? 'N/A' : hasil.nilai.saran }}<span v-if="!hasil.nilai.tidakBerlaku" class="text-[14px] text-slate-400">/{{ hasil.nilai.maks }}</span>
          </p>
          <p class="bantu">Berdasarkan isi berkas terlampir terhadap kriteria Kepdirjen 185.K/37.04/DJB/2019.</p>
          <template #aksi>
            <UsulanIsian label="Nilai butir" mode="nilai" :usulan="hasil.isian.v" :sekarang="isian.v"
                         :tampil="labelNilai(hasil.isian.v)" :tampil-sekarang="labelNilai(isian.v)" @pakai="pakai('v')" />
          </template>
        </BagianAsisten>

        <BagianAsisten v-if="hasil.berkas.length" judul="Telaah per berkas" :teks="teksBerkas">
          <div class="gulir-x -mx-1">
            <table class="w-full min-w-[360px] text-left text-[12px]">
              <thead>
                <tr class="text-[11px] uppercase tracking-wide text-slate-400">
                  <th class="px-1 pb-2 font-semibold">Berkas</th>
                  <th class="px-1 pb-2 font-semibold">Relevan</th>
                  <th class="px-1 pb-2 font-semibold">Kesesuaian</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 align-top">
                <tr v-for="b in hasil.berkas" :key="b.id">
                  <td class="px-1 py-2">
                    <p class="break-all font-semibold text-slate-900">{{ b.nama }}</p>
                    <p v-if="b.ringkasan" class="mt-0.5 text-slate-600">{{ b.ringkasan }}</p>
                    <p v-if="b.catatan" class="mt-0.5 text-rose-700">{{ b.catatan }}</p>
                    <p v-if="b.status !== 'diterima'" class="bantu mt-0.5">Status: {{ STATUS[b.status] ?? b.status }}</p>
                  </td>
                  <td class="whitespace-nowrap px-1 py-2" :class="b.relevan ? 'text-slate-700' : 'text-slate-400'">{{ b.relevan ? 'Ya' : 'Tidak' }}</td>
                  <td class="px-1 py-2"><span class="lencana" :class="KESESUAIAN[b.kesesuaian]">{{ b.label }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </BagianAsisten>

        <BagianAsisten v-if="hasil.terpenuhi.length" judul="Sudah terpenuhi" :teks="teksTerpenuhi">
          <ul class="space-y-1.5">
            <li v-for="(x, i) in hasil.terpenuhi" :key="i" class="flex items-start gap-2">
              <Ikon nama="centang" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600" /><span>{{ x }}</span>
            </li>
          </ul>
        </BagianAsisten>

        <BagianAsisten v-if="hasil.kekurangan.length" judul="Kekurangan bukti" :teks="teksKurang">
          <ul class="space-y-1.5">
            <li v-for="(x, i) in hasil.kekurangan" :key="i" class="flex items-start gap-2">
              <Ikon nama="peringatan" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-600" /><span>{{ x }}</span>
            </li>
          </ul>
          <template #aksi><UsulanIsian label="Catatan auditor" mode="tambah" :usulan="teksKurang" :sekarang="isian.ket" :maks="MAKS_KET" @pakai="pakai('ket')" /></template>
        </BagianAsisten>

        <BagianAsisten v-if="hasil.rekomendasi.length" judul="Rekomendasi" :teks="teksRekom">
          <ol class="list-decimal space-y-1.5 pl-5"><li v-for="(x, i) in hasil.rekomendasi" :key="i">{{ x }}</li></ol>
          <template #aksi><UsulanIsian label="Catatan auditor" mode="tambah" :usulan="teksRekom" :sekarang="isian.ket" :maks="MAKS_KET" @pakai="pakai('ket')" /></template>
        </BagianAsisten>

        <BagianAsisten v-if="hasil.dokumenTambahan.length" judul="Dokumen yang perlu diminta" :teks="teksMinta">
          <ul class="list-disc space-y-1.5 pl-5"><li v-for="(x, i) in hasil.dokumenTambahan" :key="i">{{ x }}</li></ul>
          <template #aksi><UsulanIsian label="Catatan auditor" mode="tambah" :usulan="teksMinta" :sekarang="isian.ket" :maks="MAKS_KET" @pakai="pakai('ket')" /></template>
        </BagianAsisten>

        <BagianAsisten v-if="hasil.isian.ket" judul="Ringkasan untuk catatan" :teks="hasil.isian.ket">
          <p class="whitespace-pre-line">{{ hasil.isian.ket }}</p>
          <template #aksi><UsulanIsian label="Catatan auditor" mode="tambah" :usulan="hasil.isian.ket" :sekarang="isian.ket" :maks="MAKS_KET" @pakai="pakai('ket')" /></template>
        </BagianAsisten>

        <div v-if="hasil.tidakTerbaca.length || hasil.dilewati.length" class="kartu space-y-2 p-4 text-[12.5px] leading-relaxed text-slate-700">
          <p class="flex items-center gap-2 font-semibold text-slate-900"><Ikon nama="peringatan" class="h-4 w-4 text-amber-600" /> Tidak ikut dianalisis</p>
          <p v-if="hasil.tidakTerbaca.length">
            Isinya tidak dapat dibaca AI (format lama DOC/XLS/PPT, berkata sandi, atau rusak): <b class="break-all font-semibold">{{ hasil.tidakTerbaca.join(', ') }}</b>.
            Minta auditi mengunggah ulang sebagai PDF atau DOCX/XLSX/PPTX.
          </p>
          <p v-if="hasil.dilewati.length">
            Dilewati karena melampaui batas ukuran sekali kirim: <b class="break-all font-semibold">{{ hasil.dilewati.join(', ') }}</b>. Periksa berkas ini secara manual.
          </p>
        </div>

        <p class="bantu text-center">Dianalisis oleh model {{ hasil.model }}.</p>
      </template>
    </PanelAsisten>
  </span>
</template>
