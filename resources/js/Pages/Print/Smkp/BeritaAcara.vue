<script setup lang="ts">
/**
 * Formulir Berita Acara Hasil Pelaksanaan Tahapan Awal Audit Internal SMKP —
 * susunan dan teks baku mengikuti Lampiran I Surat Edaran Dirjen Minerba
 * B-6450/MB.07/DBT.KP/2022. Isi disusun sebagai blok, diukur di wadah
 * tersembunyi, lalu dibagi ke lembar A4 berkop (lib/halaman bagiBlok).
 */
import { computed, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import CetakLayout from '../../../Components/Smkp/CetakLayout.vue';
import Lembar from '../../../Components/Smkp/Lembar.vue';
import BlokCetak from '../../../Components/Smkp/BlokCetak.vue';
import FormTtd from '../../../Components/Smkp/FormTtd.vue';
import Ikon from '../../../Components/Smkp/Ikon.vue';
import { angka, tanggalPanjang } from '../../../smkp/lib/format';
import { useSusunLembar } from '../../../smkp/lib/susunLembar';
import { B, miring, type BlokCetak as Blok, type Isi, type Kolom } from '../../../smkp/lib/blokCetak';
import type { KopDok, PihakTtd, RingkasAudit, TtdForm } from '../../../smkp/types';
import BlankLayout from '../../../Layouts/BlankLayout.vue';

/* Lembar cetak di luar kerangka aplikasi (konvensi EQOHSEE: Pages/Print + BlankLayout). */
defineOptions({ layout: BlankLayout });

interface Persiapan {
  tren: Record<string, string[]>;
  peralatan: Array<{ jenis: string; pa: string; ma: string }>;
  kli: Array<{ indikator: string; status: string }>;
  audit_lalu: Record<string, string>;
  masukan: Array<{ sumber: string; isi: string }>;
}

const props = defineProps<{
  audit: RingkasAudit;
  permulaan: Record<string, any>;
  periode: string;
  alamatSite: string;
  persiapan: Persiapan;
  tren: Record<string, { judul: string; kolom: string }>;
  auditLalu: Record<string, string>;
  buktiIntegritas: string;
  risiko: { present: Array<Record<string, string>>; future: Array<Record<string, string>> };
  nomorForm: Record<'rencana' | 'kesesuaian' | 'ketidaksesuaian' | 'rekapitulasi' | 'tindakLanjut' | 'rekomendasi' | 'rapat', string>;
  kinerjaIsi: Record<string, string>;
  tim: Array<{ nama: string; peran?: string; registrasi?: string; lingkup?: string }>;
  elemen: Array<{ kode: string; nama: string; status: string | null; label: string; ket: string }>;
  kelayakan: Record<string, string>;
  faktor: Record<string, string>;
  kinerja: Record<string, { label: string; satuan: string }>;
  mandays: { dasar: number; auditor: number; per_auditor: number; kondisi: number; penyesuaian: number; persen_penyesuaian: number; total: number; tahap1: number; tahap2: number; aturan?: string };
  rekap: { lengkap: number; tidak: number; belum: number; total: number; siap: boolean };
  pihak: Array<{ peran: string; kunci: PihakTtd; nama: string; ket?: string }>;
  ttd: TtdForm;
  dok: KopDok;
  kembali: string;
}>();

const KOSONG = '……';
const SMKP = 'Sistem Manajemen Keselamatan Pertambangan';

/* ---------- penyusun isi ---------- */
const teks = (isi: Isi, lekat = false): Blok => ({ k: 'teks', isi, lekat });
const judul = (tingkat: 1 | 2 | 3, nomor: string | undefined, isi: string): Blok => ({ k: 'judul', tingkat, nomor, isi: miring(isi) });
const kol = (lebar: string, judulKol?: string, tengah = false): Kolom => ({ lebar, judul: judulKol === undefined ? undefined : miring(judulKol), tengah });
const tabel = (kolom: Kolom[], baris: Isi[][], kepala = true): Blok => ({ k: 'tabel', kolom, baris, kepala });
// Tabel isian berbentuk "label | isi" seperti formulir resmi.
const tabelIsian = (baris: Array<[string, string]>): Blok => tabel([kol('45%'), kol('55%')], baris.map(([l, v]) => [miring(l), [v]]), false);
// Tabel bernomor; bila belum diisi tetap dicetak tiga baris kosong untuk diisi tangan.
const bernomor = (isi: string[][], lebarKolom: number): Isi[][] => (isi.length ? isi : [1, 2, 3].map(() => Array<string>(lebarKolom).fill('')))
  .map((b, i) => [[String(i + 1)], ...b.map((s) => [s])]);

const tgl = (iso?: string) => (iso ? tanggalPanjang(iso) : KOSONG);
const hari = (iso?: string) => {
  if (!iso) return KOSONG;
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString('id-ID', { weekday: 'long' });
};
// Pilihan pada formulir resmi ("layak/tidak layak"): yang tidak berlaku dicoret.
const pilih = (dipilih: string | undefined, a: [string, string], tebal = false): Isi =>
  (dipilih === a[0] ? [{ t: `${a[0]}/`, b: tebal }, { t: a[1], b: tebal, s: true }]
    : dipilih === a[1] ? [{ t: a[0], b: tebal, s: true }, { t: `/${a[1]}`, b: tebal }]
      : [{ t: `${a[0]}/${a[1]}`, b: tebal }]);

const blok = computed<Blok[]>(() => {
  const p = props.permulaan;
  const pt = props.audit.perusahaan.nama;
  const th = Number(props.audit.tahun);
  const md = props.mandays;
  const x = props.persiapan;
  const surat = `nomor ${p.surat_nomor || KOSONG} pada tanggal ${tgl(p.surat_tanggal)}`;
  const skAuditor = String(p.sk_auditor ?? '').trim() || `${p.surat_nomor || KOSONG} pada tanggal ${tgl(p.surat_tanggal)}`;
  const timTabel = tabel([kol('8%', 'No', true), kol('34%', 'Nama'), kol('24%', 'Jabatan'), kol('34%', 'Nomor Registrasi Auditor')],
    props.tim.length
      ? props.tim.map((a, i) => [[String(i + 1)], [a.nama], [a.peran || ''], [a.registrasi || '']])
      : ['Lead Auditor', 'Auditor', 'Auditor'].map((j, i) => [[String(i + 1)], [''], [j], ['']]));
  const kolRisiko = [kol('8%', 'No', true), kol('34%', 'Kegiatan'), kol('43%', 'Risiko'), kol('15%', 'Nilai Risiko', true)];
  const risiko = (rows: Array<Record<string, string>>) => bernomor(rows.map((r) => [r.kegiatan ?? '', r.risiko ?? '', String(r.nilai ?? '')]), 3);
  const tidakLengkap = props.elemen.filter((e) => e.status && e.status !== 'lengkap');
  const simpul = `Berdasarkan hasil evaluasi tersebut di atas, maka pelaksanaan Audit Internal ${SMKP} di ${pt}`;
  // Masukan per sumber: judul kelompok, lalu nomor urut yang dimulai lagi dari 1 di tiap kelompok.
  const masukan: Isi[][] = [];
  let sumber: string | null = null;
  let no = 0;
  for (const m of x.masukan) {
    if (m.sumber !== sumber) {
      sumber = m.sumber;
      no = 0;
      if (m.sumber) masukan.push([[m.sumber]]);
    }
    masukan.push([[`${++no}.`], [m.isi]]);
  }

  return [
    { k: 'judul', tingkat: 0, isi: ['Formulir Berita Acara Hasil Pelaksanaan Tahapan Awal Audit Internal', SMKP] },

    /* ===== I ===== */
    judul(1, 'I.', 'Informasi Pelaksanaan Audit SMKP'),
    judul(2, 'A.', 'Data Perusahaan Auditi'),
    tabelIsian([
      ['Nama Perusahaan Auditi', pt],
      ['Jenis Perizinan', props.audit.perusahaan.izin || ''],
      ['Jenis Komoditas', props.audit.perusahaan.komoditas || ''],
      ['Alamat Perusahaan Head Office', props.audit.perusahaan.alamat || ''],
      ['Alamat Perusahaan Site Office', props.alamatSite],
      ['Periode Audit', props.periode],
    ]),
    judul(3, undefined, 'Data Kinerja Keselamatan Pertambangan pada Periode Audit'),
    tabelIsian(Object.entries(props.kinerja).map(([k, b]) => [b.label, props.kinerjaIsi[k] ?? ''])),
    judul(3, undefined, 'Dasar Faktor Penyesuaian'),
    tabel([kol('8%', 'No', true), kol('62%', 'Kondisi'), kol('15%', 'Ya', true), kol('15%', 'Tidak', true)],
      Object.entries(props.faktor).map(([k, label], i) => [[String(i + 1)], miring(label), [p.faktor?.[k] ? '✓' : ''], [p.faktor?.[k] ? '' : '✓']])),
    judul(2, 'B.', 'Perhitungan Hari Kerja Audit dan Jumlah Auditor'),
    tabelIsian([
      ['Jumlah Pekerja Auditi', p.jumlah_pekerja ? `${p.jumlah_pekerja} Orang` : ''],
      ['Kelas Risiko', p.kelas_risiko || ''],
      ['Mandays', `${angka(md.dasar)} Hari${md.auditor > 1 ? ` (${md.auditor} auditor, ${angka(md.per_auditor)} Hari per auditor)` : ''}`],
      ['Faktor Penyesuaian', md.penyesuaian > 0 ? `${angka(md.penyesuaian)} Hari` : '-'],
      ['Total Mandays', `${angka(md.total)} Hari`],
      ['Alokasi Mandays\nuntuk Tahap I Audit (Maksimal 10% dari Total)', `${angka(md.tahap1)} Hari`],
      ['Alokasi Mandays\nuntuk Tahap II Audit (Minimal 90% dari Total)', `${angka(md.tahap2)} Hari`],
    ]),

    /* ===== II ===== */
    judul(1, 'II.', 'Hasil Permulaan Audit dan Peninjauan Dokumen'),
    judul(2, 'A.', 'Penetapan Tim Audit Tahap I'),
    teks([`Kepala Teknik Tambang ${pt} melalui Surat Pengangkatan Tim Audit ${surat} telah menugaskan Tim Audit untuk Tahap Pertama Audit Internal ${SMKP} (Permulaan Audit, Peninjauan Dokumen, Persiapan Audit Lapangan) di ${pt}, sebagai berikut.`], true),
    timTabel,
    judul(2, 'B.', 'Pelaksanaan Kontak Awal dengan Auditi'),
    teks(['Kontak awal dengan auditi telah dilakukan secara ', ...pilih(p.sifat_kontak, ['formal', 'informal']), ' dengan rincian sebagai berikut:'], true),
    { k: 'rincian', baris: [
      ['Hari / Tanggal', [p.tanggal_kontak ? `${hari(p.tanggal_kontak)}, ${tgl(p.tanggal_kontak)}` : '']],
      ['Media', [p.media_kontak || '']],
      ['Perwakilan Auditi', [p.wakil_auditi || '']],
      ['Jabatan Perwakilan Auditi', [p.jabatan_wakil || '']],
    ] },
    judul(2, 'C.', 'Penentuan Kelayakan Audit'),
    teks([`Penentuan Kelayakan Audit Internal ${SMKP} dilakukan pada hari ${hari(p.tanggal_kelayakan)} tanggal ${tgl(p.tanggal_kelayakan)}, dengan hasil sebagai berikut:`], true),
    tabel([kol('8%', 'No', true), kol('62%', 'Indikator Kelayakan Audit'), kol('30%', 'Hasil Evaluasi')],
      Object.entries(props.kelayakan).map(([k, label], i) => [[String(i + 1)], miring(label), [p.kelayakan?.[k] ?? '']])),
    teks([`Dengan mempertimbangkan ketersediaan faktor-faktor tersebut di atas, maka pelaksanaan Audit Internal ${SMKP} di ${pt} `,
      ...pilih(p.layak === 'layak' ? 'layak' : p.layak === 'tidak' ? 'tidak layak' : undefined, ['layak', 'tidak layak'], true), ' untuk dilaksanakan.']),
    ...(String(p.kesimpulan ?? '').trim() ? [teks([`Catatan: ${String(p.kesimpulan).trim()}`])] : []),
    judul(2, 'D.', 'Penentuan Kecukupan Dokumentasi'),
    teks([`Peninjauan dokumentasi ${SMKP} mencakup dokumen dan rekaman milik ${pt} pada periode audit, dengan memperhatikan ukuran, sifat dan kompleksitas organisasi, serta tujuan dan ruang lingkup audit terhadap 7 (tujuh) elemen ${SMKP} sesuai Lampiran II Keputusan Direktur Jenderal Mineral dan Batubara Nomor 185.K/37.04/DJB/2019.`]),
    teks([`Hasil peninjauan dokumen ${SMKP} ${pt} sebagai berikut:`], true),
    tabel([kol('8%', 'No', true), kol('62%', 'Peninjauan Dokumentasi'), kol('30%', 'Hasil Evaluasi')],
      props.elemen.map((e, i) => [[String(i + 1)], [`Elemen ${e.kode} ${e.nama}`], [e.status ? e.label : '', ...(e.ket ? [{ t: `\n${e.ket}`, i: true }] : [])]])),
    ...(props.rekap.tidak > 0
      ? [teks([`${simpul} `, B('belum dapat dilanjutkan'), ' ke tahap berikutnya hingga Auditi melengkapi dokumen berikut:'], true),
        { k: 'daftar', gaya: '1.', butir: tidakLengkap.map((e) => ({ isi: [`Elemen ${e.kode} ${e.nama}${e.ket ? `: ${e.ket}` : ''}`] })) } as Blok]
      : props.rekap.siap
        ? [teks([`${simpul} `, B('dapat dilanjutkan'), ' ke tahap berikutnya.'])]
        // Belum ditinjau seluruhnya: kedua pilihan formulir resmi tetap dicetak.
        : [teks([simpul]), teks([B('dapat dilanjutkan*)'), ' ke tahap berikutnya.']), teks(['atau']),
          teks([B('belum dapat dilanjutkan*)'), ' ke tahap berikutnya hingga Auditi melengkapi dokumen berikut:'], true),
          { k: 'daftar', gaya: '1.', butir: [1, 2, 3].map(() => ({ isi: [KOSONG] })) } as Blok]),

    /* ===== III ===== */
    judul(1, 'III.', 'Persiapan Audit Lapangan'),
    judul(2, 'A.', 'Penetapan Tim Audit Tahap II'),
    teks([`Kepala Teknik Tambang ${pt} melalui Surat Pengangkatan Tim Audit ${surat} telah menugaskan Tim Audit untuk Tahap Kedua Audit Internal ${SMKP} (Pelaksanaan Rapat Pembukaan, Pengumpulan dan Verifikasi Informasi, Perumusan Temuan Audit, Penyiapan Kesimpulan Audit, Pelaksanaan Rapat Penutupan), sebagai berikut:`], true),
    timTabel,
    teks([`Penugasan tersebut telah mempertimbangkan tujuan, ruang lingkup, kriteria dan perkiraan waktu audit, kompetensi tim audit secara keseluruhan yang diperlukan untuk mencapai tujuan audit, peraturan perundang-undangan yang berlaku, kebutuhan untuk menjamin keindependenan tim audit dari kegiatan yang diaudit dan untuk menghindarkan konflik kepentingan kemampuan anggota tim audit untuk berinteraksi secara efektif dengan auditi dan untuk bekerja bersama dalam tim, dan bahasa yang digunakan dalam audit, dan pemahaman terhadap karakteristik sosial dan budaya tertentu dari auditi. Seluruh auditor internal yang diangkat memiliki komitmen untuk menjaga integritas dan perilaku yang profesional, independen, jujur, dan objektif dalam pelaksanaan tugas, yang dibuktikan dengan ${props.buktiIntegritas}.`]),
    teks([`Auditor yang ditugaskan juga telah memperhatikan persyaratan yang ditetapkan pada Lampiran II Keputusan Direktur Jenderal Mineral dan Batubara nomor 185.K/37.04/DJB/2019 tentang Petunjuk Teknis Pelaksanaan Keselamatan Pertambangan dan Pelaksanaan, Penilaian, dan Pelaporan ${SMKP} dan Surat Edaran Direktur Teknik dan Lingkungan Mineral dan Batubara/Kepala Inspektur Tambang nomor B-7/MB.07/DBT.KP/2022 tanggal 3 Januari 2022 perihal Auditor Internal ${SMKP}.`]),
    teks([`Penetapan tanggung jawab Auditor ditetapkan sesuai Prosedur Audit Internal ${SMKP} dan Surat Pengangkatan Auditor Nomor: ${skAuditor}.`]),
    judul(2, 'B.', 'Penyiapan Rencana Audit'),
    teks([`Rencana Audit Internal ${SMKP} menjadi dasar kesepakatan antara klien audit, tim audit dan auditi terkait dengan pelaksanaan Audit Internal ${SMKP} Tahap II di ${pt}, yang meliputi kegiatan:`], true),
    { k: 'daftar', gaya: '1)', butir: ['Pelaksanaan Rapat Pembukaan', 'Pengumpulan dan Verifikasi Informasi', 'Perumusan Temuan Audit', 'Penyiapan Kesimpulan Audit', 'Pelaksanaan Rapat Penutupan'].map((t) => ({ isi: [t] })) },
    teks([`Rencana Audit Internal ${SMKP} Tahap II mencakup:`], true),
    { k: 'daftar', gaya: '1)', butir: ['Penetapan Tujuan Audit', 'Penetapan Kriteria Audit', 'Penetapan Ruang Lingkup Audit', 'Penetapan Tanggal Pelaksanaan Audit', 'Penetapan Susunan Kegiatan Audit',
      'Pembagian Tugas dan Tanggung Jawab Anggota Tim Audit', 'Penetapan Alokasi Sumberdaya terkait', 'Penetapan Metode dan Sampel Audit',
      'Pengesahan Rencana Audit dari Kepala Teknik Tambang, Penanggung Jawab Operasional (untuk auditi yang merupakan perusahaan jasa pertambangan) dan Ketua Tim Audit'].map((t) => ({ isi: [t] })) },
    teks([`Rencana Audit yang memuat informasi angka nomor 1 (satu) s.d. 9 (sembilan) tersebut di atas ditetapkan secara lengkap pada Formulir Rencana Audit ${pt} nomor ${props.nomorForm.rencana}.`]),
    teks([`Mempertimbangkan bahwa program Audit Internal ${SMKP} wajib didasarkan pada hasil penilaian risiko pada kegiatan operasional dan hasil penilaian kinerja Keselamatan Pertambangan dari Auditi, dan berikut informasi terkait ${pt} yang `, B('wajib dipertimbangkan'),
      ' Auditor dalam penetapan metode dan sampel khususnya sebagai ', { t: 'critical point', i: true }, ' untuk jenis pembuktian primer:']),
    judul(3, undefined, `Data Top Risks aspek Keselamatan Pertambangan: Periode Audit ${th} (Risk of Present)`),
    tabel(kolRisiko, risiko(props.risiko.present)),
    judul(3, undefined, `Data Top Risks aspek Keselamatan Pertambangan: Rencana Kegiatan ${th + 1} (Risk of Future)`),
    tabel(kolRisiko, risiko(props.risiko.future)),
    ...Object.entries(props.tren).flatMap(([k, d]) => [
      judul(3, undefined, `${d.judul} di ${pt} pada Periode Audit ${th}`),
      tabel([kol('8%', 'No', true), kol('92%', d.kolom)], bernomor((x.tren[k] ?? []).map((t) => [t]), 1)),
    ]),
    judul(3, undefined, `Data Unjuk Kerja Peralatan Pertambangan ${pt} pada Periode Audit ${th}`),
    tabel([kol('8%', 'No', true), kol('42%', 'Jenis Peralatan'), kol('25%', 'Physical Availability', true), kol('25%', 'Mechanical Availability', true)],
      bernomor(x.peralatan.map((r) => [r.jenis, r.pa, r.ma]), 3)),
    judul(3, undefined, `Data Capaian Key Leading Indicator ${pt} pada Periode Audit ${th}`),
    tabel([kol('8%', 'No', true), kol('52%', 'Key Leading Indicator'), kol('40%', 'Status Pencapaian hingga Tanggal Pelaksanaan Audit')],
      bernomor(x.kli.map((r) => [r.indikator, r.status]), 2)),
    judul(3, undefined, `Data Audit Internal SMKP ${pt} Tahun ${th - 1}`),
    tabel([kol('8%', 'No', true), kol('52%', `Data Audit Internal ${th - 1}`), kol('40%', 'Keterangan')],
      Object.entries(props.auditLalu).map(([k, label], i) => [[String(i + 1)], [label], [x.audit_lalu[k] ?? '']])),
    judul(3, undefined, `Daftar Masukan Pemangku Kepentingan terkait Audit Internal SMKP berdasarkan Komunikasi dan Konsultasi Risiko yang dilakukan ${pt} Tahun ${th - 1}`),
    teks(['Masukan dari Pemangku Kepentingan yang perlu diperhatikan, antara lain namun tidak terbatas kepada:'], true),
    { k: 'daftar', gaya: '•', lekat: true, butir: [
      ['Perintah, Larangan, Petunjuk dari Inspektur Tambang yang dituliskan di Buku Tambang'],
      [`Masukan dari Kegiatan Pembinaan dari Direktorat Teknik dan Lingkungan Mineral dan Batubara, seperti Verifikasi Audit Internal SMKP ${th - 1}, Verifikasi Penilaian Prestasi Pengelolaan Keselamatan Pertambangan ${th - 1}, dan lainnya`],
      ['Masukan dari Komite Keselamatan Pertambangan'],
      ['Masukan dari pemegang IUP selaku pemberi kerja (bagi pemegang IUJP)'],
      ['Masukan dari Serikat Pekerja'],
      ['Evaluasi terhadap ', { t: 'Trend', i: true }, ' Pengaduan dari Masyarakat'],
    ].map((isi) => ({ isi: isi as Isi })) },
    tabel([kol('8%', 'No', true), kol('92%', 'Masukan dari Pemangku Kepentingan')], masukan.length ? masukan : bernomor([], 1)),
    teks(['Selain mempertimbangkan bahwa hasil penilaian risiko pada kegiatan operasional dan hasil penilaian kinerja Keselamatan Pertambangan dari Auditi, penetapan metode dan sampel juga wajib memperhatikan ',
      { t: 'lesson learned', i: true }, ' dari kinerja Keselamatan Pertambangan Nasional yang disampaikan oleh instansi pembina sektor.']),
    judul(2, 'C.', 'Penyiapan Dokumen Kerja'),
    teks([`Tim Audit Internal ${SMKP} menyiapkan dokumen kerja yang diperlukan untuk rujukan dan untuk merekam pelaksanaan audit internal. Dokumen kerja tersebut mencakup hal-hal sebagai berikut:`], true),
    { k: 'daftar', gaya: '1)', butir: [
      { isi: [{ t: 'Rencana Sampling', i: true }, ` yang dimuat pada Formulir Rencana Audit ${pt} nomor ${props.nomorForm.rencana}`] },
      { isi: ['Kriteria Audit yaitu Lampiran II Keputusan Direktur Jenderal Mineral dan Batubara Nomor 185.K/37.04/DJB/2019'] },
      { isi: ['Perangkat Penunjang Pengambilan Data sesuai kebutuhan audit seperti: Daftar Pertanyaan Wawancara Terstruktur, Daftar Periksa Observasi Objek Audit, Kuesioner Audit'] },
      { isi: ['Formulir Pendokumentasian Hasil Audit, antara lain:'], anak: [
        [`Formulir Kesesuaian Audit (Formulir nomor ${props.nomorForm.kesesuaian})`],
        [`Formulir Ketidaksesuaian dan Tindak Lanjut Ketidaksesuaian Audit (Formulir nomor ${props.nomorForm.ketidaksesuaian})`],
        [`Formulir Rekapitulasi Ketidaksesuaian (Formulir nomor ${props.nomorForm.rekapitulasi})`],
        [`Formulir Rencana Tindak Lanjut Audit (Formulir nomor ${props.nomorForm.tindakLanjut})`],
        [`Formulir Rekomendasi dan Peluang untuk Perbaikan (Formulir nomor ${props.nomorForm.rekomendasi})`],
      ] },
      { isi: [`Formulir Rekaman Rapat nomor ${props.nomorForm.rapat}`] },
    ] },

    /* ===== IV ===== */
    judul(1, 'IV.', 'Penutup'),
    teks([`Berita Acara Hasil Pelaksanaan Tahapan Awal Audit Internal ${SMKP} ini dibuat pada tanggal ${tgl(p.tanggal_ba)}, dan diketahui oleh klien audit, tim audit, serta auditor.`], true),
    { k: 'ttd' },
    { k: 'teks', inden: 0, lekat: true, isi: ['Catatan:'] },
    { k: 'daftar', gaya: '•', inden: 0, butir: [
      { isi: ['Untuk audit perusahaan jasa pertambangan, ditandatangani oleh Ketua Tim Audit, PJO dan KTT'] },
      { isi: ['Untuk audit perusahaan pertambangan, ditandatangani oleh Ketua Tim Audit dan KTT'] },
    ] },
  ];
});

/* ---------- pembagian lembar: ukur blok di wadah tersembunyi ---------- */
const { ukurEl, lembar } = useSusunLembar(blok);

const menyusunWord = ref(false);
async function unduhWord() {
  if (menyusunWord.value) return;
  menyusunWord.value = true;
  try {
    const { unduhWord: susunWord } = await import('../../../smkp/lib/wordCetak');
    await susunWord({
      namaBerkas: `Berita Acara Tahap I SMKP ${props.audit.perusahaan.nama} ${props.audit.tahun}`,
      judul: 'Formulir Berita Acara Hasil Pelaksanaan Tahapan Awal Audit Internal SMKP',
      dok: props.dok,
      blok: blok.value,
      ttd: { '': props.pihak.map((x) => ({ peran: x.peran, nama: x.nama, gambar: props.ttd.gambar[x.kunci] ?? null })) },
    });
  } catch (e) {
    console.error(e);
    window.alert('Dokumen Word gagal disusun. Muat ulang halaman lalu coba lagi.');
  } finally {
    menyusunWord.value = false;
  }
}

// Tautan "Unduh Word" dari halaman lain dapat membuka halaman ini dengan
// ?unduh=word untuk langsung mengunduh, sama seperti Laporan Audit.
onMounted(() => {
  const alamat = new URL(window.location.href);
  if (alamat.searchParams.get('unduh') !== 'word') return;
  alamat.searchParams.delete('unduh');
  router.replace({ url: alamat.pathname + alamat.search + alamat.hash, preserveState: true, preserveScroll: true });
  void unduhWord();
});
</script>

<template>
  <Head title="Berita Acara Tahap I" />
  <CetakLayout judul="Berita Acara Hasil Pelaksanaan Tahapan Awal" :kembali="kembali"
               :peringatan="rekap.siap ? null : `${rekap.belum} elemen belum ditinjau kecukupan dokumentasinya — kesimpulan kelanjutan ke Tahap II belum dapat ditetapkan.`">
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
          <FormTtd v-if="b.k === 'ttd'" blok :pihak="pihak" :ttd="ttd" bagian="berita-acara" />
        </BlokCetak>
      </Lembar>
    </div>

    <p v-if="!lembar" class="text-center text-[12.5px] text-slate-500 py-10">Menyusun halaman…</p>
    <template v-else>
      <Lembar v-for="(isi, n) in lembar" :key="n" :dok="dok" :halaman="n + 1" :dari="lembar.length">
        <BlokCetak v-for="c in isi" :key="`${c.i}-${c.dari ?? 0}`" :blok="blok[c.i]" :dari="c.dari" :sampai="c.sampai">
          <FormTtd v-if="blok[c.i].k === 'ttd'" blok :pihak="pihak" :ttd="ttd" bagian="berita-acara" />
        </BlokCetak>
      </Lembar>
    </template>
  </CetakLayout>
</template>
