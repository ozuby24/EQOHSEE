<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import KepalaAudit from '../../Components/Smkp/KepalaAudit.vue';
import Ikon from '../../Components/Smkp/Ikon.vue';
import StatusSimpan from '../../Components/Smkp/StatusSimpan.vue';
import { useAutosimpan } from '../../smkp/lib/autosimpan';
import type { RingkasAudit } from '../../smkp/types';

interface Baris { elemen: string; sub: string; subsub?: string; keterangan?: string; uraian?: string }
interface Kode { kode: string; nama: string }
interface Elemen extends Kode { sub: Array<Kode & { subsub: Kode[] }> }
/** Butir bernilai penuh dari penilaian — App\Support\SmkpKesesuaian::otomatis(). */
interface Otomatis {
  kode: string; nama: string; maks: number; elemen: string; sub: string; subsub: string;
  keterangan: string; bawaan: string; diubah: boolean; keluar: boolean; ofi: boolean; justifikasi: string;
  catatan: string; bukti: string; manual: boolean;
}

const props = defineProps<{
  audit: RingkasAudit;
  otomatis: Otomatis[];
  kesesuaian: Baris[];
  rekomendasi: Baris[];
  namaPerusahaan: string;
  kriteria: Elemen[];
  ringkas: {
    rencana: { terisi: number; total: number };
    penilaian: { terisi: number; total: number };
    kesesuaian: number;
    kesesuaianNilai: number;
    rekomendasi: number;
    ketidaksesuaian: number;
    kategori: Record<string, number>;
    rtl: number;
  };
  versiSimpan: string;
  bisaSunting: boolean;
}>();

const id = props.audit.id;
const cetak = (jenis: string) => `/smkp/${id}/formulir/${jenis}/cetak`;

const kategoriTeks = computed(() => {
  const k = props.ringkas.kategori;
  return [['kritikal', 'kritikal'], ['mayor', 'mayor'], ['minor', 'minor']]
    .filter(([kunci]) => k[kunci]).map(([kunci, l]) => `${k[kunci]} ${l}`).join(' · ') || 'belum ada';
});

const FORMULIR = computed(() => [
  { no: 1, judul: 'Rencana Audit', ket: 'Jenis & kriteria audit, ruang lingkup, tim, susunan kegiatan, metode & sampel per kriteria, pengesahan.',
    status: `${props.ringkas.rencana.terisi}/${props.ringkas.rencana.total} komponen`, lengkap: props.ringkas.rencana.terisi === props.ringkas.rencana.total,
    isi: `/smkp/${id}/rencana`, cetak: `/smkp/${id}/rencana/cetak` },
  { no: 2, judul: 'Formulir Kriteria Audit (Penilaian SMKP)', ket: 'Nilai tiap sub-elemen dan sub-sub-elemen, total & persentase nilai elemen berbobot.',
    status: `${props.ringkas.penilaian.terisi}/${props.ringkas.penilaian.total} butir dinilai`, lengkap: props.ringkas.penilaian.total > 0 && props.ringkas.penilaian.terisi === props.ringkas.penilaian.total,
    isi: `/smkp/${id}/elemen/I`, cetak: cetak('penilaian') },
  { no: 3, judul: 'Formulir Kesesuaian', ket: 'Tersusun otomatis dari butir bernilai penuh pada penilaian, ditambah uraian tambahan auditor; ditandatangani auditor dan auditi.',
    status: `${props.ringkas.kesesuaian} uraian`, lengkap: props.ringkas.kesesuaian > 0, isi: '#kesesuaian', cetak: cetak('kesesuaian') },
  { no: 4, judul: 'Formulir Ketidaksesuaian & Tindak Lanjut', ket: 'Satu formulir per temuan: uraian masalah, lokasi, bukti, kategori, tindak lanjut auditi, ulasan auditor.',
    status: `${props.ringkas.ketidaksesuaian} temuan`, lengkap: props.ringkas.ketidaksesuaian > 0, isi: `/smkp/${id}/temuan`, cetak: cetak('ketidaksesuaian') },
  { no: 5, judul: 'Rekapitulasi Ketidaksesuaian', ket: 'Daftar seluruh ketidaksesuaian beserta jumlah temuan kritikal, mayor, dan minor.',
    status: kategoriTeks.value, lengkap: props.ringkas.ketidaksesuaian > 0, isi: null, cetak: cetak('rekapitulasi') },
  { no: 6, judul: 'Rekomendasi & Peluang untuk Perbaikan', ket: 'Opsional — OFI dari kesesuaian yang berjustifikasi, ditambah rekomendasi/OFI lain per elemen & sub-elemen.',
    status: `${props.ringkas.rekomendasi} uraian`, lengkap: props.ringkas.rekomendasi > 0, isi: '#rekomendasi', cetak: cetak('rekomendasi') },
  { no: 7, judul: 'Rencana Tindak Lanjut Ketidaksesuaian', ket: 'Akar permasalahan, tindakan koreksi, penanggung jawab, dan batas waktu tiap ketidaksesuaian.',
    status: `${props.ringkas.rtl}/${props.ringkas.ketidaksesuaian} terisi`, lengkap: props.ringkas.ketidaksesuaian > 0 && props.ringkas.rtl === props.ringkas.ketidaksesuaian,
    isi: `/smkp/${id}/temuan`, cetak: cetak('tindak-lanjut') },
]);

/* ───────── Editor kesesuaian & rekomendasi ───────── */
const form = useForm({
  otomatis: props.otomatis.map((r) => ({ kode: r.kode, masuk: !r.keluar, keterangan: r.keterangan, ofi: r.ofi, justifikasi: r.justifikasi })),
  kesesuaian: props.kesesuaian.map((b) => ({ elemen: b.elemen, sub: b.sub, subsub: b.subsub ?? '', keterangan: b.keterangan ?? '' })),
  rekomendasi: props.rekomendasi.map((b) => ({ elemen: b.elemen, sub: b.sub, subsub: b.subsub ?? '', uraian: b.uraian ?? '' })),
});

/* Simpan otomatis (port DBEST): beberapa detik setelah berhenti mengetik. */
const simpanOto = useAutosimpan({
  form, url: `/smkp/${props.audit.id}/formulir`, versi: props.versiSimpan, aktif: () => props.bisaSunting,
});
watch(() => props.versiSimpan, (v) => simpanOto.sudahDisimpan(v));

const subDari = (elemen: string) => props.kriteria.find((e) => e.kode === elemen)?.sub ?? [];
const subsubDari = (elemen: string, sub: string) => subDari(elemen).find((s) => s.kode === sub)?.subsub ?? [];
const tambahK = () => form.kesesuaian.push({ elemen: '', sub: '', subsub: '', keterangan: '' });
const tambahR = () => form.rekomendasi.push({ elemen: '', sub: '', subsub: '', uraian: '' });

function simpan() {
  form.post(`/smkp/${id}/formulir`, { preserveScroll: true });
}

/* ───────── Kesesuaian otomatis dari penilaian (port DBEST #23) ───────── */
const galat = (k: string) => (form.errors as Record<string, string>)[k];
const namaK = computed(() => {
  const m: Record<string, string> = {};
  for (const e of props.kriteria) {
    m[e.kode] = e.nama;
    for (const s of e.sub) { m[s.kode] = s.nama; for (const x of s.subsub) m[x.kode] = x.nama; }
  }
  return m;
});
const infoOto = computed(() => new Map(props.otomatis.map((r) => [r.kode, r])));
// Uraian tambahan berkode sama menggantikan uraian otomatisnya — dibaca dari isian yang sedang diketik.
const kodeTambahan = computed(() => new Set(form.kesesuaian.filter((b) => b.keterangan.trim() && b.elemen)
  .map((b) => b.subsub || b.sub || b.elemen)));
const diganti = (kode: string) => kodeTambahan.value.has(kode);
const nMasuk = computed(() => form.otomatis.filter((b) => b.masuk && !diganti(b.kode)).length);
const nOfi = computed(() => form.otomatis.filter((b) => b.ofi).length);
const nOfiTanpa = computed(() => form.otomatis.filter((b) => b.ofi && !b.justifikasi.trim()).length);

const cariOto = ref('');
const hanyaOfi = ref(false);
interface IsianOto { kode: string; masuk: boolean; keterangan: string; ofi: boolean; justifikasi: string }
const otoTampil = computed(() => {
  const q = cariOto.value.trim().toLowerCase();
  const out: Array<{ b: IsianOto; i: number; r: Otomatis }> = [];
  form.otomatis.forEach((b, i) => {
    const r = infoOto.value.get(b.kode);
    if (!r || (hanyaOfi.value && !b.ofi)) return;
    if (q && !`${r.kode} ${r.nama} ${namaK.value[r.sub] ?? ''} ${namaK.value[r.elemen] ?? ''}`.toLowerCase().includes(q)) return;
    out.push({ b, i, r });
  });
  return out;
});
const awalElemen = (j: number) => j === 0 || otoTampil.value[j - 1].r.elemen !== otoTampil.value[j].r.elemen;
const bawaanBeda = (b: { keterangan: string }, r: Otomatis) => b.keterangan.trim() !== '' && b.keterangan.trim() !== r.bawaan.trim();
</script>

<template>
  <div class="smkp-ui">
    <Head title="Formulir Audit" />
    <KepalaAudit :audit="audit" aktif="formulir" :bisa-sunting="bisaSunting" />

    <section class="kartu">
      <div class="kartu-kepala">
        <div>
          <h2 class="kartu-judul">Paket Formulir Audit SMKP</h2>
          <p class="kartu-sub">Mengikuti lampiran Kepdirjen Minerba No. 185.K/37.04/DJB/2019. Data diambil dari isian audit — tidak perlu mengetik ulang.</p>
        </div>
      </div>
      <ol class="divide-y divide-slate-100">
        <li v-for="f in FORMULIR" :key="f.no" class="grid grid-cols-[36px_1fr] md:grid-cols-[36px_1fr_190px_176px] items-center gap-x-4 gap-y-2 px-5 sm:px-6 py-4">
          <span class="grid place-items-center w-9 h-9 rounded-lg bg-navy-50 text-navy-800 text-[13px] font-bold tabular-nums">{{ f.no }}</span>
          <div class="min-w-0">
            <p class="text-[14px] font-semibold text-navy-900">{{ f.judul }}</p>
            <p class="text-[12.5px] text-slate-500 leading-snug">{{ f.ket }}</p>
          </div>
          <span class="col-start-2 md:col-start-auto inline-flex items-center gap-1.5 text-[12.5px] font-medium" :class="f.lengkap ? 'text-hijau-700' : 'text-slate-500'">
            <Ikon :nama="f.lengkap ? 'centang-bulat' : 'jam'" class="w-4 h-4 shrink-0" />{{ f.status }}
          </span>
          <div class="col-start-2 md:col-start-auto flex gap-2 md:justify-end">
            <component :is="f.isi.startsWith('#') ? 'a' : Link" v-if="f.isi" :href="f.isi" class="btn-garis btn-kecil"><Ikon nama="pensil" class="w-4 h-4" /> Isi</component>
            <a :href="f.cetak" target="_blank" rel="noopener" class="btn-garis btn-kecil"><Ikon nama="cetak" class="w-4 h-4" /> Cetak</a>
          </div>
        </li>
      </ol>
    </section>

    <form class="mt-5 grid xl:grid-cols-2 gap-5" @submit.prevent="simpan">
      <!-- Formulir 3: tersusun otomatis dari penilaian — setiap butir bernilai penuh satu uraian. -->
      <section id="kesesuaian" class="kartu scroll-mt-24 xl:col-span-2">
        <div class="kartu-kepala">
          <div>
            <h2 class="kartu-judul flex flex-wrap items-center gap-2">Uraian Kesesuaian
              <span class="lencana bg-hijau-50 text-hijau-700"><Ikon nama="kilau" class="w-3.5 h-3.5" /> otomatis dari penilaian</span></h2>
            <p class="kartu-sub">Formulir 3 — setiap butir yang mendapat nilai penuh (Kesesuaian) menjadi satu uraian, berisi deskripsi kriteria tertinggi Kepdirjen 185 atas nama {{ namaPerusahaan }}.
              Sunting keterangannya bila perlu; centang <b>Jadikan OFI</b> bila praktik yang sudah sesuai masih dapat ditingkatkan, lalu tulis justifikasinya.</p>
          </div>
          <div class="flex shrink-0 flex-wrap gap-2 text-[12px] text-slate-600">
            <span class="lencana bg-slate-100 text-navy-900 tabular-nums"><b>{{ nMasuk }}</b>/{{ form.otomatis.length }} masuk formulir</span>
            <span class="lencana bg-amber-50 text-amber-800 tabular-nums"><b>{{ nOfi }}</b> OFI</span>
          </div>
        </div>

        <p v-if="!otomatis.length" class="px-6 py-8 text-center text-[13px] text-slate-500">
          Belum ada butir bernilai penuh. Uraian kesesuaian muncul di sini begitu butir dinilai penuh di
          <Link :href="`/smkp/${id}/elemen/I`" class="font-semibold text-teal-700 hover:underline">Penilaian</Link>.
        </p>
        <template v-else>
          <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-slate-100 bg-slate-50/70 px-5 sm:px-6 py-2.5">
            <div class="relative flex-1 min-w-[180px] sm:max-w-[300px]">
              <Ikon nama="cari" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
              <input v-model="cariOto" type="search" class="input-kecil pl-8" placeholder="Cari kode atau kriteria" aria-label="Cari uraian kesesuaian">
            </div>
            <label class="inline-flex cursor-pointer select-none items-center gap-2 text-[12.5px] font-medium text-slate-600">
              <input v-model="hanyaOfi" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Hanya OFI
            </label>
            <span v-if="nOfiTanpa" class="inline-flex items-center gap-1 text-[12px] font-semibold text-amber-700">
              <Ikon nama="peringatan" class="w-3.5 h-3.5" /> {{ nOfiTanpa }} OFI belum berjustifikasi
            </span>
          </div>
          <fieldset :disabled="!bisaSunting">
            <ol class="divide-y divide-slate-100">
              <template v-for="(x, j) in otoTampil" :key="x.b.kode">
                <li v-if="awalElemen(j)" class="bg-slate-50/60 px-5 sm:px-6 py-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">
                  Elemen {{ x.r.elemen }} — {{ namaK[x.r.elemen] }}
                </li>
                <li :id="`kes-${x.b.kode.split('.').join('_')}`" class="px-5 sm:px-6 py-4 transition-colors"
                    :class="[!x.b.masuk || diganti(x.b.kode) ? 'bg-slate-50/80' : '', x.b.ofi ? 'border-l-4 border-l-amber-400' : '']">
                  <div class="flex flex-wrap items-start gap-x-3 gap-y-2">
                    <span class="lencana justify-center min-w-[52px] shrink-0 tabular-nums" :class="x.b.masuk && !diganti(x.b.kode) ? 'bg-hijau-50 text-hijau-700' : 'bg-slate-100 text-slate-500'">{{ x.r.kode }}</span>
                    <div class="min-w-0 flex-1">
                      <p class="text-[13px] font-semibold leading-snug text-navy-900" :class="!x.b.masuk ? 'line-through decoration-slate-300' : ''">{{ x.r.nama }}</p>
                      <p class="mt-0.5 text-[11.5px] text-slate-500">
                        <template v-if="x.r.subsub">{{ x.r.sub }} — {{ namaK[x.r.sub] }} · </template>nilai {{ x.r.maks }}/{{ x.r.maks }}
                      </p>
                    </div>
                    <label class="inline-flex shrink-0 cursor-pointer select-none items-center gap-1.5 text-[12px] font-semibold text-slate-600" title="Tidak dicentang: tidak dicetak di Formulir Kesesuaian dan Laporan Audit">
                      <input v-model="x.b.masuk" type="checkbox" class="rounded border-slate-300 text-hijau-600 focus:ring-hijau-500"> Masuk formulir
                    </label>
                  </div>
                  <p v-if="diganti(x.b.kode)" class="mt-2 flex items-start gap-1.5 rounded-lg bg-sky-50 px-3 py-2 text-[12px] leading-relaxed text-sky-800">
                    <Ikon nama="info" class="mt-0.5 w-3.5 h-3.5 shrink-0" /> Sudah ada uraian tambahan berkode {{ x.r.kode }} di bawah — uraian itu yang dicetak. Hapus uraian tambahannya untuk memakai uraian ini.
                  </p>
                  <textarea v-model="x.b.keterangan" rows="3" maxlength="3000" class="input mt-2" :aria-label="`Keterangan kesesuaian ${x.r.kode}`" />
                  <p v-if="galat(`otomatis.${x.i}.keterangan`)" class="galat">{{ galat(`otomatis.${x.i}.keterangan`) }}</p>
                  <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1.5">
                    <button v-if="bawaanBeda(x.b, x.r)" type="button" class="inline-flex items-center gap-1 text-[12px] font-semibold text-teal-700 hover:underline" @click="x.b.keterangan = x.r.bawaan">
                      <Ikon nama="putar" class="w-3.5 h-3.5" /> Kembalikan uraian bawaan
                    </button>
                    <details v-if="x.r.catatan || x.r.bukti" class="text-[12px] text-slate-600">
                      <summary class="cursor-pointer font-semibold text-slate-600 hover:text-navy-900">Catatan &amp; bukti penilaian</summary>
                      <p v-if="x.r.bukti" class="mt-1 whitespace-pre-line"><b>Bukti objektif:</b> {{ x.r.bukti }}</p>
                      <p v-if="x.r.catatan" class="mt-1 whitespace-pre-line"><b>Catatan auditor:</b> {{ x.r.catatan }}</p>
                    </details>
                    <label class="ml-auto inline-flex cursor-pointer select-none items-center gap-1.5 text-[12px] font-semibold text-amber-800">
                      <input v-model="x.b.ofi" type="checkbox" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500"> Jadikan OFI
                    </label>
                  </div>
                  <div v-if="x.b.ofi" class="mt-2 rounded-xl border border-amber-100 bg-amber-50/50 px-3 py-2.5">
                    <label class="label" :for="`kes-just-${x.i}`">Justifikasi auditor — peluang untuk perbaikan (OFI)</label>
                    <textarea :id="`kes-just-${x.i}`" v-model="x.b.justifikasi" rows="2" maxlength="3000" class="input"
                              placeholder="Sudah sesuai kriteria, namun dapat ditingkatkan: … (alasan dan saran perbaikannya)" />
                    <p v-if="!x.b.justifikasi.trim()" class="mt-1 text-[11.5px] font-semibold text-amber-700">Belum tercetak di Formulir Rekomendasi &amp; OFI sampai justifikasinya diisi.</p>
                  </div>
                </li>
              </template>
            </ol>
            <p v-if="!otoTampil.length" class="px-6 py-8 text-center text-[13px] text-slate-500">Tidak ada uraian yang cocok.</p>
          </fieldset>
        </template>
      </section>

      <section id="tambahan" class="kartu scroll-mt-24">
        <div class="kartu-kepala">
          <div><h2 class="kartu-judul">Uraian Kesesuaian Tambahan</h2><p class="kartu-sub">Opsional — praktik terbaik di luar butir bernilai penuh, atau uraian gabungan satu sub elemen. Uraian berkode sama menggantikan uraian otomatisnya.</p></div>
          <button v-if="bisaSunting" type="button" class="btn-garis btn-kecil" @click="tambahK"><Ikon nama="tambah" class="w-4 h-4" /> Tambah</button>
        </div>
        <fieldset :disabled="!bisaSunting">
          <ol v-if="form.kesesuaian.length" class="divide-y divide-slate-100">
            <li v-for="(b, i) in form.kesesuaian" :key="i" class="px-5 sm:px-6 py-4 grid sm:grid-cols-2 gap-3">
              <div>
                <label class="label" :for="`k-el-${i}`">Elemen</label>
                <select :id="`k-el-${i}`" v-model="b.elemen" class="input" @change="b.sub = ''; b.subsub = ''">
                  <option value="" disabled>Pilih elemen</option>
                  <option v-for="e in kriteria" :key="e.kode" :value="e.kode">{{ e.kode }} — {{ e.nama }}</option>
                </select>
                <p v-if="(form.errors as Record<string, string>)[`kesesuaian.${i}.elemen`]" class="galat">{{ (form.errors as Record<string, string>)[`kesesuaian.${i}.elemen`] }}</p>
              </div>
              <div>
                <label class="label" :for="`k-sub-${i}`">Sub elemen</label>
                <select :id="`k-sub-${i}`" v-model="b.sub" class="input" :disabled="!b.elemen" @change="b.subsub = ''">
                  <option value="">— Seluruh elemen —</option>
                  <option v-for="s in subDari(b.elemen)" :key="s.kode" :value="s.kode">{{ s.kode }} — {{ s.nama }}</option>
                </select>
              </div>
              <div v-if="subsubDari(b.elemen, b.sub).length" class="sm:col-span-2">
                <label class="label" :for="`k-ss-${i}`">Sub-sub elemen</label>
                <select :id="`k-ss-${i}`" v-model="b.subsub" class="input">
                  <option value="">— Seluruh sub elemen —</option>
                  <option v-for="s in subsubDari(b.elemen, b.sub)" :key="s.kode" :value="s.kode">{{ s.kode }} — {{ s.nama }}</option>
                </select>
              </div>
              <div class="sm:col-span-2">
                <div class="flex items-center justify-between"><label class="label" :for="`k-ket-${i}`">Keterangan</label>
                  <button v-if="bisaSunting" type="button" class="text-[12px] font-semibold text-rose-600 hover:text-rose-700" @click="form.kesesuaian.splice(i, 1)">Hapus</button></div>
                <textarea :id="`k-ket-${i}`" v-model="b.keterangan" rows="2" class="input" placeholder="Bukti kesesuaian: dokumen, rekaman, atau pengamatan" />
                <p v-if="(form.errors as Record<string, string>)[`kesesuaian.${i}.keterangan`]" class="galat">{{ (form.errors as Record<string, string>)[`kesesuaian.${i}.keterangan`] }}</p>
              </div>
            </li>
          </ol>
          <p v-else class="px-6 py-10 text-center text-[13px] text-slate-500">Belum ada uraian tambahan.</p>
        </fieldset>
      </section>

      <section id="rekomendasi" class="kartu scroll-mt-24">
        <div class="kartu-kepala">
          <div><h2 class="kartu-judul">Rekomendasi &amp; OFI Tambahan</h2><p class="kartu-sub">Formulir 6 — opsional. OFI dari kesesuaian dibuat lewat centang <b>Jadikan OFI</b> di atas; di sini rekomendasi/OFI lainnya.</p></div>
          <button v-if="bisaSunting" type="button" class="btn-garis btn-kecil" @click="tambahR"><Ikon nama="tambah" class="w-4 h-4" /> Tambah</button>
        </div>
        <fieldset :disabled="!bisaSunting">
          <ol v-if="form.rekomendasi.length" class="divide-y divide-slate-100">
            <li v-for="(b, i) in form.rekomendasi" :key="i" class="px-5 sm:px-6 py-4 grid sm:grid-cols-2 gap-3">
              <div>
                <label class="label" :for="`r-el-${i}`">Elemen</label>
                <select :id="`r-el-${i}`" v-model="b.elemen" class="input" @change="b.sub = ''; b.subsub = ''">
                  <option value="" disabled>Pilih elemen</option>
                  <option v-for="e in kriteria" :key="e.kode" :value="e.kode">{{ e.kode }} — {{ e.nama }}</option>
                </select>
                <p v-if="(form.errors as Record<string, string>)[`rekomendasi.${i}.elemen`]" class="galat">{{ (form.errors as Record<string, string>)[`rekomendasi.${i}.elemen`] }}</p>
              </div>
              <div>
                <label class="label" :for="`r-sub-${i}`">Sub elemen</label>
                <select :id="`r-sub-${i}`" v-model="b.sub" class="input" :disabled="!b.elemen" @change="b.subsub = ''">
                  <option value="">— Seluruh elemen —</option>
                  <option v-for="s in subDari(b.elemen)" :key="s.kode" :value="s.kode">{{ s.kode }} — {{ s.nama }}</option>
                </select>
              </div>
              <div v-if="subsubDari(b.elemen, b.sub).length" class="sm:col-span-2">
                <label class="label" :for="`r-ss-${i}`">Sub-sub elemen</label>
                <select :id="`r-ss-${i}`" v-model="b.subsub" class="input">
                  <option value="">— Seluruh sub elemen —</option>
                  <option v-for="s in subsubDari(b.elemen, b.sub)" :key="s.kode" :value="s.kode">{{ s.kode }} — {{ s.nama }}</option>
                </select>
              </div>
              <div class="sm:col-span-2">
                <div class="flex items-center justify-between"><label class="label" :for="`r-ur-${i}`">Rekomendasi dan peluang untuk perbaikan</label>
                  <button v-if="bisaSunting" type="button" class="text-[12px] font-semibold text-rose-600 hover:text-rose-700" @click="form.rekomendasi.splice(i, 1)">Hapus</button></div>
                <textarea :id="`r-ur-${i}`" v-model="b.uraian" rows="2" class="input" />
              </div>
            </li>
          </ol>
          <p v-else class="px-6 py-10 text-center text-[13px] text-slate-500">Belum ada rekomendasi atau OFI tambahan.</p>
        </fieldset>
      </section>

      <div v-if="bisaSunting" class="xl:col-span-2 sticky bottom-3 sm:bottom-4 z-10 flex items-center justify-between gap-3 rounded-kartu bg-navy-900 text-white px-3.5 py-2.5 sm:px-5 sm:py-3 shadow-angkat">
        <p class="min-w-0 text-[12.5px] sm:text-[13px] leading-snug text-white/80">{{ form.isDirty ? 'Ada perubahan yang belum disimpan.' : 'Kesesuaian dan rekomendasi tersimpan.' }}</p>
          <StatusSimpan :keadaan="simpanOto.keadaan.value" :teks="simpanOto.teks.value" :pulihan="simpanOto.pulihan.value" gelap @pulihkan="simpanOto.pulihkan" @buang="simpanOto.buangPulihan" />
        <button type="submit" class="btn-aksen btn-kecil shrink-0" :disabled="form.processing || !form.isDirty"><Ikon nama="simpan" class="w-4 h-4" /> Simpan formulir</button>
      </div>
    </form>
  </div>
</template>
