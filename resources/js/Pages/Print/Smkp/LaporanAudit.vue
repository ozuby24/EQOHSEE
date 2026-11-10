<script setup lang="ts">
/**
 * Laporan Audit Internal Penerapan SMKP Minerba — sistematika & teks baku
 * Lampiran II Kepdirjen Minerba 185.K/37.04/DJB/2019 (format laporan):
 * halaman judul, 1 latar belakang, 2 gambaran umum, 3 lingkup, 4 pelaksanaan
 * & tim auditor, 5 ringkasan & penilaian, 6 lampiran, penutup bertanda
 * tangan seluruh tim auditor (PJO untuk perusahaan jasa), distribusi.
 * Isi dibagi ke lembar A4 berkop dari tinggi yang diukur (lib/susunLembar).
 * Blok yang sama dapat diunduh sebagai dokumen Word untuk disunting
 * (lib/wordCetak; `?unduh=word` langsung mengunduhnya).
 */
import { computed, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import CetakLayout from '../../../Components/Smkp/CetakLayout.vue';
import Lembar from '../../../Components/Smkp/Lembar.vue';
import BlokCetak from '../../../Components/Smkp/BlokCetak.vue';
import FormTtd from '../../../Components/Smkp/FormTtd.vue';
import Ikon from '../../../Components/Smkp/Ikon.vue';
import { angka, tanggalPanjang, terbilang } from '../../../smkp/lib/format';
import { potongTeks } from '../../../smkp/lib/halaman';
import { useSusunLembar } from '../../../smkp/lib/susunLembar';
import { B, miring, type BlokCetak as Blok, type Isi, type Kolom } from '../../../smkp/lib/blokCetak';
import type { KopDok, PihakTtd, RingkasAudit, TtdForm } from '../../../smkp/types';
import BlankLayout from '../../../Layouts/BlankLayout.vue';

/* Lembar cetak di luar kerangka aplikasi (konvensi EQOHSEE: Pages/Print + BlankLayout). */
defineOptions({ layout: BlankLayout });

interface Isian {
  status_perusahaan: string; sk_izin: string; wiup: string; legalitas: string; kegiatan: string; penerapan: string;
  satuan_produksi: string; produksi: Array<{ periode: string; jumlah: string }>; pekerja: Array<{ jabatan: string; jumlah: string }>;
  peralatan: Array<{ jenis: string; tipe: string; produktivitas: string; jumlah: string }>;
  area: string[]; distribusi: string; tempat: string; tanggal: string;
}
interface Sub { kode: string; nama: string; teks: string | null; rinci: Array<{ kode: string; nama: string; teks: string }> }

const props = defineProps<{
  audit: RingkasAudit;
  isian: Isian;
  periode: string;
  alamatSite: string;
  jumlahPekerja: string;
  pengantar: string;
  distribusi: string[];
  penerapan: Array<{ kode: string; nama: string; sub: Sub[] }>;
  praktik: Array<{ elemen: string; namaElemen: string; kriteria: string; uraian: string }>;
  rekap: { skor: number; maks: number; nilai: number; dinilai: number; tingkat: { label: string } };
  elemen: Array<{ kode: string; nama: string; maks: number; nilai: number; skor: number }>;
  temuan: Array<{ nomor: string; kode: string; jenis: string; label: string; uraian: string }>;
  kategori: Record<string, string>;
  hitung: Record<string, number>;
  tim: Array<{ fungsi: string; nama: string; registrasi: string; tandaTangan: boolean; kunci: PihakTtd | null }>;
  pihakPjo: Array<{ peran: string; kunci: PihakTtd; nama: string }>;
  jasa: boolean;
  ttd: TtdForm;
  dok: KopDok;
  kembali: string;
}>();

const KOSONG = '……';
const SMKP = 'Sistem Manajemen Keselamatan Pertambangan';
const pt = computed(() => props.audit.perusahaan.nama);

/* ---------- penyusun isi ---------- */
const teks = (isi: Isi, inden: 0 | 1 | 2 = 1, lekat = false): Blok => ({ k: 'teks', isi, inden, lekat });
const judul = (tingkat: 1 | 2 | 3, nomor: string | undefined, isi: string): Blok => ({ k: 'judul', tingkat, nomor, isi: miring(isi) });
const kol = (lebar: string, judulKol?: string, opsi: { tengah?: boolean; rata?: boolean } = {}): Kolom => ({ lebar, judul: judulKol === undefined ? undefined : miring(judulKol), ...opsi });
const tabel = (kolom: Kolom[], baris: Isi[][], kepala = true): Blok => ({ k: 'tabel', kolom, baris, kepala });
const bilangan = (n: number) => (Number.isInteger(n) ? String(n) : angka(n, 2));

/** "1 Januari – 31 Desember 2025" → ["1 Januari 2025", "31 Desember 2025"]; null bila bukan rentang. */
function rentang(t: string): [string, string] | null {
  const m = t.match(/^(.+?)\s*(?:–|—|-|s\.\s?d\.?|s\/d|sampai dengan)\s*(.+)$/i);
  if (!m) return null;
  let a = m[1].trim();
  const b = m[2].trim();
  const th = b.match(/\b(\d{4})$/);
  if (th && !/\b\d{4}$/.test(a)) a = `${a} ${th[1]}`;
  return [a, b];
}
const sejak = (t: string) => {
  const r = rentang(t);
  return r ? `sejak periode ${r[0]} sampai dengan periode ${r[1]}` : `pada periode ${t}`;
};
/** Tanggal pelaksanaan: "05 – 23 Oktober 2026" bila bulan & tahunnya sama. */
const pelaksanaan = computed(() => {
  const { mulai, selesai } = props.audit;
  if (!mulai) return '';
  if (!selesai || selesai === mulai) return mulai;
  const a = mulai.split(' ');
  const b = selesai.split(' ');
  return a.length === 3 && b.length === 3 && a[1] === b[1] && a[2] === b[2] ? `${a[0]} – ${selesai}` : `${mulai} – ${selesai}`;
});
const pelaksanaanKalimat = computed(() => {
  const { mulai, selesai } = props.audit;
  return mulai ? `sejak periode ${mulai} sampai dengan periode ${selesai || mulai}` : `pada periode ${KOSONG}`;
});

const blok = computed<Blok[]>(() => {
  const x = props.isian;
  const p = pt.value;
  const totalPekerja = x.pekerja.reduce((n, r) => n + (Number(r.jumlah.replace(/\D/g, '')) || 0), 0);
  const isi: Blok[] = [];

  /* ===== 1 ===== */
  isi.push(
    judul(1, '1.', 'Latar Belakang'),
    teks([`Audit SMKP Minerba ini merupakan bagian dari penerapan Elemen SMKP Minerba sesuai dengan ketentuan dalam Peraturan Menteri Energi dan Sumber Daya Mineral Nomor 26 Tahun 2018 dan Keputusan Menteri Energi dan Sumber Daya Mineral Nomor 1827.K/30/MEM/2018 Lampiran IV serta Keputusan Direktur Jenderal Mineral dan Batubara Nomor 185.K/37.04/DJB/2019 tentang Petunjuk Teknis Pelaksanaan Keselamatan Pertambangan dan Pelaksanaan, Penilaian, dan Pelaporan ${SMKP} Mineral dan Batubara.`]),
    teks([`Audit SMKP Minerba ini bertujuan untuk mendapatkan gambaran tingkat penerapan SMKP Minerba di ${p}, ${sejak(props.periode)}.`]),
  );

  /* ===== 2 ===== */
  isi.push(
    judul(1, '2.', 'Gambaran Umum Perusahaan'),
    teks([`Adapun gambaran umum ${p} adalah sebagai berikut:`], 1, true),
    judul(2, '2.1.', 'Domisili dan Legalitas'),
  );
  if (x.legalitas) isi.push(...x.legalitas.split(/\n{2,}/).map((t) => teks([t.trim()], 2)));
  isi.push(
    teks([`Adapun domisili ${p} adalah sebagai berikut:`], 2, true),
    { k: 'rincian', tanpaTitik: true, baris: [
      ['Nama Perusahaan', [p]],
      ...(x.status_perusahaan ? [['Status Perusahaan', [x.status_perusahaan]] as [string, Isi]] : []),
      ['Alamat Kantor Pusat', [props.audit.perusahaan.alamat || KOSONG]],
      ['Alamat Kantor Site', [props.alamatSite || KOSONG]],
      ['Jenis Perizinan', [props.audit.perusahaan.izin || KOSONG]],
      ...(x.sk_izin ? [['Nomor SK Perizinan', [x.sk_izin]] as [string, Isi]] : []),
      ...(x.wiup ? [['WIUP', [x.wiup]] as [string, Isi]] : []),
      ...(props.audit.perusahaan.komoditas ? [['Jenis Komoditas', [props.audit.perusahaan.komoditas]] as [string, Isi]] : []),
    ] },
    judul(2, '2.2.', 'Kegiatan Pertambangan'),
  );
  if (x.kegiatan) isi.push(...x.kegiatan.split(/\n{2,}/).map((t) => teks([t.trim()], 2)));
  if (x.produksi.length) {
    isi.push(
      teks([`Jumlah produksi ${p} pada periode audit adalah sebagai berikut:`], 2, true),
      tabel([kol('10%', 'No', { tengah: true }), kol('45%', 'Periode'), kol('45%', `Jumlah Produksi${x.satuan_produksi ? ` (${x.satuan_produksi})` : ''}`, { tengah: true })],
        x.produksi.map((r, i) => [[String(i + 1)], [r.periode], [r.jumlah]])),
    );
  }
  if (x.pekerja.length) {
    isi.push(
      teks([`Adapun secara struktur organisasi lapangan, ${p} dipimpin oleh seorang ${props.jasa ? 'Penanggung Jawab Operasional' : 'Kepala Teknik Tambang'}, dengan jumlah pekerja tambang sebanyak ${totalPekerja} orang dengan rincian sebagai berikut:`], 2, true),
      tabel([kol('60%', 'Jabatan'), kol('40%', 'Jumlah', { tengah: true })],
        [...x.pekerja.map((r) => [[r.jabatan], [`${r.jumlah} Orang`]]), [[B('Total')], [B(`${totalPekerja} Orang`)]]]),
    );
  } else if (props.jumlahPekerja) {
    isi.push(teks([`Jumlah pekerja tambang ${p} sebanyak ${props.jumlahPekerja} orang.`], 2));
  }
  if (x.peralatan.length) {
    isi.push(
      teks([`Sedangkan peralatan tambang yang ada di ${p} meliputi:`], 2, true),
      tabel([kol('28%', 'Jenis Peralatan'), kol('27%', 'Tipe'), kol('27%', 'Produktivitas'), kol('18%', 'Jumlah Alat', { tengah: true })],
        x.peralatan.map((r) => [[r.jenis], [r.tipe], [r.produktivitas], [r.jumlah]])),
    );
  }
  isi.push(
    judul(2, '2.3.', 'Gambaran Umum Penerapan SMKP Minerba'),
    ...(x.penerapan || props.pengantar).split(/\n+/).filter((t) => t.trim()).map((t) => teks([t.trim()], 2)),
  );
  if (props.penerapan.length) {
    isi.push(teks([`Ringkasan penerapan SMKP di ${p} untuk setiap elemen sebagai berikut:`], 2, true));
    for (const e of props.penerapan) {
      isi.push(judul(3, undefined, `Elemen ${e.kode} : ${e.nama}`));
      e.sub.forEach((s, i) => {
        isi.push(teks([B(`${i + 1}. ${s.nama}`)], 2, true));
        if (s.teks) isi.push(teks([s.teks], 2));
        // Tiap sub-sub-elemen satu blok: uraiannya panjang dan harus dapat berpindah lembar.
        for (const r of s.rinci) isi.push({ k: 'daftar', gaya: '•', inden: 2, butir: [{ isi: [r.teks] }] });
      });
    }
  } else {
    isi.push(teks(['Penilaian kriteria audit belum diisi.'], 2));
  }

  /* ===== 3 ===== */
  isi.push(
    judul(1, '3.', 'Lingkup Audit'),
    teks([`Audit SMKP Minerba ini merupakan audit internal yang merupakan audit berkala dari periode sebelumnya untuk mendapatkan gambaran tingkat penerapan SMKP Minerba pada ${p}, dengan periode penilaian ${sejak(props.periode).replace(/^sejak periode /, 'mulai dari ').replace(/^pada periode /, '')}.`]),
    teks(['Audit SMKP Minerba ini dilaksanakan dengan mengambil sampel berdasarkan judgement auditor dari setiap area kegiatan yang terdiri atas:'], 1, true),
    { k: 'daftar', gaya: '1.', awalan: '3.', inden: 1, butir: (x.area.length ? x.area : [KOSONG, KOSONG, KOSONG]).map((a) => ({ isi: [a] })) },
  );

  /* ===== 4 ===== */
  const tim = props.tim.length ? props.tim : [{ fungsi: 'Lead Auditor', nama: '', registrasi: '' }, { fungsi: 'Auditor', nama: '', registrasi: '' }];
  isi.push(
    judul(1, '4.', 'Pelaksanaan Audit & Tim Auditor'),
    teks([`Audit SMKP Minerba dilakukan oleh internal auditor ${p} yang dilaksanakan ${pelaksanaanKalimat.value}, dengan tim auditor yang terdiri atas:`], 1, true),
    tabel([kol('28%', 'Fungsi'), kol('37%', 'Nama'), kol('35%', 'Nomor Registrasi')], tim.map((t) => [[t.fungsi], [t.nama], [t.registrasi]])),
  );

  /* ===== 5 ===== */
  isi.push(
    judul(1, '5.', 'Ringkasan Laporan & Penilaian Audit'),
    judul(2, '5.1.', 'Hasil Audit'),
    teks([`Hasil Audit SMKP Minerba yang dilaksanakan terhadap ${p} adalah audit internal ${pelaksanaanKalimat.value}, dengan memperoleh informasi sebagai berikut:`], 2, true),
    judul(3, '5.1.1.', 'Informasi Praktik Terbaik'),
  );
  if (props.praktik.length) {
    const baris: Isi[][] = [];
    let el = '';
    let no = 0;
    for (const r of props.praktik) {
      if (r.elemen !== el) { el = r.elemen; no = 0; baris.push([[`Elemen ${r.elemen} ${r.namaElemen}`]]); }
      baris.push([[`${++no}.`], [r.kriteria], [r.uraian]]);
    }
    isi.push(tabel([kol('8%', 'No', { tengah: true }), kol('30%', 'Kriteria'), kol('62%', 'Uraian Praktik Terbaik', { rata: true })], baris));
  } else {
    isi.push(teks(['Tidak terdapat praktik terbaik yang dicatat pada Formulir Kesesuaian.'], 2));
  }
  isi.push(judul(3, '5.1.2.', 'Ringkasan Temuan Ketidaksesuaian'));
  const jenis = Object.keys(props.kategori);
  isi.push({ k: 'rincian', baris: jenis.map((k) => [`Kategori ${props.kategori[k]}`, [props.hitung[k] ? `${props.hitung[k]} ketidaksesuaian` : 'Tidak Ada']] as [string, Isi]) });
  for (const k of jenis) {
    const daftar = props.temuan.filter((t) => t.jenis === k);
    if (!daftar.length) continue;
    // Uraian yang sangat panjang dipecah agar tiap baris tabel muat dalam satu lembar.
    const baris = daftar.flatMap((t) => potongTeks(t.uraian, 95, 42).map((u, j) => [[j ? `${t.nomor}\n(lanjutan)` : t.nomor], [u]] as Isi[]));
    isi.push(
      teks([B(`Uraian Kategori ${props.kategori[k]}`)], 2, true),
      tabel([kol('22%', 'Nomor Ketidaksesuaian'), kol('78%', 'Deskripsi Ketidaksesuaian', { rata: true })], baris),
    );
  }
  const persen = props.rekap.skor;
  isi.push(
    judul(2, '5.2.', 'Tingkat Pencapaian Penerapan SMKP Minerba'),
    teks([`Selanjutnya, berdasarkan Pedoman Teknis SMKP Minerba sebagaimana dimaksud, maka tingkat Pencapaian Penerapan SMKP di ${p} adalah `,
      B(`${angka(persen, 2).replace(/,?0+$/, '')}%`), ` (${terbilang(persen)} Persen).`], 2, true),
    tabel([kol('8%', 'No', { tengah: true }), kol('40%', 'Elemen'), kol('17%', 'Nilai Maksimal Elemen', { tengah: true }), kol('15%', 'Nilai Audit', { tengah: true }), kol('20%', '% Nilai Elemen SMKP', { tengah: true })], [
      ...props.elemen.map((e, i) => [[String(i + 1)], [e.nama], [bilangan(e.maks)], [bilangan(e.nilai)], [`${angka(e.skor, 2)}%`]]),
      [[''], [B('Total')], [B(bilangan(props.rekap.maks))], [B(bilangan(props.rekap.nilai))], [B(`${angka(persen, 2)}%`)]],
    ]),
  );

  /* ===== 6 ===== */
  isi.push(
    judul(1, '6.', 'Lampiran'),
    { k: 'daftar', gaya: '1.', inden: 1, butir: [
      'Formulir Kriteria Audit',
      'Formulir Ketidaksesuaian dan Tindak Lanjut Ketidaksesuaian Audit',
      'Formulir Rekomendasi dan Peluang untuk Perbaikan (OFI)',
      'Formulir Kesesuaian Audit',
      'Formulir Rekapitulasi Ketidaksesuaian',
      'Formulir Rencana Tindak Lanjut Audit',
      'Daftar Hadir Peserta Pertemuan Pembukaan Audit',
      'Daftar Hadir Peserta Pertemuan Penutupan Audit',
      `Respon ${p} Terhadap Pelaksanaan Audit`,
      ...(props.jasa ? [] : [`Hasil Audit Pemegang IUJP yang bekerja pada ${p}`]),
    ].map((t) => ({ isi: [t] })) },
  );

  /* ===== Penutup ===== */
  isi.push(
    teks([`${x.tempat || KOSONG}, ${x.tanggal ? tanggalPanjang(x.tanggal) : KOSONG}`], 0, true),
    teks(['Dibuat Oleh,'], 0, true),
    teks([`Tim Internal Auditor ${p}`], 0, true),
    { k: 'ttd', id: 'tim' },
  );
  if (props.pihakPjo.length) isi.push(teks(['Mengetahui,'], 0, true), { k: 'ttd', id: 'pjo' });
  isi.push(
    teks([B('Distribusi Laporan')], 0, true),
    { k: 'daftar', gaya: '1.', inden: 0, butir: (x.distribusi ? x.distribusi.split('\n').map((t) => t.trim()).filter(Boolean) : props.distribusi).map((t) => ({ isi: [t] })) },
  );
  return isi;
});

// Penanda tangan laporan: Lead Auditor & auditor (pemandu/pengamat/tenaga ahli tidak menandatangani).
const pihakTim = computed(() => {
  const t = props.tim.filter((a) => a.tandaTangan);
  return t.length
    ? t.map((a) => ({ peran: a.fungsi, kunci: a.kunci, nama: a.nama }))
    : [{ peran: 'Lead Auditor', kunci: 'ketua' as PihakTtd, nama: '' }, { peran: 'Auditor', kunci: null, nama: '' }];
});

const { ukurEl, lembar } = useSusunLembar(blok);

const menyusunWord = ref(false);
async function unduhWord() {
  if (menyusunWord.value) return;
  menyusunWord.value = true;
  try {
    const { unduhWord: susunWord } = await import('../../../smkp/lib/wordCetak');
    await susunWord({
      namaBerkas: `Laporan Audit SMKP ${pt.value} ${props.audit.tahun}`,
      judul: `Laporan Audit Internal SMKP Minerba ${pt.value}`,
      dok: props.dok,
      blok: blok.value,
      sampul: {
        judul: [['Laporan ', { t: 'Internal Audit', i: true }], ['Penerapan Sistem Manajemen'], ['Keselamatan Pertambangan'], ['Mineral dan Batubara']],
        perusahaan: pt.value,
        rincian: [['Alamat Kantor Pusat', props.audit.perusahaan.alamat ?? ''], ['Alamat Kantor Site', props.alamatSite], ['Pelaksanaan Audit', pelaksanaan.value]],
      },
      ttd: {
        tim: pihakTim.value.map((x) => ({ peran: x.peran, nama: x.nama, gambar: x.kunci ? props.ttd.gambar[x.kunci] : null })),
        pjo: props.pihakPjo.map((x) => ({ peran: x.peran, nama: x.nama, gambar: props.ttd.gambar[x.kunci] })),
      },
    });
  } catch (e) {
    console.error(e);
    window.alert('Dokumen Word gagal disusun. Muat ulang halaman lalu coba lagi.');
  } finally {
    menyusunWord.value = false;
  }
}

// Tautan "Unduh Word" dari halaman isian laporan membuka halaman ini dengan ?unduh=word.
// Parameternya dihapus dari alamat agar memuat ulang halaman tidak mengunduh lagi.
onMounted(() => {
  const alamat = new URL(window.location.href);
  if (alamat.searchParams.get('unduh') !== 'word') return;
  alamat.searchParams.delete('unduh');
  router.replace({ url: alamat.pathname + alamat.search + alamat.hash, preserveState: true, preserveScroll: true });
  void unduhWord();
});
</script>

<template>
  <Head title="Laporan Audit SMKP" />
  <CetakLayout judul="Laporan Audit Internal SMKP" :kembali="kembali">
    <template #aksi>
      <button type="button" class="btn btn-kecil border border-white/20 bg-white/10 text-white hover:bg-white/20" :disabled="menyusunWord"
              title="Unduh sebagai dokumen Word (.docx) untuk disunting" @click="unduhWord">
        <Ikon nama="unduh" class="w-4 h-4" />
        <span>{{ menyusunWord ? 'Menyusun…' : 'Word' }}<span v-if="!menyusunWord" class="hidden sm:inline"> (.docx)</span></span>
      </button>
    </template>
    <!-- Wadah ukur: lebar & tata letak sama dengan lembar cetak, tidak terlihat. -->
    <div ref="ukurEl" class="smkp-ukur no-print" aria-hidden="true" inert>
      <Lembar :dok="dok" :halaman="1" :dari="1">
        <div data-awal />
        <BlokCetak v-for="(b, i) in blok" :key="i" :blok="b" data-blok>
          <template v-if="b.k === 'ttd'">
            <FormTtd v-if="b.id === 'tim'" daftar :pihak="pihakTim" :ttd="ttd" />
            <FormTtd v-else daftar :pihak="pihakPjo" :ttd="ttd" />
          </template>
        </BlokCetak>
      </Lembar>
    </div>

    <!-- Halaman judul -->
    <Lembar polos :dok="dok" :halaman="0" :dari="0">
      <div class="flex min-h-[255mm] flex-col items-center text-center">
        <h1 class="mt-14 text-[19px] font-bold uppercase leading-snug text-slate-900">
          Laporan <i>Internal Audit</i><br>Penerapan Sistem Manajemen<br>Keselamatan Pertambangan<br>Mineral dan Batubara
        </h1>
        <img v-if="dok.logo" :src="dok.logo" alt="" class="mt-16 h-32 w-auto max-w-[60%] object-contain">
        <p class="mt-8 text-[15px] font-bold uppercase text-slate-900">{{ audit.perusahaan.nama }}</p>
        <table class="mt-auto mb-6 w-full text-left text-[12px] font-bold text-slate-900">
          <tbody>
            <tr v-for="r in [['Alamat Kantor Pusat', audit.perusahaan.alamat], ['Alamat Kantor Site', alamatSite], ['Pelaksanaan Audit', pelaksanaan]]" :key="r[0]">
              <td class="w-[34%] py-1 align-top uppercase">{{ r[0] }}</td><td class="w-4 py-1 align-top">:</td>
              <td class="py-1 align-top whitespace-pre-line">{{ r[1] || '……' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </Lembar>

    <p v-if="!lembar" class="text-center text-[12.5px] text-slate-500 py-10">Menyusun halaman…</p>
    <template v-else>
      <Lembar v-for="(isi, n) in lembar" :key="n" :dok="dok" :halaman="n + 1" :dari="lembar.length">
        <BlokCetak v-for="c in isi" :key="`${c.i}-${c.dari ?? 0}`" :blok="blok[c.i]" :dari="c.dari" :sampai="c.sampai">
          <template v-if="blok[c.i].k === 'ttd'">
            <FormTtd v-if="(blok[c.i] as { id?: string }).id === 'tim'" daftar :pihak="pihakTim" :ttd="ttd" />
            <FormTtd v-else daftar :pihak="pihakPjo" :ttd="ttd" />
          </template>
        </BlokCetak>
      </Lembar>
    </template>
  </CetakLayout>
</template>
