<script setup lang="ts">
import { computed, onMounted, reactive, ref, watchEffect } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import KepalaAudit from '../../Components/Smkp/KepalaAudit.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import Kosong from '../../Components/Smkp/Kosong.vue';
import AsistenTemuan from '../../Components/Smkp/Asisten/AsistenTemuan.vue';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
import type { IsianTemuanAi, RingkasAudit } from '../../smkp/types';

/* Penegasan memakai dialog EQOHSEE (useDialog), pengganti konfirmasi() Aurorasys. */
const { dialog, tanya, batal, lanjut } = useDialog();

interface Kiriman {
  id: number; oleh: string; waktu: string | null; akar_masalah: string; tindakan: string; bukti_perbaikan: string; penanggung_jawab: string;
  lampiran: Array<{ nama: string; ukuran: number; gambar: boolean; url: string }>; bisaHapus: boolean;
}
interface Temuan {
  id: number; nomor: string; nomorTampil: string; kode: string; jenis: string; uraian: string; lokasi: string; divisi: string; departemen: string; bukti: string; deskripsi: string; deskripsiOtomatis: string; acuan: string; namaKriteria: string;
  akar_masalah: string; tindakan: string; bukti_perbaikan: string; penanggung_jawab: string;
  target_selesai: string; tanggal_selesai: string; status: string; verifikasi: string; terlambat: boolean;
  kiriman: Kiriman[];
}
type Isian = Omit<Temuan, 'id' | 'kode' | 'terlambat' | 'nomorTampil' | 'kiriman' | 'deskripsiOtomatis' | 'acuan' | 'namaKriteria'> & { proses: boolean };

const props = defineProps<{
  audit: RingkasAudit;
  temuan: Temuan[];
  usulan: Array<{ kode: string; uraian: string; elemen: string; jenis: string; label: string; capaian: number; nilai: number; maks: number; ref: string | null }>;
  statusPilihan: string[];
  kategoriPilihan: Record<string, string>;
  bisaSunting: boolean;
  bisaTindakLanjut: boolean;
  buka: number | null;
  batasBerkas: { jumlah: number; kb: number; ekstensi: string[] };
}>();

const saring = ref<string>('');
const buka = ref<number | null>(props.buka);

const isian = reactive<Record<number, Isian>>({});
// Deskripsi yang masih otomatis mengikuti uraian, bukti, dan lokasi; berhenti begitu diubah manual.
const dsOtomatis = reactive<Record<number, boolean>>({});
function muat(hanyaBaru = false) {
  for (const t of props.temuan) {
    if (hanyaBaru && isian[t.id]) continue; // isian yang belum disimpan tidak tertimpa
    const { id, kode, terlambat, nomorTampil, kiriman, deskripsiOtomatis, acuan, namaKriteria, ...data } = t;
    void id; void kode; void terlambat; void nomorTampil; void kiriman; void acuan; void namaKriteria;
    isian[t.id] = { ...data, proses: false };
    dsOtomatis[t.id] = !t.deskripsi.trim() || t.deskripsi.trim() === deskripsiOtomatis;
  }
}
muat();

/*
 * Deskripsi otomatis — harus sama persis dengan Smkp::deskripsiTemuan():
 * "Ditemukan bukti {uraian}, berdasarkan {bukti} di {lokasi}, sehingga tidak
 * sesuai dengan {referensi}." Bagian yang kosong dilewati.
 */
function potongan(x: string): string {
  x = x.replace(/[ \t\r\n]+/g, ' ').replace(/^ +/, '').replace(/[ .;,:]+$/, '');
  return /^\p{Lu}\p{Ll}+(?![\p{L}\p{N}])/u.test(x) ? x.charAt(0).toLowerCase() + x.slice(1) : x;
}
function deskripsiDari(t: Temuan, f: Isian): string {
  const ur = (f.uraian ?? '').trim();
  const masalah = potongan(ur.toLowerCase() === t.namaKriteria.trim().toLowerCase() ? '' : ur);
  const bukti = potongan(f.bukti ?? '');
  const lokasi = potongan(f.lokasi ?? '');
  let teks = masalah ? `Ditemukan bukti ${masalah}` : 'Ditemukan ketidaksesuaian';
  if (bukti) teks += `, berdasarkan ${bukti}`;
  if (lokasi) teks += ` di ${lokasi}`;
  if (t.acuan) teks += `, sehingga tidak sesuai dengan ${t.acuan}`;
  return `${teks}.`;
}
watchEffect(() => {
  for (const t of props.temuan) {
    const f = isian[t.id];
    if (f && dsOtomatis[t.id]) f.deskripsi = deskripsiDari(t, f);
  }
});

// Tautan dari dasbor / daftar tindak lanjut membuka temuan yang dituju.
onMounted(() => {
  if (props.buka) document.getElementById(`temuan-${props.buka}`)?.scrollIntoView({ block: 'start' });
});

/* ---------- Tindak lanjut oleh auditi: isian + lampiran bukti ---------- */
const bisaKirim = (t: Temuan) => props.bisaTindakLanjut && t.status !== 'Closed';
const berkas = reactive<Record<number, File[]>>({});
const galatKirim = reactive<Record<number, string[]>>({});
// Batas unggah server (post_max_size) 12 MB per pengiriman.
const MAKS_TOTAL = 12 * 1024 * 1024;
const terima = computed(() => props.batasBerkas.ekstensi.map((e) => `.${e}`).join(','));

function pilihBerkas(t: Temuan, e: Event) {
  const input = e.target as HTMLInputElement;
  berkas[t.id] = [...(berkas[t.id] ?? []), ...Array.from(input.files ?? [])];
  input.value = '';
}
const buangBerkas = (t: Temuan, i: number) => berkas[t.id].splice(i, 1);

function masalahBerkas(id: number): string | null {
  const daftar = berkas[id] ?? [];
  if (daftar.length > props.batasBerkas.jumlah) return `Paling banyak ${props.batasBerkas.jumlah} berkas per pengiriman.`;
  const besar = daftar.find((f) => f.size > props.batasBerkas.kb * 1024);
  if (besar) return `${besar.name} lebih dari ${props.batasBerkas.kb / 1024} MB.`;
  if (daftar.reduce((n, f) => n + f.size, 0) > MAKS_TOTAL) return 'Total berkas lebih dari 12 MB — kirim bertahap dalam beberapa pengiriman.';
  return null;
}

function ukuran(n: number) {
  return n >= 1024 * 1024 ? `${(n / 1024 / 1024).toLocaleString('id-ID', { maximumFractionDigits: 1 })} MB` : `${Math.max(1, Math.round(n / 1024))} KB`;
}

function kirim(t: Temuan) {
  if (masalahBerkas(t.id)) return;
  const f = isian[t.id];
  f.proses = true;
  galatKirim[t.id] = [];
  router.post(`/smkp/${props.audit.id}/temuan/${t.id}/tindak-lanjut`, {
    akar_masalah: f.akar_masalah, tindakan: f.tindakan, bukti_perbaikan: f.bukti_perbaikan,
    penanggung_jawab: f.penanggung_jawab, lampiran: berkas[t.id] ?? [],
  }, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => { berkas[t.id] = []; muat(); },
    onError: (e) => { galatKirim[t.id] = Object.values(e).filter(Boolean) as string[]; },
    onFinish: () => { f.proses = false; },
  });
}

async function tarik(t: Temuan, k: Kiriman) {
  if (await tanya({ judul: 'Hapus kiriman tindak lanjut ini?', pesan: `${k.waktu ?? ''} · ${k.lampiran.length} lampiran ikut terhapus.`, labelAksi: 'Hapus', nada: 'bahaya' })) {
    router.delete(`/smkp/${props.audit.id}/temuan/${t.id}/tindak-lanjut/${k.id}`, { preserveScroll: true });
  }
}

const hitung = computed(() => ({
  semua: props.temuan.length,
  Open: props.temuan.filter((t) => t.status === 'Open').length,
  'In Progress': props.temuan.filter((t) => t.status === 'In Progress').length,
  Closed: props.temuan.filter((t) => t.status === 'Closed').length,
}));
const tampil = computed(() => props.temuan.filter((t) => !saring.value || t.status === saring.value));

// Tanggal penutupan tidak boleh melewati hari ini — menurut WIT, sama dengan server.
const hariIni = new Date(Date.now() + 9 * 3600 * 1000).toISOString().slice(0, 10);

function simpan(t: Temuan) {
  const f = isian[t.id];
  f.proses = true;
  const { proses, ...data } = f;
  void proses;
  router.put(`/smkp/${props.audit.id}/temuan/${t.id}`, data, {
    preserveScroll: true,
    onSuccess: () => { muat(); },
    onFinish: () => { f.proses = false; },
  });
}

/* Asisten AI: usulan diterapkan per kolom ke formulir di layar; tersimpan lewat Simpan. */
function terapkanAsisten(id: number, kolom: keyof IsianTemuanAi, nilai: string) {
  isian[id][kolom] = nilai;
}

async function hapus(t: Temuan) {
  if (await tanya({ judul: `Hapus temuan ${t.kode}?`, pesan: 'Tindakan perbaikan yang sudah dicatat ikut terhapus.', labelAksi: 'Hapus', nada: 'bahaya' })) {
    router.delete(`/smkp/${props.audit.id}/temuan/${t.id}`, { preserveScroll: true });
  }
}

const mengangkat = ref(false);
const menomori = ref(false);
async function nomorUlang() {
  if (menomori.value) return;
  if (await tanya({ judul: 'Susun ulang nomor temuan?', pesan: 'Seluruh temuan diberi nomor baru: Mayor lalu Minor, masing-masing urut sub-elemen dan sub-sub-elemen dengan format (kode perusahaan)-(kategori)-(nomor)-(tahun), mis. AIC-MYR-01-2025 …, AIC-MNR-01-2025 …. Nomor yang diketik manual ikut diganti.', labelAksi: 'Susun ulang' })) {
    router.post(`/smkp/${props.audit.id}/temuan/nomor-ulang`, {}, {
      preserveScroll: true,
      onStart: () => (menomori.value = true),
      onFinish: () => (menomori.value = false),
      onSuccess: () => { for (const t of props.temuan) if (isian[t.id]) isian[t.id].nomor = t.nomor; },
    });
  }
}
async function angkat() {
  if (mengangkat.value) return;
  if (await tanya({ judul: `Angkat ${props.usulan.length} ketidaksesuaian menjadi temuan?`, pesan: 'Tiap ketidaksesuaian dicatat sebagai temuan berstatus Open: sub-elemen bercapaian Mayor sebagai satu temuan Mayor, rincian yang belum penuh sebagai temuan Minor.', labelAksi: 'Angkat semua' })) {
    router.post(`/smkp/${props.audit.id}/temuan/angkat`, {}, {
      preserveScroll: true,
      onStart: () => (mengangkat.value = true),
      onFinish: () => (mengangkat.value = false),
      // Isian formulir dibuat untuk temuan yang baru diangkat saja.
      onSuccess: () => muat(true),
    });
  }
}

const JENIS: Record<string, string> = { kritikal: 'bg-rose-900 text-white', mayor: 'bg-rose-600 text-white', minor: 'bg-amber-500 text-white' };
const cetakSatu = (t: Temuan) => `/smkp/${props.audit.id}/formulir/ketidaksesuaian/cetak?temuan=${t.id}`;
const STATUS: Record<string, string> = { Open: 'bg-rose-50 text-rose-700', 'In Progress': 'bg-amber-50 text-amber-700', Closed: 'bg-hijau-50 text-hijau-700' };
</script>

<template>
  <div class="smkp-ui">
    <Head title="Temuan & Tindakan Perbaikan" />
    <KepalaAudit :audit="audit" aktif="temuan" :bisa-sunting="bisaSunting" :jumlah-temuan="temuan.length" />

    <section v-if="usulan.length" class="mb-5 rounded-kartu border border-amber-200 bg-gradient-to-r from-amber-50 to-white p-5">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex gap-3">
          <span class="grid place-items-center w-10 h-10 rounded-xl bg-amber-100 text-amber-700 shrink-0"><Ikon nama="peringatan" class="w-5 h-5" /></span>
          <div>
            <h2 class="text-[14px] font-bold text-amber-900">{{ usulan.length }} ketidaksesuaian belum menjadi tindakan perbaikan</h2>
            <p class="text-[12.5px] text-amber-800/80 mt-0.5">Diturunkan otomatis dari nilai sub-elemen pada formulir penilaian.</p>
          </div>
        </div>
        <button v-if="bisaSunting" type="button" class="btn bg-amber-500 hover:bg-amber-600 text-white" :disabled="mengangkat" @click="angkat"><Ikon nama="unggah" class="w-4 h-4" /> {{ mengangkat ? 'Mengangkat…' : 'Angkat semua' }}</button>
      </div>
      <ul class="mt-4 grid md:grid-cols-2 gap-2">
        <li v-for="u in usulan.slice(0, 6)" :key="u.kode" class="flex items-center gap-2.5 rounded-xl bg-white/80 border border-amber-100 px-3 py-2 text-[12.5px]">
          <span class="lencana" :class="JENIS[u.jenis]">{{ u.jenis }}</span>
          <b class="tabular-nums text-navy-900">{{ u.kode }}</b>
          <span class="truncate text-slate-600">{{ u.uraian }}</span>
          <span class="ml-auto text-slate-400 tabular-nums whitespace-nowrap">{{ u.nilai }}/{{ u.maks }}</span>
        </li>
      </ul>
      <p v-if="usulan.length > 6" class="mt-2 text-[12px] text-amber-800/70">…dan {{ usulan.length - 6 }} lainnya.</p>
    </section>

    <div class="flex flex-wrap items-center gap-2 mb-4">
      <button v-for="s in ['', 'Open', 'In Progress', 'Closed']" :key="s" type="button" @click="saring = s"
              class="rounded-xl px-4 py-2 text-[13px] font-semibold transition"
              :class="saring === s ? 'bg-navy-900 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:border-slate-300'">
        {{ s || 'Semua' }} <span class="opacity-60 tabular-nums">({{ hitung[(s || 'semua') as keyof typeof hitung] }})</span>
      </button>
      <button v-if="bisaSunting && temuan.length" type="button" class="ml-auto btn-garis btn-kecil" :disabled="menomori" @click="nomorUlang">
        <Ikon nama="muat" class="w-4 h-4" /> {{ menomori ? 'Menyusun…' : 'Susun ulang nomor' }}
      </button>
    </div>

    <div v-if="tampil.length" class="space-y-3">
      <article v-for="t in tampil" :id="`temuan-${t.id}`" :key="t.id" class="kartu overflow-hidden scroll-mt-24">
        <button type="button" class="w-full text-left flex flex-wrap items-center gap-3 px-5 py-4 hover:bg-slate-50/60 transition" @click="buka = buka === t.id ? null : t.id">
          <span class="min-w-[58px] shrink-0 whitespace-nowrap text-[12px] font-semibold text-slate-500 tabular-nums">{{ t.nomorTampil }}</span>
          <span class="lencana uppercase tracking-wide" :class="JENIS[t.jenis] ?? 'bg-slate-500 text-white'">{{ kategoriPilihan[t.jenis] ?? t.jenis }}</span>
          <b class="tabular-nums text-[13px] text-navy-900">{{ t.kode }}</b>
          <span class="flex-1 min-w-[200px] text-[13.5px] text-slate-700 truncate">{{ t.uraian }}</span>
          <span v-if="t.terlambat" class="lencana bg-rose-100 text-rose-700">Lewat target</span>
          <span v-if="t.kiriman.length" class="lencana bg-brand-50 text-brand-700" :title="`${t.kiriman.length} kiriman tindak lanjut`"><Ikon nama="unggah" class="w-3 h-3" /> {{ t.kiriman.length }}</span>
          <span class="lencana" :class="STATUS[t.status]">{{ t.status }}</span>
          <span class="text-[12px] text-slate-400 whitespace-nowrap">{{ t.penanggung_jawab || 'PJ belum ditetapkan' }}</span>
          <Ikon :nama="buka === t.id ? 'chevron-up' : 'chevron-down'" class="w-4 h-4 text-slate-400" />
        </button>

        <form v-if="buka === t.id" class="border-t border-slate-100" @submit.prevent="bisaSunting ? simpan(t) : kirim(t)">
          <div class="divide-y divide-slate-100">
            <!-- Uraian ketidaksesuaian (diisi auditor) -->
            <fieldset :disabled="!bisaSunting"><div class="p-5 grid lg:grid-cols-[180px_1fr] gap-x-6 gap-y-4">
              <div>
                <p class="eyebrow">Uraian ketidaksesuaian</p>
                <p class="mt-1 text-[12px] text-slate-500 leading-snug">Diisi auditor saat audit lapangan.</p>
              </div>
              <div class="grid sm:grid-cols-6 gap-4">
                <div class="sm:col-span-2"><label class="label" :for="`no-${t.id}`">Nomor ketidaksesuaian</label><input :id="`no-${t.id}`" v-model="isian[t.id].nomor" class="input tabular-nums" :placeholder="t.nomorTampil"></div>
                <div class="sm:col-span-4">
                  <span class="label">Kategori</span>
                  <div class="flex flex-wrap gap-2" role="radiogroup">
                    <label v-for="(l, k) in kategoriPilihan" :key="k" class="flex items-center gap-2 h-[38px] rounded-lg border px-3 text-[13px] font-semibold cursor-pointer transition-colors"
                           :class="isian[t.id].jenis === k ? 'border-navy-900 bg-navy-900 text-white' : 'border-slate-300 text-slate-600 hover:bg-slate-50'">
                      <input v-model="isian[t.id].jenis" type="radio" :value="k" class="sr-only">{{ l }}
                    </label>
                  </div>
                </div>
                <div class="sm:col-span-6"><label class="label" :for="`ur-${t.id}`">Uraian masalah <span class="font-normal text-slate-400">(problem)</span></label><textarea :id="`ur-${t.id}`" v-model="isian[t.id].uraian" rows="2" class="input" /></div>
                <div class="sm:col-span-3"><label class="label" :for="`lok-${t.id}`">Area / lokasi &amp; departemen <span class="font-normal text-slate-400">(location)</span></label><input :id="`lok-${t.id}`" v-model="isian[t.id].lokasi" class="input" placeholder="mis. Pit Utara · Dept. Produksi"></div>
                <div class="sm:col-span-3"><label class="label">Referensi <span class="font-normal text-slate-400">(elemen / sub-elemen)</span></label><p class="input bg-slate-50 text-slate-600 tabular-nums">{{ t.kode }}</p></div>
                <div class="sm:col-span-3">
                  <label class="label" :for="`div-${t.id}`">Divisi (kop cetak)</label>
                  <input :id="`div-${t.id}`" v-model="isian[t.id].divisi" class="input" placeholder="Kosongkan untuk memakai divisi perusahaan">
                </div>
                <div class="sm:col-span-3">
                  <label class="label" :for="`dep-${t.id}`">Departemen (kop cetak)</label>
                  <input :id="`dep-${t.id}`" v-model="isian[t.id].departemen" class="input" placeholder="Kosongkan untuk memakai departemen perusahaan">
                </div>
                <div class="sm:col-span-6"><label class="label" :for="`bk-${t.id}`">Bukti <span class="font-normal text-slate-400">(objective evidence)</span></label><textarea :id="`bk-${t.id}`" v-model="isian[t.id].bukti" rows="2" class="input" placeholder="Dokumen, rekaman, hasil wawancara, atau pengamatan yang menjadi dasar temuan" /></div>
                <div class="sm:col-span-4">
                  <div class="flex items-baseline justify-between gap-2">
                    <label class="label" :for="`ds-${t.id}`">Deskripsi <span class="font-normal text-slate-400">{{ dsOtomatis[t.id] ? '(otomatis dari uraian, bukti, lokasi & referensi)' : '(diubah manual)' }}</span></label>
                    <button v-if="!dsOtomatis[t.id] && bisaSunting" type="button" class="text-[12px] font-semibold text-brand-700 hover:underline" @click="dsOtomatis[t.id] = true">Isi otomatis</button>
                  </div>
                  <textarea :id="`ds-${t.id}`" v-model="isian[t.id].deskripsi" rows="4" class="input" placeholder="Kosong = deskripsi otomatis saat dicetak" @input="dsOtomatis[t.id] = false" />
                </div>
                <div class="sm:col-span-2"><label class="label" :for="`bt-${t.id}`">Batas waktu perbaikan</label><input :id="`bt-${t.id}`" v-model="isian[t.id].target_selesai" type="date" class="input"></div>
              </div>
            </div></fieldset>

            <!-- Tindak lanjut (diisi auditi) -->
            <fieldset :disabled="!bisaSunting && !bisaKirim(t)"><div class="p-5 grid lg:grid-cols-[180px_1fr] gap-x-6 gap-y-4">
              <div>
                <p class="eyebrow">Tindak lanjut audit</p>
                <p class="mt-1 text-[12px] text-slate-500 leading-snug">{{ !bisaSunting && bisaKirim(t) ? 'Isi perbaikan dan lampirkan buktinya, lalu kirim. Auditor memverifikasi sebelum temuan ditutup.' : 'Diisi auditi: akar masalah, perbaikan, dan buktinya.' }}</p>
              </div>
              <div class="grid sm:grid-cols-6 gap-4">
                <div class="sm:col-span-3"><label class="label" :for="`ak-${t.id}`">Investigasi akar permasalahan</label><textarea :id="`ak-${t.id}`" v-model="isian[t.id].akar_masalah" rows="3" class="input" /></div>
                <div class="sm:col-span-3"><label class="label" :for="`tp-${t.id}`">Tindakan perbaikan</label><textarea :id="`tp-${t.id}`" v-model="isian[t.id].tindakan" rows="3" class="input" :required="!bisaSunting" /></div>
                <div class="sm:col-span-6"><label class="label" :for="`bp-${t.id}`">Bukti perbaikan</label><textarea :id="`bp-${t.id}`" v-model="isian[t.id].bukti_perbaikan" rows="2" class="input" /></div>
                <div class="sm:col-span-3"><label class="label" :for="`pj-${t.id}`">Penanggung jawab</label><input :id="`pj-${t.id}`" v-model="isian[t.id].penanggung_jawab" class="input" placeholder="Jabatan / departemen"></div>
                <div class="sm:col-span-3"><label class="label" :for="`tg-${t.id}`">Tanggal perbaikan</label><input :id="`tg-${t.id}`" v-model="isian[t.id].tanggal_selesai" type="date" class="input" :max="hariIni" :disabled="!bisaSunting || isian[t.id].status !== 'Closed'"></div>

                <!-- Lampiran bukti (auditi) -->
                <div v-if="!bisaSunting && bisaKirim(t)" class="sm:col-span-6">
                  <span class="label">Lampiran bukti <span class="font-normal text-slate-400">(PDF, foto, dokumen Office · maks. {{ batasBerkas.jumlah }} berkas, {{ batasBerkas.kb / 1024 }} MB per berkas)</span></span>
                  <label class="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 rounded-xl border-2 border-dashed border-slate-300 px-4 py-5 text-center text-[13px] text-slate-500 cursor-pointer hover:border-brand-400 hover:bg-brand-50/40 transition-colors">
                    <Ikon nama="unggah" class="w-5 h-5 text-brand-600" />
                    <span><b class="text-navy-900">Pilih berkas</b> dokumen atau foto bukti perbaikan</span>
                    <input type="file" multiple class="sr-only" :accept="terima" @change="pilihBerkas(t, $event)">
                  </label>
                  <ul v-if="berkas[t.id]?.length" class="mt-2 space-y-1.5">
                    <li v-for="(f, i) in berkas[t.id]" :key="`${f.name}-${i}`" class="flex items-center gap-2.5 rounded-lg bg-slate-50 px-3 py-2 text-[12.5px]">
                      <Ikon nama="file-text" class="w-4 h-4 text-slate-400 shrink-0" />
                      <span class="flex-1 truncate text-slate-700">{{ f.name }}</span>
                      <span class="tabular-nums text-slate-400 whitespace-nowrap">{{ ukuran(f.size) }}</span>
                      <button type="button" class="p-1 rounded text-slate-400 hover:text-rose-600" :aria-label="`Buang ${f.name}`" @click="buangBerkas(t, i)"><Ikon nama="tutup" class="w-3.5 h-3.5" /></button>
                    </li>
                  </ul>
                  <p v-if="masalahBerkas(t.id)" class="galat">{{ masalahBerkas(t.id) }}</p>
                  <ul v-if="galatKirim[t.id]?.length" class="mt-2 space-y-0.5"><li v-for="g in galatKirim[t.id]" :key="g" class="galat">{{ g }}</li></ul>
                </div>
              </div>
            </div></fieldset>

            <!-- Ulasan tim auditor -->
            <fieldset :disabled="!bisaSunting"><div class="p-5 grid lg:grid-cols-[180px_1fr] gap-x-6 gap-y-4">
              <div>
                <p class="eyebrow">Ulasan tim auditor</p>
                <p class="mt-1 text-[12px] text-slate-500 leading-snug">Verifikasi keefektifan sebelum ditutup.</p>
              </div>
              <div class="grid sm:grid-cols-6 gap-4">
                <div class="sm:col-span-2"><span class="label">Nomor tindak lanjut <span class="font-normal text-slate-400">(= nomor ketidaksesuaian)</span></span><p class="input bg-slate-50 text-slate-600 tabular-nums">{{ isian[t.id].nomor.trim() || t.nomorTampil }}</p></div>
                <div class="sm:col-span-2"><label class="label" :for="`st-${t.id}`">Status</label><select :id="`st-${t.id}`" v-model="isian[t.id].status" class="input"><option v-for="s in statusPilihan" :key="s" :value="s">{{ s }}</option></select></div>
                <div class="sm:col-span-6"><label class="label" :for="`vf-${t.id}`">Verifikasi</label><textarea :id="`vf-${t.id}`" v-model="isian[t.id].verifikasi" rows="2" class="input" /></div>
              </div>
            </div></fieldset>

            <!-- Riwayat kiriman auditi -->
            <section v-if="t.kiriman.length" class="p-5 grid lg:grid-cols-[180px_1fr] gap-x-6 gap-y-4">
              <div>
                <p class="eyebrow">Riwayat tindak lanjut</p>
                <p class="mt-1 text-[12px] text-slate-500 leading-snug">Kiriman auditi beserta lampiran bukti, terbaru di atas.</p>
              </div>
              <ol class="space-y-3">
                <li v-for="k in t.kiriman" :key="k.id" class="rounded-xl border border-slate-200 p-4">
                  <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <b class="text-[13px] text-navy-900">{{ k.oleh }}</b>
                    <span class="text-[12px] text-slate-400 tabular-nums">{{ k.waktu }}</span>
                    <button v-if="k.bisaHapus" type="button" class="ml-auto p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50" aria-label="Hapus kiriman" @click="tarik(t, k)"><Ikon nama="hapus" class="w-4 h-4" /></button>
                  </div>
                  <p class="mt-2 text-[13px] text-slate-700 leading-relaxed whitespace-pre-line">{{ k.tindakan }}</p>
                  <dl v-if="k.akar_masalah || k.bukti_perbaikan || k.penanggung_jawab" class="mt-2.5 grid sm:grid-cols-3 gap-3 text-[12px]">
                    <div v-if="k.akar_masalah"><dt class="text-slate-400">Akar masalah</dt><dd class="text-slate-600 whitespace-pre-line">{{ k.akar_masalah }}</dd></div>
                    <div v-if="k.bukti_perbaikan"><dt class="text-slate-400">Bukti perbaikan</dt><dd class="text-slate-600 whitespace-pre-line">{{ k.bukti_perbaikan }}</dd></div>
                    <div v-if="k.penanggung_jawab"><dt class="text-slate-400">Penanggung jawab</dt><dd class="text-slate-600">{{ k.penanggung_jawab }}</dd></div>
                  </dl>
                  <ul v-if="k.lampiran.length" class="mt-3 flex flex-wrap gap-2">
                    <li v-for="l in k.lampiran" :key="l.url" class="min-w-0 max-w-full">
                      <a :href="l.url" class="flex items-center gap-2.5 rounded-lg border border-slate-200 bg-white py-1.5 pl-1.5 pr-3 text-[12.5px] hover:border-brand-300 hover:bg-brand-50/40 transition-colors sm:max-w-[280px]">
                        <img v-if="l.gambar" :src="`${l.url}?lihat=1`" alt="" loading="lazy" class="w-9 h-9 rounded-md object-cover bg-slate-100 shrink-0">
                        <span v-else class="grid place-items-center w-9 h-9 rounded-md bg-slate-100 text-slate-500 shrink-0"><Ikon nama="file-text" class="w-4 h-4" /></span>
                        <span class="min-w-0"><span class="block truncate font-medium text-navy-900">{{ l.nama }}</span><span class="block text-[11px] text-slate-400 tabular-nums">{{ ukuran(l.ukuran) }}</span></span>
                        <Ikon nama="unduh" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                      </a>
                    </li>
                  </ul>
                </li>
              </ol>
            </section>
          </div>
          <div class="px-5 py-4 bg-slate-50/70 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <a :href="cetakSatu(t)" target="_blank" class="inline-flex items-center gap-1.5 text-[12.5px] tautan"><Ikon nama="cetak" class="w-4 h-4" /> Cetak formulir ketidaksesuaian</a>
            <div v-if="bisaSunting" class="flex gap-2">
              <AsistenTemuan :audit-id="audit.id" :temuan="t" :isian="isian[t.id]" @terapkan="(kolom, nilai) => terapkanAsisten(t.id, kolom, nilai)" />
              <button type="button" class="btn-garis-bahaya btn-kecil" @click="hapus(t)"><Ikon nama="hapus" class="w-4 h-4" /> Hapus</button>
              <button type="submit" class="btn-utama btn-kecil" :disabled="isian[t.id].proses"><Ikon nama="simpan" class="w-4 h-4" /> Simpan</button>
            </div>
            <button v-else-if="bisaKirim(t)" type="submit" class="btn-utama btn-kecil" :disabled="isian[t.id].proses || !!masalahBerkas(t.id)"><Ikon nama="unggah" class="w-4 h-4" /> {{ isian[t.id].proses ? 'Mengirim…' : 'Kirim tindak lanjut' }}</button>
            <p v-else-if="bisaTindakLanjut" class="text-[12.5px] text-slate-500">Temuan sudah ditutup auditor.</p>
          </div>
        </form>
      </article>
    </div>
    <div v-else class="kartu"><Kosong judul="Belum ada temuan" :pesan="usulan.length ? 'Angkat ketidaksesuaian di atas menjadi temuan untuk mulai mencatat tindakan perbaikan.' : 'Temuan muncul setelah butir kriteria dinilai dengan capaian di bawah 100% (sub-elemen berincian: setelah seluruh rinciannya dinilai).'" ikon="centang-bulat" /></div>
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>
