<script setup lang="ts">
import { computed, nextTick, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Ikon from '../Ikon.vue';
import Dialog from '../../Dialog.vue';
import { useDialog } from '../../../dialog';
import { ukuranBerkas } from '../../../smkp/lib/format';
import type { BatasBerkas, BerkasBukti, StatusBukti } from '../../../smkp/types';

/* Penegasan memakai dialog EQOHSEE (useDialog), pengganti konfirmasi() Aurorasys. */
const { dialog, tanya, batal, lanjut } = useDialog();

/*
 * Bukti berkas satu butir kriteria: daftar berkas beserta status
 * verifikasinya, unggah (pilih atau seret-lepas), dan verifikasi oleh
 * auditor/administrator.
 *
 * Komponen ini dirender di dalam <form> penilaian (Smkp/Nilai): tidak boleh
 * ada <form> bersarang, seluruh tombol bertipe "button", dan Enter di
 * isiannya tidak boleh mengirim formulir nilai. Semua kunjungan memakai
 * preserveState agar nilai yang belum disimpan tetap ada di layar, dan
 * berjalan asinkron agar tidak memutus "Simpan" penilaian yang sedang
 * dikirim (kunjungan sinkron saling membatalkan).
 *
 * Dipakai juga di Penilaian PTPKKP: `alamat` (dasar URL berkas, untuk hapus),
 * `alamatUnggah` dan `tambahan` (isian unggah lain, mis. metode) mengganti
 * alamat SMKP; `tanpaStatus` menyembunyikan status verifikasi.
 */
const props = defineProps<{
  auditId?: number;
  kode: string;
  berkas: BerkasBukti[];
  bisaUnggah: boolean;
  bisaVerifikasi: boolean;
  batas: BatasBerkas;
  alamat?: string;
  alamatUnggah?: string;
  tambahan?: Record<string, string>;
  tanpaStatus?: boolean;
}>();
const dasar = computed(() => props.alamat ?? `/smkp/${props.auditId}/bukti`);

// Batas unggah server (post_max_size) 12 MB per pengiriman.
const MAKS_TOTAL = 12 * 1024 * 1024;

const STATUS: Record<StatusBukti, { label: string; ikon: string; kelas: string }> = {
  menunggu: { label: 'Menunggu verifikasi', ikon: 'jam', kelas: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' },
  diterima: { label: 'Diterima', ikon: 'centang-bulat', kelas: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' },
  ditolak: { label: 'Perlu perbaikan', ikon: 'peringatan', kelas: 'bg-rose-50 text-rose-700 ring-1 ring-rose-200' },
};

const hitung = computed(() => {
  const h: Record<StatusBukti, number> = { menunggu: 0, diterima: 0, ditolak: 0 };
  for (const b of props.berkas) h[b.status]++;
  return h;
});

const ekstensi = (nama: string) => (nama.includes('.') ? nama.split('.').pop()!.toLowerCase() : '');

/** Tegel jenis berkas untuk dokumen yang bukan gambar. */
function jenis(nama: string) {
  const e = ekstensi(nama);
  if (e === 'pdf') return { label: 'PDF', kelas: 'bg-rose-50 text-rose-600 ring-rose-100' };
  if (e === 'doc' || e === 'docx') return { label: e.toUpperCase(), kelas: 'bg-brand-50 text-brand-700 ring-brand-100' };
  if (e === 'xls' || e === 'xlsx') return { label: e.toUpperCase(), kelas: 'bg-emerald-50 text-emerald-700 ring-emerald-100' };
  if (e === 'ppt' || e === 'pptx') return { label: e.toUpperCase(), kelas: 'bg-amber-50 text-amber-700 ring-amber-100' };
  return { label: (e || 'berkas').slice(0, 4).toUpperCase(), kelas: 'bg-slate-100 text-slate-600 ring-slate-200' };
}

/* ---------- Unggah ---------- */
const buka = ref(false);
const pilihan = ref<File[]>([]);
const keterangan = ref('');
const mengunggah = ref(false);
const progres = ref<number | null>(null);
const galatUnggah = ref<string[]>([]);
const masukan = ref<HTMLInputElement | null>(null);

const terima = computed(() => props.batas.ekstensi.map((e) => `.${e}`).join(','));
const batasUkuran = computed(() => ukuranBerkas(props.batas.kb * 1024));

/** Ringkasan jenis yang diterima, mis. "PDF, foto (JPG/PNG/WEBP), Word, Excel, PowerPoint". */
const labelJenis = computed(() => {
  const ext = props.batas.ekstensi.map((e) => e.toLowerCase());
  const foto = ext.filter((e) => ['jpg', 'jpeg', 'png', 'webp'].includes(e) && e !== 'jpeg').map((e) => e.toUpperCase());
  const label: string[] = [];
  if (ext.includes('pdf')) label.push('PDF');
  if (foto.length) label.push(`foto (${foto.join('/')})`);
  if (ext.some((e) => e === 'doc' || e === 'docx')) label.push('Word');
  if (ext.some((e) => e === 'xls' || e === 'xlsx')) label.push('Excel');
  if (ext.some((e) => e === 'ppt' || e === 'pptx')) label.push('PowerPoint');
  const lain = ext.filter((e) => !['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'].includes(e));
  return [...label, ...lain.map((e) => e.toUpperCase())].join(', ');
});

function masalahBerkas(f: File): string | null {
  if (!props.batas.ekstensi.includes(ekstensi(f.name))) return 'jenis berkas tidak didukung';
  if (f.size === 0) return 'berkas kosong';
  if (f.size > props.batas.kb * 1024) return `lebih dari ${batasUkuran.value}`;
  return null;
}

const totalPilihan = computed(() => pilihan.value.reduce((n, f) => n + f.size, 0));
const masalah = computed(() => {
  const m: string[] = [];
  const n = pilihan.value.length;
  if (n > props.batas.jumlah) m.push(`Paling banyak ${props.batas.jumlah} berkas sekali unggah — buang ${n - props.batas.jumlah} berkas atau unggah bertahap.`);
  for (const f of pilihan.value) {
    const x = masalahBerkas(f);
    if (x) m.push(`${f.name}: ${x}.`);
  }
  if (totalPilihan.value > MAKS_TOTAL) m.push('Total berkas lebih dari 12 MB — unggah bertahap dalam beberapa kali kirim.');
  return m;
});

function tambah(daftar: File[]) {
  if (!props.bisaUnggah || !daftar.length) return;
  const ada = new Set(pilihan.value.map((f) => `${f.name}|${f.size}|${f.lastModified}`));
  pilihan.value = [...pilihan.value, ...daftar.filter((f) => !ada.has(`${f.name}|${f.size}|${f.lastModified}`))];
  galatUnggah.value = [];
  buka.value = true;
}

function pilihBerkas(e: Event) {
  const input = e.target as HTMLInputElement;
  tambah(Array.from(input.files ?? []));
  input.value = '';
}

const buang = (i: number) => pilihan.value.splice(i, 1);

function batalUnggah() {
  pilihan.value = [];
  keterangan.value = '';
  galatUnggah.value = [];
  buka.value = false;
}

function bukaUnggah() {
  buka.value = !buka.value;
  if (buka.value && !pilihan.value.length) nextTick(() => masukan.value?.click());
}

/* Seret-lepas di seluruh bagian bukti butir. Lepasan selalu ditahan di
   sini agar peramban tidak membuka berkasnya dan membuang isian nilai. */
const seret = ref(false);
let lapisSeret = 0;
const membawaBerkas = (e: DragEvent) => Array.from(e.dataTransfer?.types ?? []).includes('Files');

function masukSeret(e: DragEvent) {
  if (!props.bisaUnggah || !membawaBerkas(e)) return;
  lapisSeret++;
  seret.value = true;
}
function keluarSeret() {
  if (lapisSeret > 0 && --lapisSeret === 0) seret.value = false;
}
function lepas(e: DragEvent) {
  lapisSeret = 0;
  seret.value = false;
  tambah(Array.from(e.dataTransfer?.files ?? []));
}

const KUNCI_GALAT = /^(berkas(\.\d+)?|kode|keterangan|metode)$/;
const OPSI = { preserveScroll: true, preserveState: true, async: true, showProgress: true } as const;

function unggah() {
  if (!pilihan.value.length || masalah.value.length || mengunggah.value) return;
  galatUnggah.value = [];
  router.post(props.alamatUnggah ?? dasar.value, { ...props.tambahan, kode: props.kode, keterangan: keterangan.value.trim(), berkas: pilihan.value }, {
    ...OPSI,
    forceFormData: true,
    onStart: () => { mengunggah.value = true; progres.value = 0; },
    onProgress: (p) => { progres.value = p?.percentage ?? null; },
    onSuccess: () => { pilihan.value = []; keterangan.value = ''; buka.value = false; },
    onError: (e) => {
      const milik = Object.entries(e).filter(([k]) => KUNCI_GALAT.test(k));
      galatUnggah.value = (milik.length ? milik : Object.entries(e)).map(([, v]) => String(v));
    },
    onFinish: () => { mengunggah.value = false; progres.value = null; },
  });
}

/* ---------- Verifikasi & hapus ---------- */
const sibuk = ref<number | null>(null);
const tolak = ref<number | null>(null);
const catatanTolak = ref('');
const galatBerkas = reactive<Record<number, string>>({});

function bukaTolak(b: BerkasBukti) {
  tolak.value = b.id;
  catatanTolak.value = b.status === 'ditolak' ? (b.catatan ?? '') : '';
  delete galatBerkas[b.id];
  nextTick(() => document.getElementById(`tolak-${b.id}`)?.focus());
}
function tutupTolak() {
  tolak.value = null;
  catatanTolak.value = '';
}

function verifikasi(b: BerkasBukti, status: StatusBukti, catatan = '') {
  if (sibuk.value) return;
  if (status === 'ditolak' && !catatan.trim()) {
    galatBerkas[b.id] = 'Tuliskan apa yang perlu diperbaiki auditi.';
    return;
  }
  delete galatBerkas[b.id];
  sibuk.value = b.id;
  router.post(`${dasar.value}/${b.id}/verifikasi`, { status, catatan: catatan.trim() }, {
    ...OPSI,
    onSuccess: () => { if (tolak.value === b.id) tutupTolak(); },
    onError: (e) => { galatBerkas[b.id] = String(e.catatan ?? e.status ?? Object.values(e)[0] ?? 'Verifikasi gagal.'); },
    onFinish: () => { sibuk.value = null; },
  });
}

async function hapus(b: BerkasBukti) {
  if (sibuk.value) return;
  const ya = await tanya({
    judul: 'Hapus berkas bukti?',
    pesan: `"${b.nama}" (${ukuranBerkas(b.ukuran)}) dihapus permanen dari butir ${props.kode}.`,
    labelAksi: 'Hapus',
    nada: 'bahaya',
  });
  if (!ya) return;
  sibuk.value = b.id;
  router.delete(`${dasar.value}/${b.id}`, {
    ...OPSI,
    onError: (e) => { galatBerkas[b.id] = String(Object.values(e)[0] ?? 'Berkas gagal dihapus.'); },
    onFinish: () => { sibuk.value = null; },
  });
}
</script>

<template>
  <section class="relative rounded-xl border bg-white transition-colors"
           :class="seret ? 'border-brand-400 ring-4 ring-brand-500/10' : 'border-slate-200'"
           :aria-label="`Bukti berkas butir ${kode}`"
           @dragenter.prevent="masukSeret" @dragover.prevent @dragleave="keluarSeret" @drop.prevent="lepas">
    <!-- Kepala -->
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3.5 py-2.5" :class="berkas.length || buka ? 'border-b border-slate-100' : ''">
      <p class="flex items-center gap-2 text-[11.5px] font-semibold uppercase tracking-[0.08em] text-slate-500">
        <Ikon nama="folder" class="w-4 h-4 text-slate-400" /> Bukti berkas
        <span v-if="berkas.length" class="tabular-nums normal-case tracking-normal text-slate-400 font-medium">· {{ berkas.length }}</span>
      </p>
      <div v-if="berkas.length && !tanpaStatus" class="flex flex-wrap items-center gap-1.5">
        <span v-if="hitung.menunggu" class="lencana" :class="STATUS.menunggu.kelas"><span class="tabular-nums">{{ hitung.menunggu }}</span> menunggu</span>
        <span v-if="hitung.ditolak" class="lencana" :class="STATUS.ditolak.kelas"><span class="tabular-nums">{{ hitung.ditolak }}</span> perlu perbaikan</span>
        <span v-if="hitung.diterima" class="lencana" :class="STATUS.diterima.kelas"><span class="tabular-nums">{{ hitung.diterima }}</span> diterima</span>
      </div>
      <p v-if="!berkas.length && !buka" class="text-[12px] text-slate-400">Belum ada berkas.</p>
      <div class="ml-auto flex flex-wrap items-center gap-1.5">
        <slot name="aksi" />
        <button v-if="bisaUnggah" type="button" class="btn-garis btn-kecil" :aria-expanded="buka" @click="bukaUnggah">
          <Ikon :nama="buka ? 'tutup' : 'unggah'" class="w-3.5 h-3.5" /> {{ buka ? 'Tutup' : 'Unggah bukti' }}
        </button>
      </div>
    </div>

    <!-- Daftar berkas -->
    <ul v-if="berkas.length" class="divide-y divide-slate-100">
      <!-- Unduh/hapus melayang di pojok agar isi berkas memakai lebar penuh di ponsel -->
      <li v-for="b in berkas" :key="b.id" class="relative flex items-start gap-3 px-3.5 py-3" :class="sibuk === b.id ? 'opacity-60' : ''">
        <a v-if="b.gambar" :href="`${b.url}?lihat=1`" target="_blank" rel="noopener" class="shrink-0" :title="`Lihat ${b.nama}`">
          <img :src="`${b.url}?lihat=1`" alt="" loading="lazy" class="w-11 h-11 rounded-lg object-cover bg-slate-100 ring-1 ring-slate-200">
        </a>
        <span v-else class="shrink-0 grid place-items-center w-11 h-11 rounded-lg ring-1 text-[10px] font-bold tracking-wide" :class="jenis(b.nama).kelas">{{ jenis(b.nama).label }}</span>

        <div class="flex-1 min-w-0">
          <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1" :class="b.bisaHapus ? 'pr-16' : 'pr-8'">
            <a :href="b.url" class="min-w-0 max-w-full truncate text-[13px] font-semibold text-navy-900 hover:text-brand-700 hover:underline underline-offset-2" :title="`Unduh ${b.nama}`">{{ b.nama }}</a>
            <span v-if="!tanpaStatus" class="lencana" :class="STATUS[b.status].kelas"><Ikon :nama="STATUS[b.status].ikon" class="w-3 h-3" /> {{ STATUS[b.status].label }}</span>
          </div>
          <p class="mt-0.5 text-[11.5px] text-slate-400" :class="b.bisaHapus ? 'pr-16' : 'pr-8'">
            <span class="tabular-nums">{{ ukuranBerkas(b.ukuran) }}</span>
            <template v-if="b.oleh"> · {{ b.oleh }}</template>
            <template v-if="b.waktu"> · <span class="tabular-nums whitespace-nowrap">{{ b.waktu }}</span></template>
          </p>
          <p v-if="b.keterangan" class="mt-1.5 text-[12.5px] leading-relaxed text-slate-600 whitespace-pre-line">{{ b.keterangan }}</p>

          <div v-if="!tanpaStatus && (b.status !== 'menunggu' || b.catatan)" class="mt-2 rounded-lg border px-3 py-2 text-[12px] leading-relaxed"
               :class="b.status === 'ditolak' ? 'border-rose-200 bg-rose-50/70 text-rose-800' : b.status === 'diterima' ? 'border-emerald-200 bg-emerald-50/60 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-600'">
            <p v-if="b.status !== 'menunggu'" class="font-semibold">
              {{ b.status === 'ditolak' ? 'Perlu perbaikan' : 'Diterima' }}<template v-if="b.verifikator"> oleh {{ b.verifikator }}</template><template v-if="b.diverifikasi"> · <span class="tabular-nums font-normal">{{ b.diverifikasi }}</span></template>
            </p>
            <p v-if="b.catatan" class="whitespace-pre-line" :class="b.status !== 'menunggu' ? 'mt-0.5' : ''">{{ b.catatan }}</p>
          </div>

          <!-- Verifikasi auditor / administrator -->
          <div v-if="bisaVerifikasi && tolak !== b.id" class="mt-2.5 flex flex-wrap items-center gap-1.5">
            <button v-if="b.status !== 'diterima'" type="button" class="btn btn-kecil text-white bg-emerald-600 hover:bg-emerald-700" :disabled="!!sibuk" @click="verifikasi(b, 'diterima')">
              <Ikon nama="centang" class="w-3.5 h-3.5" :tebal="2.5" /> Terima
            </button>
            <button type="button" class="btn-garis-bahaya btn-kecil" :disabled="!!sibuk" @click="bukaTolak(b)">
              <Ikon :nama="b.status === 'ditolak' ? 'pensil' : 'peringatan'" class="w-3.5 h-3.5" /> {{ b.status === 'ditolak' ? 'Ubah catatan' : 'Perlu perbaikan' }}
            </button>
            <button v-if="b.status !== 'menunggu'" type="button" class="btn-hantu btn-kecil" :disabled="!!sibuk" title="Kembalikan status berkas ke menunggu verifikasi" @click="verifikasi(b, 'menunggu')">
              <Ikon nama="riwayat" class="w-3.5 h-3.5" /> Kembalikan ke menunggu
            </button>
          </div>
          <div v-if="bisaVerifikasi && tolak === b.id" class="mt-2.5 rounded-lg border border-rose-200 bg-rose-50/50 p-2.5">
            <label :for="`tolak-${b.id}`" class="block text-[11.5px] font-semibold text-rose-800">Apa yang perlu diperbaiki auditi?</label>
            <div class="mt-1.5 flex flex-col sm:flex-row gap-2">
              <input :id="`tolak-${b.id}`" v-model="catatanTolak" class="input-kecil flex-1" maxlength="1000"
                     placeholder="mis. Halaman pengesahan belum ditandatangani KTT"
                     @keydown.enter.prevent="verifikasi(b, 'ditolak', catatanTolak)" @keydown.esc.prevent="tutupTolak">
              <div class="flex gap-1.5 shrink-0">
                <button type="button" class="btn-bahaya btn-kecil" :disabled="!!sibuk || !catatanTolak.trim()" @click="verifikasi(b, 'ditolak', catatanTolak)">
                  {{ sibuk === b.id ? 'Mengirim…' : 'Kirim' }}
                </button>
                <button type="button" class="btn-hantu btn-kecil" @click="tutupTolak">Batal</button>
              </div>
            </div>
          </div>
          <p v-if="galatBerkas[b.id]" class="galat">{{ galatBerkas[b.id] }}</p>
        </div>

        <div class="absolute right-2.5 top-2.5 flex items-center gap-0.5">
          <a :href="b.url" class="grid place-items-center w-8 h-8 rounded-lg text-slate-400 hover:text-navy-900 hover:bg-slate-100" :aria-label="`Unduh ${b.nama}`" title="Unduh">
            <Ikon nama="unduh" class="w-4 h-4" />
          </a>
          <button v-if="b.bisaHapus" type="button" class="grid place-items-center w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 disabled:opacity-40"
                  :disabled="!!sibuk" :aria-label="`Hapus ${b.nama}`" title="Hapus" @click="hapus(b)">
            <Ikon nama="hapus" class="w-4 h-4" />
          </button>
        </div>
      </li>
    </ul>

    <!-- Unggah -->
    <div v-if="bisaUnggah && buka" class="p-3.5 space-y-3" :class="berkas.length ? 'border-t border-slate-100 bg-slate-50/50' : ''">
      <label class="flex flex-col sm:flex-row items-center justify-center gap-x-3 gap-y-1.5 rounded-xl border-2 border-dashed px-4 py-4 text-center sm:text-left cursor-pointer transition-colors"
             :class="seret ? 'border-brand-400 bg-brand-50/60' : 'border-slate-300 bg-white hover:border-brand-400 hover:bg-brand-50/40'">
        <span class="grid place-items-center w-9 h-9 rounded-lg bg-brand-50 text-brand-600 shrink-0"><Ikon nama="unggah" class="w-[18px] h-[18px]" /></span>
        <span class="min-w-0">
          <span class="block text-[13px] text-slate-600"><b class="text-navy-900">Pilih berkas</b> atau seret ke sini</span>
          <span class="block text-[11.5px] text-slate-400">{{ labelJenis }} · maks. {{ batas.jumlah }} berkas, {{ batasUkuran }} per berkas, total 12 MB</span>
        </span>
        <input ref="masukan" type="file" multiple class="sr-only" :accept="terima" @change="pilihBerkas">
      </label>

      <ul v-if="pilihan.length" class="space-y-1.5">
        <li v-for="(f, i) in pilihan" :key="`${f.name}-${f.size}-${f.lastModified}`"
            class="flex items-center gap-2.5 rounded-lg border px-3 py-2 text-[12.5px]"
            :class="masalahBerkas(f) ? 'border-rose-200 bg-rose-50/60' : 'border-slate-200 bg-white'">
          <Ikon :nama="masalahBerkas(f) ? 'peringatan' : 'file-text'" class="w-4 h-4 shrink-0" :class="masalahBerkas(f) ? 'text-rose-500' : 'text-slate-400'" />
          <span class="flex-1 min-w-0 truncate text-slate-700">{{ f.name }}</span>
          <span v-if="masalahBerkas(f)" class="hidden sm:inline text-[11.5px] text-rose-600 whitespace-nowrap">{{ masalahBerkas(f) }}</span>
          <span class="tabular-nums text-slate-400 whitespace-nowrap">{{ ukuranBerkas(f.size) }}</span>
          <button type="button" class="p-1 rounded text-slate-400 hover:text-rose-600" :disabled="mengunggah" :aria-label="`Buang ${f.name}`" @click="buang(i)">
            <Ikon nama="tutup" class="w-3.5 h-3.5" />
          </button>
        </li>
      </ul>

      <div v-if="pilihan.length">
        <label :for="`ket-bukti-${kode}`" class="block text-[11.5px] font-semibold text-slate-500 mb-1">Keterangan <span class="font-normal text-slate-400">(opsional)</span></label>
        <input :id="`ket-bukti-${kode}`" v-model="keterangan" class="input-kecil" maxlength="500"
               placeholder="mis. Prosedur izin kerja rev. 3 beserta 3 sampel izin kerja Jan–Mar" @keydown.enter.prevent>
      </div>

      <ul v-if="masalah.length || galatUnggah.length" class="space-y-0.5">
        <li v-for="(g, i) in [...masalah, ...galatUnggah]" :key="i" class="galat mt-0">{{ g }}</li>
      </ul>

      <div v-if="mengunggah && progres !== null" class="bilah h-1.5" role="progressbar" :aria-valuenow="progres" aria-valuemin="0" aria-valuemax="100">
        <i class="bg-brand-500" :style="{ width: `${progres}%` }" />
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2">
        <span v-if="pilihan.length" class="mr-auto text-[11.5px] text-slate-400 tabular-nums">{{ pilihan.length }} berkas · {{ ukuranBerkas(totalPilihan) }}</span>
        <button type="button" class="btn-hantu btn-kecil" :disabled="mengunggah" @click="batalUnggah">Batal</button>
        <button type="button" class="btn-utama btn-kecil" :disabled="mengunggah || !pilihan.length || masalah.length > 0" @click="unggah">
          <Ikon nama="unggah" class="w-3.5 h-3.5" />
          {{ mengunggah ? (progres ? `Mengunggah ${progres}%` : 'Mengunggah…') : pilihan.length ? `Unggah ${pilihan.length} berkas` : 'Unggah' }}
        </button>
      </div>
    </div>

    <!-- Lapisan seret -->
    <div v-if="seret" class="pointer-events-none absolute inset-0 grid place-items-center rounded-xl bg-brand-50/85">
      <p class="flex items-center gap-2 text-[13px] font-semibold text-brand-700"><Ikon nama="unggah" class="w-4 h-4" /> Lepaskan untuk menambahkan bukti butir {{ kode }}</p>
    </div>
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </section>
</template>
