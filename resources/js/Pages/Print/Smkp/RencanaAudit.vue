<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import CetakLayout from '../../../Components/Smkp/CetakLayout.vue';
import Lembar from '../../../Components/Smkp/Lembar.vue';
import FormTtd from '../../../Components/Smkp/FormTtd.vue';
import Ikon from '../../../Components/Smkp/Ikon.vue';
import { tanggalPanjang } from '../../../smkp/lib/format';
import { bagiLembar, jumlahBaris, potongTeks } from '../../../smkp/lib/halaman';
import { B, type BlokCetak as Blok, type Kolom } from '../../../smkp/lib/blokCetak';
import { susunMetodeSampel, type BarisMS } from '../../../smkp/lib/metodeSampel';
import type { KopDok, PihakTtd, RingkasAudit, TtdForm } from '../../../smkp/types';
import BlankLayout from '../../../Layouts/BlankLayout.vue';

/* Lembar cetak di luar kerangka aplikasi (konvensi EQOHSEE: Pages/Print + BlankLayout). */
defineOptions({ layout: BlankLayout });

interface Elemen { kode: string; nama: string; sub: Array<{ kode: string; nama: string }> }
const props = defineProps<{
  audit: RingkasAudit;
  rencana: Record<string, any> & { susunan?: Array<Record<string, string>>; tugas?: Array<Record<string, string>> };
  bawaan: Record<string, string>;
  kriteriaDaftar: Elemen[];
  jasa: boolean;
  risiko: { present?: Array<Record<string, string>>; future?: Array<Record<string, string>> };
  rekap: { lengkap: boolean; kurang: string[] };
  pihak: Array<{ peran: string; kunci: PihakTtd; nama: string }>;
  ttd: TtdForm;
  dok: KopDok;
  kembali: string;
}>();

const isi = (k: string) => String(props.rencana[k] ?? '').trim() || String(props.bawaan[k] ?? '').trim();
const baris = (k: string) => isi(k).split('\n').map((x) => x.trim()).filter(Boolean);
const tanggal = computed(() => (props.rencana.tanggal_mulai && props.rencana.tanggal_selesai
  ? `${tanggalPanjang(props.rencana.tanggal_mulai)} – ${tanggalPanjang(props.rencana.tanggal_selesai)}` : props.audit.periode));

/* Susunan kegiatan dikelompokkan per hari pelaksanaan. */
const hari = computed(() => {
  const kelompok: Array<{ tanggal: string; baris: Array<Record<string, string>> }> = [];
  for (const b of props.rencana.susunan ?? []) {
    const t = b.tanggal || '';
    const ada = kelompok.find((k) => k.tanggal === t);
    if (ada) ada.baris.push(b); else kelompok.push({ tanggal: t, baris: [b] });
  }
  return kelompok;
});
const NAMA_HARI = ['Pertama', 'Kedua', 'Ketiga', 'Keempat', 'Kelima', 'Keenam', 'Ketujuh'];

/*
 * Metode & sampel per kriteria, dibagi per lembar A4 menurut perkiraan tinggi
 * baris (nama kriteria resmi dan isian metode/sampel dapat terlipat). Elemen
 * yang berlanjut ke lembar berikutnya mendapat baris judul "(lanjutan)".
 */
const ms = (kode: string) => (props.rencana.metode_sampel ?? {})[kode] ?? {};
type BarisMetode = { jenis: 'elemen' | 'lanjut'; e: Elemen } | { jenis: 'sub'; e: Elemen; s: Elemen['sub'][number] };
// Tiap metode satu baris bernomor; sampelnya sejajar di baris yang sama (lib/metodeSampel).
const tabelMS = computed(() => Object.fromEntries(props.kriteriaDaftar.flatMap((e) => e.sub)
  .map((s) => [s.kode, susunMetodeSampel(ms(s.kode).metode, ms(s.kode).sampel)])));
const barisSampel = (r: BarisMS) => (r.sampel.length
  ? r.sampel.reduce((n, x) => n + jumlahBaris(x, 40), 0) + Math.ceil((r.sampel.length - 1) * 2 / 15) : 1);
function tinggiSub(s: Elemen['sub'][number]): number {
  const rs = tabelMS.value[s.kode];
  let total = 0;
  for (const r of rs) total += 23 + 15 * (Math.max(jumlahBaris(`${r.no ? `${r.no}. ` : ''}${r.metode || '—'}`, 21), r.rentang === 1 ? barisSampel(r) : 1) - 1);
  const bentang = rs.find((r) => r.rentang > 1);
  if (bentang) total = Math.max(total, 23 + 15 * (barisSampel(bentang) - 1));
  return Math.max(total, 23 + 15 * (jumlahBaris(s.nama, 32) - 1));
}
const tinggiMetode = (b: BarisMetode) => (b.jenis !== 'sub' ? 23 : tinggiSub(b.s));
const lembarMetode = computed(() => {
  const datar: BarisMetode[] = [];
  for (const e of props.kriteriaDaftar) {
    datar.push({ jenis: 'elemen', e });
    for (const s of e.sub) datar.push({ jenis: 'sub', e, s });
  }
  // A4 1123 px − bantalan bawah 60 − kop & judul 285 − kepala tabel 23, disisakan satu baris untuk judul lanjutan.
  return bagiLembar(datar, tinggiMetode, () => 730).map((isi) =>
    isi.length && isi[0].jenis !== 'elemen' ? [{ jenis: 'lanjut', e: isi[0].e } as BarisMetode, ...isi] : isi);
});

/* Top risks sebagai tabel 4 kolom; barisnya mengikuti jumlah isian di formulir. */
const terisi = (r: Record<string, string>) => ['kegiatan', 'risiko', 'nilai'].some((k) => String(r[k] ?? '').trim() !== '');
const topRisks = computed(() => [
  { kunci: 'present', huruf: 'A', judul: 'Top Risks — Risk of Present', ket: 'risiko pada periode audit', baris: (props.risiko.present ?? []).filter(terisi) },
  { kunci: 'future', huruf: 'B', judul: 'Top Risks — Risk of Future', ket: 'risiko dari rencana kegiatan', baris: (props.risiko.future ?? []).filter(terisi) },
]);

/*
 * Lembar dasar penetapan metode & sampel (hanya bila ada isinya), dibagi per
 * lembar A4 seperti metode & sampel: metode/dasar sampel yang panjang dipotong,
 * tabel top risks yang berlanjut mengulang judul dan kepala tabelnya.
 */
type TopRisk = (typeof topRisks.value)[number];
type BarisDasar =
  | { jenis: 'info'; label: string; teks: string; lanjut: boolean }
  | { jenis: 'judul'; t: TopRisk }
  | { jenis: 'risiko'; t: TopRisk; r: Record<string, string>; no: number }
  | { jenis: 'kosong'; t: TopRisk };
const tinggiDasar = (b: BarisDasar) => {
  if (b.jenis === 'info') return 27 + 19 * (jumlahBaris(b.teks, 64) - 1);
  if (b.jenis === 'judul') return 80;                       // judul tabel, kepala tabel, dan jaraknya
  if (b.jenis === 'kosong') return 40;
  return 27 + 19 * (Math.max(jumlahBaris(b.r.kegiatan, 30), jumlahBaris(b.r.risiko, 40), jumlahBaris(b.r.nilai, 8)) - 1);
};
const adaDasar = computed(() => !!(isi('metode') || isi('sampel') || topRisks.value.some((t) => t.baris.length)));
const lembarDasar = computed(() => {
  if (!adaDasar.value) return [];
  const datar: BarisDasar[] = [];
  for (const [kunci, label] of [['metode', 'Metode Audit'], ['sampel', 'Dasar Pengambilan Sampel']]) {
    if (isi(kunci)) datar.push(...potongTeks(isi(kunci), 64, 30).map((teks, j) => ({ jenis: 'info' as const, label, teks, lanjut: j > 0 })));
  }
  for (const t of topRisks.value) {
    datar.push({ jenis: 'judul', t });
    if (t.baris.length) datar.push(...t.baris.map((r, i) => ({ jenis: 'risiko' as const, t, r, no: i + 1 })));
    else datar.push({ jenis: 'kosong', t });
  }
  // A4 1123 px − bantalan bawah 60 − kop & judul 285; lembar lanjutan menyisakan ruang judul + kepala tabel yang diulang.
  const lembar = bagiLembar(datar, tinggiDasar, (i) => (i === 0 ? 740 : 690));
  // Judul tabel tidak ditinggal sendirian di dasar lembar: ikut ke lembar barisnya.
  for (let i = 0; i < lembar.length - 1; i++) {
    if (lembar[i][lembar[i].length - 1]?.jenis === 'judul') lembar[i + 1].unshift(lembar[i].pop()!);
  }
  return lembar.filter((x) => x.length).map((isiLembar) => {
    const kelompok: Array<{ kunci: string; t: TopRisk | null; lanjut: boolean; baris: BarisDasar[] }> = [];
    for (const b of isiLembar) {
      const kunci = b.jenis === 'info' ? 'info' : b.t.kunci;
      let g = kelompok[kelompok.length - 1];
      if (!g || g.kunci !== kunci) {
        g = { kunci, t: b.jenis === 'info' ? null : b.t, lanjut: b.jenis === 'risiko' || b.jenis === 'kosong', baris: [] };
        kelompok.push(g);
      }
      if (b.jenis !== 'judul') g.baris.push(b);
    }
    return kelompok;
  });
});
const halDasar = computed(() => 3 + lembarMetode.value.length);
const dari = computed(() => halDasar.value + lembarDasar.value.length);

/*
 * Isi yang sama disusun ulang sebagai blok untuk unduhan Word. Lembar cetak
 * memakai rowspan yang tidak dikenal .docx, jadi tiap kriteria menjadi satu
 * baris: metode bernomor dan sampelnya ditulis berbaris di dalam selnya.
 */
const CATATAN = [
  'Untuk audit perusahaan jasa pertambangan, Rencana Audit ditandatangani oleh Ketua Tim Audit, PJO dan KTT/PTL.',
  'Untuk audit perusahaan pertambangan, Rencana Audit ditandatangani oleh Ketua Tim Audit dan KTT.',
  'Untuk audit perusahaan pengolahan dan/atau pemurnian, Rencana Audit ditandatangani oleh Ketua Tim Audit dan PTL.',
];

function sampelTeks(rs: BarisMS[]): string {
  const berisi = rs.filter((r) => r.sampel.length);
  if (!berisi.length) return '—';
  if (berisi.length > 1) return berisi.map((r) => `${r.no}. ${r.sampel.join(', ')}`).join('\n');
  const butir = berisi[0].sampel;
  return butir.length > 1 ? butir.map((x) => `• ${x}`).join('\n') : butir[0];
}

const blokWord = computed<Blok[]>(() => {
  const kol = (lebar: string, judul: string, tengah = false): Kolom => ({ lebar, judul: [judul], tengah });
  const nomorkan = (xs: string[]) => (xs.length ? xs.map((x, i) => `${i + 1}. ${x}`).join('\n') : '—');
  const b: Blok[] = [];

  b.push({ k: 'judul', tingkat: 0, isi: ['Rencana Audit', 'Sistem Manajemen Keselamatan Pertambangan',
    ...(props.rencana.nomor ? [`No. ${props.rencana.nomor}`] : [])] });
  b.push({ k: 'tabel', kepala: false, kolom: [{ lebar: '30%' }, { lebar: '70%' }], baris: ([
    ['Jenis Audit', isi('jenis_audit')],
    ['Kriteria Audit', isi('kriteria')],
    ['Nama Perusahaan', props.audit.perusahaan.nama],
    ['Alamat', isi('alamat') || props.audit.perusahaan.alamat],
    ['Tanggal Pelaksanaan Audit', tanggal.value],
    ['Tujuan Audit', isi('tujuan')],
    ['Ruang Lingkup Audit', isi('ruang_lingkup')],
    ['Pengecualian', isi('pengecualian') || '—'],
    ['Tim Audit', (props.rencana.tugas ?? []).map((t) => `${t.peran || 'Auditor'}: ${t.nama}`).join('\n')],
    ['Dokumentasi Relevan', nomorkan(baris('dokumentasi'))],
    ['Fasilitas', nomorkan(baris('sumberdaya'))],
    ['Distribusi Laporan', nomorkan(baris('distribusi'))],
  ] as Array<[string, string]>).map(([l, v]) => [[B(l)], [v]]) });

  b.push({ k: 'judul', tingkat: 0, isi: ['Susunan Kegiatan Pelaksanaan Audit', 'Sistem Manajemen Keselamatan Pertambangan'] });
  b.push({ k: 'tabel', kepala: true,
    kolom: [kol('18%', 'Tanggal / Waktu'), kol('58%', 'Fungsi / Area / Departemen / Kegiatan yang akan diaudit (termasuk persyaratan terkait)'), kol('24%', 'Auditor')],
    baris: hari.value.length
      ? hari.value.flatMap((h, i) => [
          [[`Hari ${NAMA_HARI[i] ?? `Ke-${i + 1}`}${h.tanggal ? ` — ${tanggalPanjang(h.tanggal)}` : ''}`]],
          ...h.baris.map((x) => [[x.waktu ?? ''], [`${x.kegiatan ?? ''}${x.auditi ? `\nAuditi: ${x.auditi}` : ''}`], [x.auditor ?? '']]),
        ])
      : [[['Susunan kegiatan belum ditetapkan']]] });

  b.push({ k: 'judul', tingkat: 0, isi: ['Metode dan Sampel Audit', 'Sistem Manajemen Keselamatan Pertambangan'] });
  b.push({ k: 'tabel', kepala: true,
    kolom: [kol('7%', 'No', true), kol('30%', 'Kriteria'), kol('23%', 'Metode Audit'), kol('40%', 'Sampel')],
    baris: props.kriteriaDaftar.flatMap((e) => [
      [[`${e.kode}  ${e.nama.toUpperCase()}`]],
      ...e.sub.map((s) => {
        const rs = tabelMS.value[s.kode];
        return [[s.kode], [s.nama],
          [rs.map((r) => (r.no ? `${r.no}. ${r.metode}` : r.metode || '—')).join('\n')],
          [sampelTeks(rs)]];
      }),
    ]) });

  if (adaDasar.value) {
    b.push({ k: 'judul', tingkat: 0, isi: ['Dasar Penetapan Metode dan Sampel Audit', 'Sistem Manajemen Keselamatan Pertambangan'] });
    const info = ([['metode', 'Metode Audit'], ['sampel', 'Dasar Pengambilan Sampel']] as Array<[string, string]>)
      .filter(([k]) => isi(k)).map(([k, l]) => [[B(l)], [isi(k)]]);
    if (info.length) b.push({ k: 'tabel', kepala: false, kolom: [{ lebar: '30%' }, { lebar: '70%' }], baris: info });
    for (const t of topRisks.value) {
      b.push({ k: 'teks', isi: [B(`${t.huruf}. ${t.judul} `), `(${t.ket})`], lekat: true });
      b.push({ k: 'tabel', kepala: true,
        kolom: [kol('7%', 'No', true), kol('35%', 'Kegiatan'), kol('45%', 'Risiko'), kol('13%', 'Nilai Risiko', true)],
        baris: t.baris.length
          ? t.baris.map((r, i) => [[String(i + 1)], [r.kegiatan ?? ''], [r.risiko ?? ''], [r.nilai ?? '']])
          : [[['Tidak ada risiko yang dicatat']]] });
    }
  }

  b.push({ k: 'judul', tingkat: 0, isi: ['Pengesahan Rencana Audit'] });
  b.push({ k: 'ttd' });
  b.push({ k: 'teks', isi: [B('Catatan:')], lekat: true });
  b.push({ k: 'daftar', gaya: '•', butir: CATATAN.map((x) => ({ isi: [x] })) });
  return b;
});

const menyusunWord = ref(false);
async function unduhWord() {
  if (menyusunWord.value) return;
  menyusunWord.value = true;
  try {
    const { unduhWord: susunWord } = await import('../../../smkp/lib/wordCetak');
    await susunWord({
      namaBerkas: `Rencana Audit SMKP ${props.audit.perusahaan.nama} ${props.audit.tahun}`,
      judul: 'Rencana Audit Internal SMKP Minerba',
      dok: props.dok,
      blok: blokWord.value,
      ttd: { '': props.pihak.map((x) => ({ peran: x.peran, nama: x.nama, gambar: props.ttd.gambar[x.kunci] ?? null })) },
    });
  } catch (e) {
    console.error(e);
    window.alert('Dokumen Word gagal disusun. Muat ulang halaman lalu coba lagi.');
  } finally {
    menyusunWord.value = false;
  }
}
</script>

<template>
  <Head title="Rencana Audit" />
  <CetakLayout judul="Rencana Audit SMKP" :kembali="kembali"
               :peringatan="rekap.lengkap ? null : `Rencana Audit belum lengkap. Belum terisi: ${rekap.kurang.join(', ')}. Berkas tetap dapat dicetak.`">
    <template #aksi>
      <button type="button" class="btn btn-kecil border border-white/20 bg-white/10 text-white hover:bg-white/20" :disabled="menyusunWord"
              title="Unduh sebagai dokumen Word (.docx) untuk disunting" @click="unduhWord">
        <Ikon nama="unduh" class="w-4 h-4" />
        <span>{{ menyusunWord ? 'Menyusun…' : 'Word' }}<span v-if="!menyusunWord" class="hidden sm:inline"> (.docx)</span></span>
      </button>
    </template>
    <!-- Lembar 1: identitas rencana audit -->
    <Lembar :dok="dok" :halaman="1" :dari="dari">
      <p class="form-judul">Rencana Audit<br>Sistem Manajemen Keselamatan Pertambangan<template v-if="rencana.nomor"><br><span class="font-semibold normal-case">No. {{ rencana.nomor }}</span></template></p>
      <table class="tabel-form">
        <tbody>
          <tr><td class="label-sel font-semibold">Jenis Audit</td><td>{{ isi('jenis_audit') }}</td></tr>
          <tr><td class="label-sel font-semibold">Kriteria Audit</td><td class="whitespace-pre-line">{{ isi('kriteria') }}</td></tr>
          <tr><td class="label-sel font-semibold">Nama Perusahaan</td><td>{{ audit.perusahaan.nama }}</td></tr>
          <tr><td class="label-sel font-semibold">Alamat</td><td class="whitespace-pre-line">{{ isi('alamat') || audit.perusahaan.alamat }}</td></tr>
          <tr><td class="label-sel font-semibold">Tanggal Pelaksanaan Audit</td><td>{{ tanggal }}</td></tr>
          <tr><td class="label-sel font-semibold">Tujuan Audit</td><td class="whitespace-pre-line">{{ isi('tujuan') }}</td></tr>
          <tr><td class="label-sel font-semibold">Ruang Lingkup Audit</td><td class="whitespace-pre-line">{{ isi('ruang_lingkup') }}</td></tr>
          <tr><td class="label-sel font-semibold">Pengecualian</td><td class="whitespace-pre-line">{{ isi('pengecualian') || '—' }}</td></tr>
          <tr>
            <td class="label-sel font-semibold">Tim Audit</td>
            <td><p v-for="(t, i) in rencana.tugas ?? []" :key="i"><span class="inline-block w-28 text-slate-600">{{ t.peran || 'Auditor' }}</span>: {{ t.nama }}</p></td>
          </tr>
          <tr><td class="label-sel font-semibold">Dokumentasi Relevan</td><td><ol class="list-decimal pl-4"><li v-for="d in baris('dokumentasi')" :key="d">{{ d }}</li></ol></td></tr>
          <tr><td class="label-sel font-semibold">Fasilitas</td><td><ol class="list-decimal pl-4"><li v-for="d in baris('sumberdaya')" :key="d">{{ d }}</li></ol></td></tr>
          <tr><td class="label-sel font-semibold">Distribusi Laporan</td><td><ol class="list-decimal pl-4"><li v-for="d in baris('distribusi')" :key="d">{{ d }}</li></ol></td></tr>
        </tbody>
      </table>
    </Lembar>

    <!-- Lembar 2: susunan kegiatan -->
    <Lembar :dok="dok" :halaman="2" :dari="dari">
      <p class="form-judul">Susunan Kegiatan Pelaksanaan Audit<br>Sistem Manajemen Keselamatan Pertambangan</p>
      <table class="tabel-form">
        <thead><tr><th class="w-[18%]">Tanggal / Waktu</th><th>Fungsi / Area / Departemen / Kegiatan yang akan diaudit (termasuk persyaratan terkait)</th><th class="w-[24%]">Auditor</th></tr></thead>
        <tbody>
          <template v-for="(h, i) in hari" :key="i">
            <tr class="bg-slate-100 font-bold"><td colspan="3">Hari {{ NAMA_HARI[i] ?? `Ke-${i + 1}` }}<template v-if="h.tanggal"> — {{ tanggalPanjang(h.tanggal) }}</template></td></tr>
            <tr v-for="(b, j) in h.baris" :key="j">
              <td class="tabular-nums">{{ b.waktu }}</td>
              <td>{{ b.kegiatan }}<template v-if="b.auditi"><br><span class="text-slate-600">Auditi: {{ b.auditi }}</span></template></td>
              <td>{{ b.auditor }}</td>
            </tr>
          </template>
          <tr v-if="!hari.length"><td colspan="3" class="h-24 text-center text-slate-400 italic align-middle">Susunan kegiatan belum ditetapkan</td></tr>
        </tbody>
      </table>
    </Lembar>

    <!-- Lembar 3 dst.: metode dan sampel audit per kriteria -->
    <Lembar v-for="(kelompok, li) in lembarMetode" :key="li" :dok="dok" :halaman="3 + li" :dari="dari">
      <p class="form-judul">Metode dan Sampel Audit<br>Sistem Manajemen Keselamatan Pertambangan</p>
      <table class="tabel-form text-[10.5px] leading-snug">
        <thead><tr><th class="w-[7%]">No</th><th>Kriteria</th><th class="w-[23%]">Metode Audit</th><th class="w-[40%]">Sampel</th></tr></thead>
        <tbody>
          <template v-for="(b, j) in kelompok" :key="j">
            <tr v-if="b.jenis !== 'sub'" class="bg-slate-100 font-bold">
              <td class="text-center">{{ b.e.kode }}</td>
              <td colspan="3"><span class="uppercase">{{ b.e.nama }}</span><span v-if="b.jenis === 'lanjut'" class="font-normal italic text-slate-600"> (lanjutan)</span></td>
            </tr>
            <template v-else>
              <tr v-for="(r, k) in tabelMS[b.s.kode]" :key="k">
                <template v-if="k === 0">
                  <td class="text-center tabular-nums" :rowspan="tabelMS[b.s.kode].length">{{ b.s.kode }}</td>
                  <td :rowspan="tabelMS[b.s.kode].length">{{ b.s.nama }}</td>
                </template>
                <td :class="{ 'text-center text-slate-400': !r.metode }">
                  <span v-if="r.no" class="flex gap-1"><span class="tabular-nums shrink-0 whitespace-nowrap">{{ r.no }}.</span><span class="min-w-0">{{ r.metode }}</span></span>
                  <template v-else>{{ r.metode || '—' }}</template>
                </td>
                <td v-if="r.rentang" :rowspan="r.rentang" :class="{ 'text-center text-slate-400': !r.sampel.length }">
                  <ul v-if="r.sampel.length > 1" class="list-disc space-y-[2px] pl-3.5"><li v-for="(x, n) in r.sampel" :key="n">{{ x }}</li></ul>
                  <template v-else>{{ r.sampel[0] ?? '—' }}</template>
                </td>
              </tr>
            </template>
          </template>
        </tbody>
      </table>
    </Lembar>

    <!-- Dasar penetapan metode & sampel, termasuk top risks -->
    <Lembar v-for="(kelompok, li) in lembarDasar" :key="`dasar-${li}`" :dok="dok" :halaman="halDasar + li" :dari="dari">
      <p class="form-judul">Dasar Penetapan Metode dan Sampel Audit<br>Sistem Manajemen Keselamatan Pertambangan</p>
      <template v-for="g in kelompok" :key="g.kunci">
        <table v-if="g.kunci === 'info'" class="tabel-form mb-5">
          <tbody>
            <template v-for="(b, j) in g.baris" :key="j">
              <tr v-if="b.jenis === 'info'"><td class="label-sel font-semibold">{{ b.label }}<template v-if="b.lanjut"> (lanjutan)</template></td><td class="whitespace-pre-line">{{ b.teks }}</td></tr>
            </template>
          </tbody>
        </table>
        <div v-else-if="g.t" class="mb-5">
          <p class="font-bold text-slate-900 mb-1.5">{{ g.t.huruf }}. {{ g.t.judul }} <span class="font-normal text-slate-600">({{ g.t.ket }}){{ g.lanjut ? ' — lanjutan' : '' }}</span></p>
          <table class="tabel-form">
            <thead><tr><th class="w-[7%]">No</th><th class="w-[35%]">Kegiatan</th><th>Risiko</th><th class="w-[13%]">Nilai Risiko</th></tr></thead>
            <tbody>
              <template v-for="(b, j) in g.baris" :key="j">
                <tr v-if="b.jenis === 'risiko'">
                  <td class="text-center tabular-nums">{{ b.no }}</td>
                  <td>{{ b.r.kegiatan }}</td>
                  <td>{{ b.r.risiko }}</td>
                  <td class="text-center tabular-nums font-semibold">{{ b.r.nilai }}</td>
                </tr>
                <tr v-else-if="b.jenis === 'kosong'"><td colspan="4" class="h-10 text-center text-slate-400 italic align-middle">Tidak ada risiko yang dicatat</td></tr>
              </template>
            </tbody>
          </table>
        </div>
      </template>
    </Lembar>

    <!-- Lembar terakhir: pengesahan -->
    <Lembar :dok="dok" :halaman="dari" :dari="dari">
      <p class="form-judul">Pengesahan Rencana Audit</p>
      <FormTtd :pihak="pihak" :ttd="ttd" bagian="rencana" />
      <div class="mt-5 text-[10.5px] text-slate-700">
        <p class="font-bold">Catatan:</p>
        <ul class="list-disc pl-4">
          <li :class="jasa ? 'font-semibold text-slate-900' : ''">Untuk audit perusahaan jasa pertambangan, Rencana Audit ditandatangani oleh Ketua Tim Audit, PJO dan KTT/PTL.</li>
          <li :class="!jasa ? 'font-semibold text-slate-900' : ''">Untuk audit perusahaan pertambangan, Rencana Audit ditandatangani oleh Ketua Tim Audit dan KTT.</li>
          <li>Untuk audit perusahaan pengolahan dan/atau pemurnian, Rencana Audit ditandatangani oleh Ketua Tim Audit dan PTL.</li>
        </ul>
      </div>
    </Lembar>
  </CetakLayout>
</template>
