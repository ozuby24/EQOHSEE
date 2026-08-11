/**
 * Bentuk data yang dikirim server ke halaman Inertia.
 *
 * Ditulis sekali di sini, bukan diulang di tiap komponen: yang dijaga
 * TypeScript hanyalah bentuk yang benar-benar dideklarasikan, dan bentuk
 * yang ditulis ulang di beberapa berkas akan berbeda satu sama lain
 * tanpa ada yang menegur.
 */

export type Nada = 'toska' | 'biru' | 'kuning' | 'hijau' | 'ungu' | 'merah';

export interface Pengguna {
  id: number;
  nama: string;
  peran: string;
  admin: boolean;
  avatar: string | null;
}

/** Satu butir menu: label, alamat, dan apakah ia sedang dibuka. */
export interface ButirMenu {
  label: string;
  url: string;
  aktif: boolean;
  ikon: string;
  /** true bila tujuannya dirender Inertia — menentukan <Link> atau <a>. */
  inertia: boolean;
}

export interface Modul {
  kunci: string;
  label: string;
  ikon: string;
  url: string;
  aktif: boolean;
  inertia: boolean;
}

export interface KerangkaMenu {
  modul: Modul[];
  label: string;
  grup: Array<{ nama: string; butir: ButirMenu[] }>;
}

export interface Kilat {
  sukses?: string | null;
  galat?: string | null;
}

/** Prop yang dibagikan ke SELURUH halaman Inertia. */
export interface PropBersama {
  pengguna: Pengguna | null;
  menu: KerangkaMenu;
  kilat: Kilat;
  pengumuman: number;
  tema: 'terang' | 'gelap' | null;
  warna: { aksen: string; dasar: string };
  ziggy?: Record<string, string>;
}

/* ══════════════ PTPKKP — penilaian ══════════════ */

export interface Rubrik {
  tingkat: number;
  teks: string;
  warna: string;
}

export interface ItemNilai {
  kode: string;
  nama: string;
  metode: string[];
  maks: number;
  rubrik: Rubrik[];
  target: string | null;
  /** Nilai per entitas; kunci '_' dipakai bila metode ini tidak per entitas. */
  nilai: Record<string, number | null>;
  ket: string;
}

export interface Parameter {
  kode: string;
  nama: string;
  jumlah: number;
  bobot?: number;
  target?: number;
}

export interface MetodeInfo {
  kode: string;
  nama: string;
  labelEntitas: string;
}

/** Ambang kategori kematangan — selalu dari server, tidak pernah ditulis di klien. */
export interface Ambang {
  batas: number;
  label: string;
  warna: string;
}

/** Navigasi dalam-halaman PTPKKP — dari App\Support\TpkkpNav. */
export interface TabPicker {
  label: string;
  url: string;
  ikon: string;
  aktif: boolean;
  inertia: boolean;
}

export interface Picker {
  tabs: TabPicker[];
  tahun: number;
  daftarTahun: number[];
}

/* ══════════════ PTPKKP — rubrik & jadwal ══════════════ */

export interface TingkatRubrik {
  tingkat: number;
  teks: string;
  warna: string;
}

export interface ItemRubrik {
  kode: string;
  nama: string;
  metode: string[];
  maks: number;
  acuan: TingkatRubrik[] | null;
  rubrik: Array<{ metode: string; tingkat: TingkatRubrik[] }>;
  target: Array<{ metode: string; teks: string }>;
}

export interface HalamanRubrik {
  judul: string;
  subjudul: string;
  picker: Picker;
  parameter: Array<{ kode: string; nama: string; jumlah: number; url: string }>;
  paramAktif: string;
  items: ItemRubrik[];
}

export interface BarisJadwal {
  idx: number;
  kegiatan: string;
  keluaran: string;
  mulai: number;
  akhir: number;
  kiri: number;
  lebar: number;
  selesai: boolean;
}

export interface HalamanJadwal {
  judul: string;
  subjudul: string;
  picker: Picker;
  tahun: number;
  tahap: Array<{ nama: string; baris: BarisJadwal[] }>;
  bisaSunting: boolean;
}

/** Satu set data grafik batang: label, nilai, dan warna per batang. */
export interface SeriGrafik {
  label: string[];
  nilai: number[];
  warna: string[];
}

export interface HalamanVisual {
  judul: string;
  subjudul: string;
  picker: Picker;
  indikator: { label: string[]; capaian: number[]; target: number[] };
  parameter: { label: string[]; capaian: number[]; target: number[]; warna: string[] };
  metode: SeriGrafik;
  donat: SeriGrafik;
}

/* ══════════════ PTPKKP — metode & instrumen ══════════════ */

export interface MetodePengukuran {
  kode: string;
  nama: string;
  labelEntitas: string;
  entitas: string[];
  items: number;
  terisi: number;
  kategori: string | null;
  warna: string;
  url: string;
}

export interface HalamanMetode {
  judul: string;
  subjudul: string;
  picker: Picker;
  metode: MetodePengukuran[];
}

export interface HalamanTentang {
  judul: string;
  subjudul: string;
  picker: Picker;
  meta: { judul: string; basis: string };
  ringkas: Array<{ label: string; nilai: string }>;
  ambang: Array<{ label: string; batas: string; warna: string }>;
  indikator: Array<{
    kode: string;
    nama: string;
    bobot: number;
    parameter: Array<{
      kode: string; nama: string; bobot: number; target: number; jumlahItem: number;
    }>;
  }>;
}

/* ══════════════ PTPKKP — matriks, summary, hasil ══════════════ */

export interface ItemMatriks {
  kode: string;
  nama: string;
  metode: string[];
  perMetode: Record<string, number | null>;
  nilai: number | null;
  maks: number;
  capaian: number | null;
  kategori: string | null;
  warna: string;
}

export interface ParamMatriks {
  kode: string;
  nama: string;
  bobot: number;
  target: number | null;
  nilai: number | null;
  maks: number;
  rasio: number | null;
  kategori: string | null;
  warna: string;
  items: ItemMatriks[];
}

export interface IndikatorMatriks {
  kode: string;
  nama: string;
  bobot: number;
  rasio: number | null;
  kategori: string | null;
  warna: string;
  parameter: ParamMatriks[];
}

export interface HalamanMatriks {
  judul: string;
  subjudul: string;
  picker: Picker;
  metode: string[];
  indikator: IndikatorMatriks[];
}

export interface ParamSummary {
  kode: string;
  nama: string;
  bobot: number;
  skor: number | null;
  rasio: number | null;
  target: number | null;
  kategori: string | null;
  warna: string;
  gap: number | null;
}

export interface IndikatorSummary extends Omit<ParamSummary, 'target'> {
  target: number;
  parameter: ParamSummary[];
}

export interface HalamanSummary {
  judul: string;
  subjudul: string;
  picker: Picker;
  indikator: IndikatorSummary[];
  total: {
    skor: number | null;
    rasio: number | null;
    target: number;
    kategori: string | null;
    warna: string;
    gap: number | null;
  };
}

export interface MetodeHasil {
  kode: string;
  nama: string;
  items: number;
  terisi: number;
  maks: number;
  jumlah: number;
  rasio: number | null;
  kategori: string | null;
  warna: string;
}

export interface HalamanHasil {
  judul: string;
  subjudul: string;
  picker: Picker;
  rentang: Array<{ teks: string; kategori: string; warna: string }>;
  total: {
    skor: number | null;
    target: number;
    kategori: string | null;
    warna: string;
  };
  indikator: Array<{
    kode: string; nama: string; bobot: number;
    skor: number | null; rasio: number | null;
    kategori: string | null; warna: string;
  }>;
  metode: MetodeHasil[];
}

/* ══════════════ PTPKKP — beranda ══════════════ */

export interface IndikatorBeranda {
  kode: string;
  nama: string;
  skor: number | null;
  rasio: number | null;
  bobot: number;
  target: number;
  kategori: string | null;
  warna: string;
  selTerisi: number;
  selTotal: number;
}

export interface MetodeRekap {
  kode: string;
  nama: string;
  terisi: number;
  jumlah: number;
  rasio: number | null;
}

export interface TingkatSebaran {
  nama: string;
  warna: string;
  jumlah: number;
}

export interface ItemGap {
  kode: string;
  nama: string;
  nilai: number;
  maks: number;
  kategori: string | null;
  warna: string;
}

export interface HalamanBeranda {
  judul: string;
  subjudul: string;
  picker: Picker;
  identitas: {
    organisasi: string;
    site: string | null;
    komoditas: string | null;
    tahun: number;
  };
  hasil: {
    skor: number | null;
    tingkat: number;
    kategori: string | null;
    target: number;
    selTerisi: number;
    selTotal: number;
    kelengkapan: number;
    indikator: IndikatorBeranda[];
  };
  metode: MetodeRekap[];
  tingkat: TingkatSebaran[];
  belumLengkap: number;
  totalItem: number;
  gaps: ItemGap[];
  radar: { label: string[]; capaian: number[]; target: number[] };
}

/* ══════════════ PTPKKP — rekapitulasi ══════════════ */

export interface ParamRekap {
  kode: string;
  nama: string;
  nilai: number | null;
  maks: number;
  rasio: number | null;
  bobot: number;
  skor: number | null;
  target: number | null;
  kategori: string | null;
}

export interface IndikatorRekap {
  kode: string;
  nama: string;
  bobot: number;
  nilai: number | null;
  target: number;
  kategori: string | null;
  parameter: ParamRekap[];
}

export interface HasilTotal {
  skor: number | null;
  target: number;
  indikator: IndikatorRekap[];
}

export interface ParamPerusahaan {
  kode: string;
  nama: string;
  rerata: number | null;
  jumlah: number;
}

export interface IndikatorPerusahaan {
  kode: string;
  nama: string;
  rerata: number | null;
  parameter: ParamPerusahaan[];
}

export interface ItemLemah {
  kode: string;
  nama: string;
  rerata: number | null;
}

/** Label kategori → warna — dibaca dari server, sama untuk seluruh PTPKKP. */
export interface AmbangLabel {
  label: string;
  warna: string;
}

export interface HalamanRekap {
  judul: string;
  subjudul: string;
  picker: Picker;
  tahun: number;
  hasil: HasilTotal;
  perusahaan: string[];
  entitasAktif: string | null;
  metodePerusahaan: string[];
  rincian: IndikatorPerusahaan[] | null;
  lemah: ItemLemah[] | null;
  ambang: AmbangLabel[];
}

export interface HalamanPenilaian {
  judul: string;
  subjudul: string;
  picker: Picker;
  ambang: Ambang[];
  tahun: number;
  metode: MetodeInfo[];
  metodeAktif: string;
  parameter: Parameter[];
  paramAktif: string | null;
  entitas: string[];
  items: ItemNilai[];
  bisaSunting: boolean;
  paramBobot: number;
  paramTarget: number;
}

export interface IsianProfil {
  judul: string;
  organisasi: string;
  site: string;
  komoditas: string;
  ktt: string;
  basis: string;
  status: string;
}

export interface RosterMetode {
  kode: string;
  label: string;
  entitas: string[];
}

export interface HalamanProfil {
  judul: string;
  subjudul: string;
  picker: Picker;
  tahun: number;
  isian: IsianProfil;
  roster: RosterMetode[];
  bisaSunting: boolean;
}

export interface SaranProgram {
  kode: string;
  nama: string;
  gap: number;
}

export interface BarisProgram {
  id: string | null;
  param: string;
  opsi: string;
  durasi: string;
  sasaran: string;
  target: string;
  status: string;
  progress: number;
}

/** Salinan lokal satu baris selama disunting, sebelum dikirim ke server. */
export interface BarisSunting {
  status: string;
  progress: number;
}

export interface HalamanProgram {
  judul: string;
  subjudul: string;
  picker: Picker;
  tahun: number;
  saran: SaranProgram[];
  program: BarisProgram[];
  statusPilihan: string[];
  bisaSunting: boolean;
}

export interface Strata {
  nama: string;
  N: number;
}

export interface BarisAlokasi {
  nama: string;
  N: number;
  nh: number;
}

export interface Alokasi {
  N: number;
  n: number;
  total: number;
  baris: BarisAlokasi[];
}

export interface HalamanSampling {
  judul: string;
  subjudul: string;
  picker: Picker;
  tahun: number;
  strata: Strata[];
  e: number;
  eMin: number;
  eMaks: number;
  alokasi: Alokasi;
  bisaSunting: boolean;
}

export interface BarisSampel {
  perusahaan: string;
  mgm: number | null;
  emp: number | null;
  jumlah: number | null;
}

export interface TotalSampel {
  mgm: number | null;
  emp: number | null;
  jumlah: number | null;
}

export interface BlokSampel {
  kode: string;
  baris: BarisSampel[];
  /** Penjumlahan baris terbulat — yang benar-benar tampak di tabel. */
  total: TotalSampel;
  /** Alokasi proporsional instrumen sebelum dibulatkan; null bila tak ada. */
  acuan: TotalSampel | null;
}

export interface HalamanSampel {
  judul: string;
  subjudul: string;
  picker: Picker;
  populasi: { management: number; employee: number; total: number };
  metode: BlokSampel[];
  metodeAwal: string;
}

export interface MetodeRoster {
  kode: string;
  nama: string;
  labelEntitas: string;
  punyaEntitas: boolean;
  entitas: string[];
  bawaan: string[];
}

export interface HalamanRoster {
  judul: string;
  subjudul: string;
  picker: Picker;
  tahun: number;
  metode: MetodeRoster[];
  bisaSunting: boolean;
}
