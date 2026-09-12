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

  /** Null bagi pengguna lintas perusahaan — bukan berarti datanya hilang. */
  perusahaan: string | null;

  /**
   * Perusahaan yang boleh dipilih. KOSONG bagi pengguna biasa.
   *
   * Kosong itu yang membedakan pemilih dari label: daftar berisi satu
   * akan menggambar tombol yang dapat dibuka dan tidak dapat mengubah
   * apa pun.
   */
  perusahaanPilihan?: { id: number; nama: string }[];

  /** Perusahaan yang sedang dilihat; null berarti seluruhnya. */
  perusahaanDilihat?: number | null;
}

/** Satu butir menu: label, alamat, dan apakah ia sedang dibuka. */
export interface ButirMenu {
  label: string;
  url: string;
  aktif: boolean;
  ikon: string;
  /** true bila tujuannya dirender Inertia — menentukan <Link> atau <a>. */
  inertia: boolean;
  /** Angka penanda, mis. pesan belum dibaca; null bila tidak ada. */
  lencana: number | null;
}

export interface Modul {
  kunci: string;
  label: string;
  ikon: string;
  url: string;
  aktif: boolean;
  inertia: boolean;
  /** Jumlah penanda seluruh butir modul ini. */
  lencana: number | null;
}

export interface KerangkaMenu {
  modul: Modul[];
  /** Kunci modul yang sedang aktif — dipakai menandai dan memberi tema. */
  kunci: string;
  label: string;
  grup: Array<{ nama: string; butir: ButirMenu[] }>;

  /**
   * Nama tema modul ini, bila ia menyatakan satu.
   *
   * Dikirim sebagai NAMA, bukan sebagai daftar warna: yang menentukan
   * rupa sebuah tema adalah lembar gayanya, dan mengirim warnanya lewat
   * prop berarti dua tempat yang harus sama selamanya.
   */
  tema?: string | null;

  /** Semboyan modul, untuk tagline di kop halaman. */
  semboyan?: string | null;

  /** Kutipan penutup halaman. */
  kutipan?: string | null;

  /** Alamat akar modul, untuk remah roti di kop. */
  akar?: string | null;
}

export interface Kilat {
  sukses?: string | null;
  galat?: string | null;
  /** Jawaban asisten AI — berparagraf, jadi dibawa terpisah dari `sukses`. */
  aiJawaban?: string | null;
}

/** Prop yang dibagikan ke SELURUH halaman Inertia. */
export interface PropBersama {
  /** Inertia memperbolehkan prop halaman tambahan di luar prop global. */
  [key: string]: unknown;
  pengguna: Pengguna | null;
  menu: KerangkaMenu;
  kilat: Kilat;
  status?: string | null;
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

/**
 * Tingkat rubrik 1–5.
 *
 * Berbeda dari `Ambang`, yang memetakan RASIO capaian. Kelimanya bernama
 * sama dan karena itu mudah tertukar — tetapi skalanya berbeda, dan
 * menampilkan yang satu sebagai yang lain membuat penilai yang mengisi 3
 * membaca "Reaktif".
 */
export interface Tingkatan {
  nomor: number;
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
  tingkat: string | null;
  rerata: number | null;
  warnaTingkat: string;
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
  tingkat: string | null;
  rerata: number | null;
  warnaTingkat: string;
}

export interface IndikatorMatriks {
  kode: string;
  nama: string;
  bobot: number;
  rasio: number | null;
  kategori: string | null;
  warna: string;
  parameter: ParamMatriks[];
  tingkat: string | null;
  rerata: number | null;
  warnaTingkat: string;
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
  tingkat: string | null;
  rerata: number | null;
  warnaTingkat: string;
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

    /* Tingkat rubrik 1–5 dari skor yang benar-benar diisi penilai —
       berbeda dari `kategori`, yang merupakan rasio capaian terhadap
       nilai maksimum. Keduanya kerap berselisih, dan itu bukan
       kesalahan. */
    tingkat: string | null;
    rerata: number | null;
    warnaTingkat: string;
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
  tingkatan: Tingkatan[];
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

export interface RingkasData {
  label: string;
  nilai: string;
}

export interface HalamanData {
  judul: string;
  subjudul: string;
  picker: Picker;
  tahun: number;
  ringkas: RingkasData[];
  urlEkspor: string;
  kunciDikenal: string[];
  bisaSunting: boolean;
}

export interface ParamKuesioner {
  kode: string;
  nama: string;
  rerata: number | null;
  pct: number;
}

export interface RingkasKuesioner {
  kunci: string;
  label: string;
  jumlah: number;
  rerata: number | null;
  url: string;
  params: ParamKuesioner[];
}

export interface Responden {
  id: number;
  kategori: string;
  kategoriLabel: string;
  nrp: string | null;
  jabatan: string | null;
  dept: string | null;
  jumlahJawaban: number;
  waktu: string | null;
}

export interface PerusahaanRingkas {
  id: number;
  nama: string;
}

export interface HalamanKuesioner {
  judul: string;
  subjudul: string;
  picker: Picker;
  /** null bila belum ada satu pun perusahaan terdaftar. */
  perusahaan: PerusahaanRingkas | null;
  daftarPerusahaan: PerusahaanRingkas[];
  urlPublik: string | null;
  ringkas: RingkasKuesioner[];
  responden: Responden[];
  bisaTarik: boolean;

  /* Respons mitra kerja — analisa saja, di luar skor penilaian. */
  mitra: Array<{ perusahaan: string; jumlah: number; rerata: number | null }>;
}

/* ══════════════ Pengujian (metode PJ) ══════════════ */

export interface PesertaUji {
  id: number;
  nama: string;
  nrp: string | null;
  jabatan: string | null;
  dept: string | null;
  perusahaan: string | null;
  benar: number;
  total: number;
  tingkat: number;
  label: string | null;
  durasi: number;
  /** Berapa kali peserta meninggalkan layar saat mengerjakan. */
  pindahLayar: number;
  waktu: string | null;
}

export interface RingkasUji {
  peserta: number;
  /** Tingkat yang masuk penilaian — rerata tingkat peserta, dibulatkan. */
  tingkat: number | null;
  rerataTingkat: number | null;
  rerataPct: number | null;
  /** tingkat 1–5 → jumlah peserta */
  sebaran: Record<string, number>;
  pindahLayar: number;
}

export interface HalamanPengujian {
  judul: string;
  subjudul: string;
  picker: Picker;
  bank: number;
  jumlahSoal: number;
  detikPerSoal: number;
  pita: Array<{ batas: number; tingkat: number; nama: string }>;
  /** Palet tingkat 1–5, datang dari server agar tidak ada salinan palet. */
  warnaTingkat: string[];
  namaTingkat: string[];
  perusahaan: PerusahaanRingkas | null;
  daftarPerusahaan: PerusahaanRingkas[];
  urlPublik: string | null;
  ringkas: RingkasUji;
  peserta: PesertaUji[];
  skorKini: { kode: string; nama: string; nilai: number | null; ket: string | null } | null;
  bisaTerapkan: boolean;
}

/* ══════════════ Personalia ══════════════ */

export interface MedanIsian {
  nama: string;
  label: string;
  tipe: string;
  wajib: boolean;
}

export interface PerusahaanSingkat {
  nama: string;
  jenis: string;
  lokasi: string | null;
  logo: string | null;
}

export interface HalamanProfilDiri {
  judul: string;
  subjudul: string;
  medan: MedanIsian[];
  isian: Record<string, string>;
  avatar: string | null;
  inisial: string;
  perusahaan: PerusahaanSingkat | null;
  urlPerusahaan: string;
}

export interface MedanPerusahaan {
  nama: string;
  label: string;
  wajib: boolean;
  lebar: boolean;
  /** Identitas perusahaan — hanya administrator yang boleh mengubahnya. */
  khususAdmin: boolean;
}

export interface PilihanPerusahaan {
  id: number;
  nama: string;
}

export interface WarnaLogo {
  nama: string;
  hex: string | null;
}

export interface HalamanPerusahaan {
  judul: string;
  subjudul: string;
  medan: MedanPerusahaan[];
  ada: boolean;
  nama: string | null;
  isian: Record<string, string> | null;
  logo: string | null;
  /** Logo milik perusahaan ini sendiri — hanya itu yang boleh dihapus. */
  logoSendiri: boolean;
  /** Namanya 'palet', bukan 'warna': lihat catatan di PersonaliaController. */
  palet: WarnaLogo[];
  bisaSunting: boolean;
  /** Administrator memilih perusahaan mana yang dibuka dan boleh menambah. */
  admin: boolean;
  aktif: number | null;
  daftar: PilihanPerusahaan[];
}

export interface OrangDirektori {
  id: number;
  nama: string;
  inisial: string;
  jabatan: string | null;
  departemen: string | null;
  email: string | null;
  telepon: string | null;
  avatar: string | null;
  perusahaan: string | null;
  perusahaanId: number | null;
}

export interface TautanHalaman {
  label: string;
  url: string | null;
  aktif: boolean;
}

export interface HalamanDirektori {
  judul: string;
  subjudul: string;
  cari: string;
  orang: OrangDirektori[];
  halaman: { kini: number; akhir: number; total: number; tautan: TautanHalaman[] };
  /** Hanya administrator menerima daftarnya; bagi yang lain kosong. */
  admin: boolean;
  daftar: PilihanPerusahaan[];
}

/* ══════════════ Bantuan ══════════════ */

export interface PesanBantuan {
  id: number;
  /** pengguna | asisten | admin | sistem — menentukan sisi dan warna gelembung. */
  peran: string;
  isi: string;
  nama: string | null;
  waktu: string | null;
}

export interface HalamanBantuan {
  judul: string;
  subjudul: string;
  /** false bila kunci AI belum dipasang; kotaknya mengatakannya terus terang. */
  aiAktif: boolean;
  pesan: PesanBantuan[];
  status: string;
  admin: boolean;
}

export interface UtasBantuan {
  id: number;
  nama: string;
  perusahaan: string | null;
  status: string;
  terakhir: string | null;
  aktif: boolean;
}

export interface HalamanBantuanMasuk {
  judul: string;
  subjudul: string;
  utas: UtasBantuan[];
  terpilih: number | null;
  pesan: PesanBantuan[];
  status: string | null;
}

/* ══════════════ Pesan (chat langsung + grup perusahaan) ══════════════ */

export interface PercakapanRingkas {
  id: number;
  /** langsung | grup — menentukan apakah nama diambil dari lawan bicara atau perusahaan. */
  jenis: string;
  nama: string;
  avatar: string | null;
  terakhir: string | null;
  belumDibaca: number;
  aktif: boolean;
}

export interface PesanChat {
  id: number;
  isi: string;
  nama: string | null;
  avatar: string | null;
  waktu: string | null;
  /** Menentukan sisi gelembung — dibandingkan terhadap pemirsa, bukan dari kolom peran. */
  milikSaya: boolean;
}

export interface HalamanPesan {
  judul: string;
  subjudul: string;
  percakapan: PercakapanRingkas[];
  terpilih: number | null;
  pesan: PesanChat[];
  penggunaId: number;
}

/* ══════════════ Autentikasi ══════════════ */

export interface HalamanVerifikasi {
  judul: string;
  subjudul: string;
  email: string;
  /** Detik tersisa sebelum boleh minta kode lagi; 0 bila boleh. */
  jeda: number;
  /** true bila kode terkunci karena terlalu banyak percobaan salah. */
  hangus: boolean;
  berlaku: number;
  /** false bila pengantar surelnya 'log' — kodenya tidak dikirim ke mana pun. */
  suratAktif: boolean;
}

/* ══════════════ Inspeksi ══════════════ */

export interface BarisInspeksi {
  id: number; kode: string; judul: string; status: string;
  tanggal: string | null; lokasi: string | null;
  template: string | null; perusahaan: string | null;
  jumlahItem: number; inspektur: string[]; url: string;
}

export interface HalamanDaftarInspeksi {
  judul: string; subjudul: string;
  saring: { status: string | null; template: string | null };
  opsi: { status: string[]; template: Array<{ id: number; nama: string }> };
  inspeksi: BarisInspeksi[];
  halaman: { kini: number; akhir: number; total: number; tautan: TautanHalaman[] };
  tautan: { buat: string };
}

export interface HalamanFormInspeksi {
  judul: string; subjudul: string;
  awal: Record<string, string>;
  sunting: boolean;
  opsi: {
    perusahaan: PilihanPerusahaan[];
    template: Array<{ id: number; nama: string; jumlahItem: number }>;
  };
  tautan: { simpan: string; batal: string };
}

export interface ItemInspeksi {
  id: number;
  kelompok: string | null; uraian: string; acuan: string | null;
  kondisi: string | null; risiko: string | null;
  temuan: string | null; tindakan: string | null;
  foto: string[];
  /** Kode hazard bila temuan ini sudah dinaikkan; null bila belum. */
  hazard: string | null;
  urlHazard: string | null;
  urlAngkat: string; urlHapus: string;
}

export interface HalamanDetailInspeksi {
  judul: string; subjudul: string;
  i: {
    id: number; kode: string; judul: string; status: string;
    tanggal: string | null; lokasi: string | null; catatan: string | null;
    template: string | null; perusahaan: string | null; pembuat: string | null;
  };
  inspektur: Array<{ id: number; nama: string; jabatan: string | null; peran: string }>;
  item: ItemInspeksi[];
  opsi: {
    kondisi: string[]; risiko: string[]; status: string[]; peran: string[];
    kandidat: Array<{ id: number; nama: string; jabatan: string | null }>;
  };
  tautan: {
    simpanItem: string; tambahItem: string; tambahPetugas: string;
    ubah: string; cetak: string; kembali: string;
  };
}

export interface HalamanKpiInspeksi {
  judul: string; subjudul: string;
  bulan: string | null; bulanAktif: number; total: number;
  temuan: { total: number; sesuai: number; tidak: number; naik: number };
  opsiBulan: Array<{ nilai: string; label: string }>;
  golongan: BarisGolongan[];
  petugas: BarisPelapor[];
}

export interface HalamanJenisInspeksi {
  judul: string; subjudul: string;
  jenis: Array<{
    id: number; nama: string; keterangan: string | null; aktif: boolean;
    jumlahItem: number; dipakai: number;
    urlUbah: string; urlSalin: string; urlHapus: string;
  }>;
  tautan: { buat: string };
}

export interface HalamanJenisFormInspeksi {
  judul: string; subjudul: string;
  /** false sebelum jenisnya tersimpan — parameter belum bisa ditambahkan. */
  tersimpan: boolean;
  awal: { nama: string; keterangan: string; is_active: boolean };
  item: Array<{
    id: number; kelompok: string | null; uraian: string;
    acuan: string | null; risiko: string | null; urlHapus: string;
  }>;
  opsi: { risiko: string[] };
  tautan: { simpan: string; tambahItem: string | null; batal: string };
}

/* ══════════════ Hazard Report ══════════════ */

export interface LaporanBahaya {
  id: number;
  kode: string;
  risiko: string;
  /** Warna dari server — satu sumber dengan cetakan dan ekspor. */
  warnaRisiko: string;
  status: string;
  warnaStatus: string;
  kategori: string | null;
  deskripsi: string;
  lokasi: string | null;
  pelapor: string | null;
  tanggal: string | null;
  /** Perusahaan yang dituju, atau nama terlapor bila belum ada perusahaan. */
  tujuan: string | null;
  /** Terisi hanya bila terlapor dan perusahaan sama-sama ada. */
  terlapor: string | null;
  foto: string | null;
  url: string;
}

export interface SaringanBahaya {
  q: string;
  bulan: string | null;
  kategori: string | null;
  risiko: string | null;
  status: string | null;
  perusahaan: string | null;
}

export interface OpsiBahaya {
  risiko: string[];
  status: string[];
  kategori: string[];
  bulan: Array<{ nilai: string; label: string }>;
  perusahaan: PilihanPerusahaan[];
}

export interface BarisGolongan {
  nama: string;
  orang: number;
  target: number;
  aktual: number;
  tercapai: number;
  /** Capaian dalam persen — dihitung server; target nol menghasilkan 0. */
  pct: number;
}

export interface BatangTren {
  label: string;
  nilai: number;
  /** Nilai tertinggi sepanjang tren, untuk menskalakan tinggi batang. */
  maks: number;
}

export interface SebaranBahaya {
  judul: string;
  maks: number;
  baris: Array<{ label: string; nilai: number }>;
}

export interface BarisPelapor {
  nama: string;
  jabatan: string | null;
  gol: string;
  target: number;
  aktual: number;
  pct: number;
}

export interface HalamanAnalitikBahaya {
  judul: string;
  subjudul: string;
  bulan: string | null;
  bulanAktif: number;
  total: number;
  opsiBulan: Array<{ nilai: string; label: string }>;
  golongan: BarisGolongan[];
  tren: BatangTren[];
  sebaran: SebaranBahaya[];
  pelapor: BarisPelapor[];
}

export interface PengingatPerusahaan {
  id: number;
  nama: string;
  pic: { nama: string | null; email: string | null; telepon: string | null };
  jumlah: number;
  tinggi: number;
  lama: number;
  /** Teks pesan lengkap — disusun server, ditampilkan apa adanya. */
  pesan: string;
  wa: string;
  /** false bila perusahaan belum punya nomor; tautannya membuka pilih grup. */
  punyaNomor: boolean;
  mail: string | null;
  urlLihat: string;
  urlEdit: string;
}

export interface HalamanPengingat {
  judul: string;
  subjudul: string;
  perusahaan: PengingatPerusahaan[];
  urlPerusahaan: string;
}

export interface OrangManpower {
  id: string;
  nama: string;
  nrp: string | null;
  dept: string | null;
  jabatan: string | null;
  perusahaan: string | null;
}

export interface HalamanBuatBahaya {
  judul: string;
  subjudul: string;
  awal: Record<string, string>;
  opsi: {
    risiko: string[]; kategori: string[]; lokasi: string[]; hirarki: string[];
    unsafeAction: string[]; unsafeCondition: string[];
    perusahaan: PilihanPerusahaan[];
  };
  manpower: OrangManpower[];
  tautan: { simpan: string; batal: string };
}

export interface HalamanDetailBahaya {
  judul: string;
  subjudul: string;
  r: {
    id: number; kode: string;
    risiko: string; warnaRisiko: string;
    status: string; warnaStatus: string;
    kategori: string | null; deskripsi: string;
    rekomendasi: string | null; hirarki: string | null;
    lokasi: string | null; tanggal: string | null; waktu: string | null;
    pelapor: {
      nama: string | null; nrp: string | null; jabatan: string | null;
      departemen: string | null; perusahaan: string | null;
    };
    tujuan: string | null; terlapor: string | null;
    /** Selalu daftar — data lama berupa teks tunggal diseragamkan accessor. */
    unsafeAction: string[];
    unsafeCondition: string[];
    foto: string[];
    fotoTindakLanjut: string[];
    catatanPenutupan: string | null;
    penutup: string | null;
    ditutup: string | null;
  };
  opsi: { status: string[] };
  admin: boolean;
  tautan: { kembali: string; tindak: string; hapus: string };
}

export interface HalamanEvaluasiBahaya {
  judul: string;
  subjudul: string;
  saring: { bulan: string | null; perusahaan: string | null };
  opsi: {
    bulan: Array<{ nilai: string; label: string }>;
    perusahaan: PilihanPerusahaan[];
  };
  ringkas: {
    hazard: number; inspeksi: number; itemDiperiksa: number; temuanInspeksi: number;
    totalTemuan: number; belumTutup: number; risikoTinggi: number; naikJadiHazard: number;
  };
  rerataHari: number | null;
  sebaran: SebaranBahaya[];
  perPerusahaan: Array<{ nama: string; total: number; tutup: number; tinggi: number }>;
  /** maks dibawa per batang agar skalanya sama untuk hazard dan inspeksi. */
  tren: Array<{ label: string; hazard: number; inspeksi: number; maks: number }>;
}

export interface HalamanMonitorBahaya {
  judul: string;
  subjudul: string;
  stat: { total: number; open: number; proses: number; closed: number; tinggi: number };
  saring: SaringanBahaya;
  adaSaringan: boolean;
  opsi: OpsiBahaya;
  laporan: LaporanBahaya[];
  halaman: { kini: number; akhir: number; total: number; tautan: TautanHalaman[] };
  tautan: { buat: string; csv: string; cetak: string; wa: string; pengingat: string };
}

/* ══════════════ Gudang & Penyimpanan ══════════════ */

export interface BarisMutasiGudang {
  id: number;
  jenis: 'masuk' | 'keluar' | 'rusak' | 'opname' | string;
  nomor: string | null;
  tanggal: string | null;
  pihak: string | null;
  jumlah: number;
  /** Hanya terisi untuk opname — ia menetapkan saldo, bukan menambahnya. */
  stokFisik: number | null;
  barang: string | null;
  satuan: string | null;
}

export interface StatusStok {
  kode: 'aman' | 'menipis' | 'habis' | string;
  nama: string;
  nada: string;
}

export interface BarisBarangGudang {
  id: number;
  kode: string;
  nama: string;
  kategori: string;
  namaKategori: string;
  nadaKategori: string;
  satuan: string;
  stok: number;
  stokMin: number;
  lokasi: string | null;
  partNumber: string | null;
  kelasB3: string | null;
  namaKelas: string | null;
  msds: string | null;
  status: StatusStok;
  urlUbah: string;
  urlHapus: string;
}

export interface BarisKedaluwarsa {
  nama: string;
  batch: string | null;
  tanggal: string;
  /** Sisa hari; negatif berarti sudah lewat. */
  sisa: number;
}

export interface PelanggaranSimpan {
  a: string;
  b: string;
  alasan: string;
  lokasi?: string;
}

export interface RingkasGudang {
  jumlah: number;
  habis: number;
  menipis: number;
  aman: number;
  kategori: Record<string, number>;
  tanpa_msds: number;
}

export interface Pilihan {
  nilai: string;
  label: string;
}

export interface HalamanGudang {
  judul: string; subjudul: string;
  r: RingkasGudang;
  kategori: Array<{ kode: string; nama: string; jumlah: number; url: string }>;
  kritis: BarisBarangGudang[];
  kedaluwarsa: BarisKedaluwarsa[];
  ambang: number;
  langgar: PelanggaranSimpan[];
  terakhir: BarisMutasiGudang[];
  tautan: { barang: string; menipis: string; mutasi: string };
}

export interface HalamanBarangGudang {
  judul: string; subjudul: string;
  barang: BarisBarangGudang[];
  f: { cari: string; kategori: string; status: string; lokasi: string };
  opsi: { kategori: Pilihan[]; status: Pilihan[]; lokasi: Pilihan[] };
  bolehUbah: boolean;
  tautan: { baru: string; daftar: string };
}

export interface HalamanFormBarangGudang {
  judul: string; subjudul: string;
  tersimpan: boolean;
  nama: string;
  awal: Record<string, string | boolean>;
  msds: string | null;
  opsi: { kategori: Pilihan[]; kelasB3: Pilihan[]; wujud: string[]; lokasi: Pilihan[] };
  tautan: { simpan: string; batal: string };
}

export interface HalamanMutasiGudang {
  judul: string; subjudul: string;
  mutasi: BarisMutasiGudang[];
  halaman: { kini: number; akhir: number; total: number; tautan: TautanHalaman[] };
  barang: Array<{ id: number; nama: string; satuan: string; stok: number }>;
  f: { jenis: string; barang: string; dari: string; sampai: string };
  opsi: { jenis: Pilihan[]; jenisSaring: Pilihan[] };
  hariIni: string;
  bolehCatat: boolean;
  tautan: { simpan: string; daftar: string };
}

export interface HalamanOpnameGudang {
  judul: string; subjudul: string;
  barang: Array<{
    id: number; kode: string; nama: string; satuan: string;
    lokasi: string | null; buku: number;
  }>;
  lalu: BarisMutasiGudang[];
  hariIni: string;
  bolehCatat: boolean;
  tautan: { simpan: string };
}

export interface HalamanLokasiGudang {
  judul: string; subjudul: string;
  lokasi: Array<{
    id: number; kode: string; nama: string; jenis: string;
    letak: string | null; pj: string | null; jumlah: number;
    syarat: Array<{ kunci: string; label: string; ada: boolean }>;
    langgar: PelanggaranSimpan[];
    urlUbah: string;
  }>;
  opsi: { jenis: Pilihan[]; syarat: Array<{ kunci: string; label: string }> };
  bolehUbah: boolean;
  tautan: { simpan: string };
}

export interface HalamanB3Gudang {
  judul: string; subjudul: string;
  matriks: Array<{
    kelas: string; nama: string;
    sel: Array<{ sama: boolean; alasan: string | null }>;
  }>;
  judulKolom: string[];
  b3: Array<{
    id: number; kode: string; nama: string; unNumber: string | null;
    kelas: string | null; namaKelas: string | null; caraSimpan: string | null;
    wujud: string | null; lokasi: string | null;
    stok: number; satuan: string; msds: string | null;
  }>;
  langgar: PelanggaranSimpan[];
  kedaluwarsa: BarisKedaluwarsa[];
  tautan: { barang: string };
}

export interface HalamanLaporanGudang {
  judul: string; subjudul: string;
  baris: Array<{
    nama: string; kode: string; satuan: string;
    namaKategori: string; nadaKategori: string;
    awal: number; masuk: number; keluar: number;
    penyesuaian: number; akhir: number; opname: boolean;
  }>;
  dari: string; sampai: string;
  label: { dari: string | null; sampai: string | null };
  r: RingkasGudang;
  tautan: { laporan: string };
}

/* ══════════════ ISO & Dokumen ══════════════ */

export interface BarisDokumen {
  id: number;
  kode: string;
  judul: string;
  jenis: string;
  status: string;
  warnaStatus: string;
  revisi: number;
  labelRevisi: string;
  departemen: string | null;
  tanggalTinjau: string | null;
  perluTinjau: boolean;
  segeraTinjau: boolean;
  adaBerkas: boolean;
  url: string;
}

export interface HalamanRegisterDokumen {
  judul: string; subjudul: string;
  dokumen: BarisDokumen[];
  halaman: { kini: number; akhir: number; total: number; tautan: TautanHalaman[] };
  f: { q: string; jenis: string | null; status: string | null; departemen: string | null; tinjau: string | null };
  opsi: {
    jenis: string[];
    status: string[];
    tinjau: Pilihan[];
    departemen: string[];
  };
  stat: { total: number; berlaku: number; draft: number; lewat: number };
  tautan: { daftar: string; buat: string; piramida: string; daftarInduk: string };
}

export interface HalamanPiramidaDokumen {
  judul: string; subjudul: string;
  tingkat: Array<{
    jenis: string; urutan: number; total: number;
    berlaku: number; draft: number; ket: string; url: string;
  }>;
  total: number;
}

export interface HalamanDetailDokumen {
  judul: string; subjudul: string;
  d: BarisDokumen & {
    ringkasan: string | null;
    perusahaan: string | null;
    klasifikasi: string | null;
    disetujui: string | null;
    acuan: string | null;
    tanggalTerbit: string | null;
    tanggalBerlaku: string | null;
    revisiBerikut: string;
  };
  riwayat: Array<{
    id: number; label: string; tanggal: string | null;
    oleh: string | null; ringkasan: string | null;
  }>;
  klausul: Array<{ kode: string; nama: string; warna: string; url: string; butir: string[] }>;
  bolehHapus: boolean;
  tautan: { ubah: string; unduh: string | null; revisi: string; hapus: string; daftar: string };
}

export interface ButirIso {
  no: string;
  judul: string;
}

export interface HalamanFormDokumen {
  judul: string; subjudul: string;
  tersimpan: boolean;
  awal: Record<string, string>;
  /** Klausul tercentang per kode standar; objek kosong bila dokumen baru. */
  isoAwal: Record<string, string[]>;
  adaBerkas: boolean;
  opsi: {
    jenis: string[]; status: string[]; klasifikasi: string[];
    perusahaan: Pilihan[]; prosedur: Pilihan[];
  };
  standar: Array<{ kode: string; nama: string; judul: string; warna: string; butir: ButirIso[] }>;
  tautan: { simpan: string; batal: string };
}

export interface HalamanIso {
  judul: string; subjudul: string;
  standar: Array<{
    kode: string; nama: string; judul: string; ket: string;
    aspek: string | null; warna: string;
    butir: number; tercakup: number; celah: number; rasio: number;
    url: string;
  }>;
  catatan: string;
  dokumen: number;
  dipetakan: number;
}

export interface HalamanDetailIso {
  judul: string; subjudul: string;
  kode: string;
  warna: string;
  cakupan: { butir: number; tercakup: number; celah: number; rasio: number };
  bab: Array<{
    nomor: string; judul: string;
    klausul: Array<{
      no: string; judul: string;
      dokumen: Array<{ kode: string; judul: string; url: string }>;
    }>;
  }>;
  tautan: { cetak: string; daftar: string };
}

/* ══════════════ Admin ══════════════ */

export interface HalamanDaftarPengguna {
  judul: string; subjudul: string;
  /** Namanya 'daftar', bukan 'pengguna': lihat catatan di UserController. */
  daftar: Array<{
    id: number; nama: string; email: string; inisial: string;
    admin: boolean; lmsRole: string | null; auditRole: string | null;
    ohseRole: string | null;
    jabatan: string | null; departemen: string | null;
    aktif: boolean; perusahaan: string | null;
    /** true untuk akun yang sedang dipakai — tombol hapusnya tidak digambar. */
    diri: boolean;
    urlUbah: string; urlHapus: string;
  }>;
  halaman: { kini: number; akhir: number; total: number; tautan: TautanHalaman[] };
  q: string;
  tautan: { daftar: string; buat: string };
}

export interface HalamanFormPengguna {
  judul: string; subjudul: string;
  tersimpan: boolean;
  awal: Record<string, string | boolean>;
  opsi: {
    lms: Pilihan[]; audit: Pilihan[]; ohse: Pilihan[]; perusahaan: Pilihan[];
    jabatan: string[]; departemen: string[];
  };
  tautan: { simpan: string; batal: string };
}

export interface HalamanDaftarPerusahaan {
  judul: string; subjudul: string;
  perusahaan: Array<{
    id: number; nama: string; kode: string | null; inisial: string;
    logo: string | null; risiko: string;
    izin: string | null; komoditas: string | null; lokasi: string | null;
    ktt: string | null; pjo: string | null;
    pekerja: number; pengguna: number;
    urlUbah: string; urlHapus: string; urlTpkkp: string;
  }>;
  halaman: { kini: number; akhir: number; total: number; tautan: TautanHalaman[] };
  q: string;
  tautan: { daftar: string; buat: string };
}

export interface HalamanFormPerusahaan {
  judul: string; subjudul: string;
  tersimpan: boolean;
  awal: Record<string, string>;
  logo: string | null;
  opsi: { induk: Pilihan[]; risiko: string[] };
  contoh: { prefiks: string; divisi: string; departemen: string };
  tautan: { simpan: string; batal: string };
}

export interface HalamanSistem {
  judul: string; subjudul: string;
  server: Array<{ label: string; nilai: string }>;
  modul: Array<{
    nama: string; url: string | null;
    items: Array<{ label: string; nilai: number; ikon: string }>;
  }>;
  peran: Array<{ label: string; jumlah: number }>;
  tren: Array<{ label: string; jumlah: number }>;
  perusahaan: Array<{
    id: number; nama: string; komoditas: string | null; lokasi: string | null;
    pekerja: number; pengguna: number; urlUbah: string;
    /* Data contoh. `isi` hanya dihitung untuk perusahaan contoh —
       untuk yang lain nilainya null, bukan objek kosong, supaya
       "belum dihitung" tidak tersamar menjadi "isinya nol". */
    demo: boolean;
    isi: Record<string, number> | null;
    urlTandai: string;
    urlMuat: string; urlHapus: string;
  }>;
  log: Array<{
    id: number; aksi: string; modul: string | null;
    detail: string | null; oleh: string | null; waktu: string | null;
  }>;
  pintasan: Array<{ url: string; label: string; sub: string; warna: string; ikon: string }>;
  diagnosa: { ringkas: Record<string, number>; url: string };
  tautan: { perusahaanBaru: string; bersihkanLog: string };
  pemeliharaan: Array<{ aksi: string; label: string; url: string }>;
}

export interface HalamanAi {
  judul: string; subjudul: string;
  penyedia: Array<{
    kode: string; nama: string; modelBawaan: string;
    contohModel: string[]; kunciDari: string;
    /* Sengaja tidak ada bidang untuk kuncinya sendiri: server tidak
       pernah mengirimkannya, dan bidang yang tersedia cepat atau lambat
       akan diisi seseorang. */
    terpasang: boolean; ekor: string | null; dariBerkas: boolean;
  }>;
  terpilih: string;
  model: string;
  maksToken: number;
  aktif: boolean;
  tautan: { simpan: string; uji: string; hapus: string; sistem: string; diagnosa: string };
}

export interface HalamanDiagnosa {
  judul: string; subjudul: string;
  hasil: Array<{
    kode: string; kelompok: string; judul: string;
    /* 'gawat' | 'perhatian' | 'tak-tahu' | 'aman' — dibiarkan string
       supaya keadaan baru dari sisi server tidak memaksa perubahan
       tipe di sini sebelum tampilannya siap menanganinya. */
    keadaan: string;
    nilai: string; uraian: string; tindakan: string | null;
  }>;
  ringkas: Record<string, number>;
  dijalankan: string;
  perbaikan: Array<{ aksi: string; label: string; ket: string; berat: boolean; url: string }>;
  ai: { aktif: boolean; url: string; atur: string };
  tautan: { sistem: string; pemeliharaan: string };

  /* Kesesuaian angka tiap modul. Bentuk barisnya sengaja sama persis
     dengan `hasil` di atas, supaya satu perender melayani keduanya —
     dua perender untuk bentuk yang sama pasti berbeda cepat atau
     lambat, dan bedanya muncul justru pada baris yang paling jarang
     tampil: yang merah. */
  sesuai: HalamanDiagnosa['hasil'];
  ringkasSesuai: Record<string, number>;
}

/* ══════════════ Register temuan lintas modul ══════════════ */

export interface BarisTemuan {
  sumber: string;
  /** Kunci baris ASALNYA, dipakai menugaskan. */
  id: number | null;
  modul: string;
  kode: string;
  judul: string;
  uraian: string | null;
  prioritas: string;
  status: string;
  terbuka: boolean;
  /** Punya penanggung jawab DAN tenggat. Salah satu saja belum cukup. */
  bertuan: boolean;
  penanggungJawab: string | null;
  targetSelesai: string | null;
  terlambat: boolean;
  hariTerlambat: number;
  /** Punya id DAN masih terbuka. Yang sudah selesai tidak perlu ditugaskan. */
  dapatDitugaskan: boolean;
}

export interface HalamanRegisterTemuan {
  judul: string; subjudul: string;
  temuan: BarisTemuan[];
  ringkas: { semua: number; terbuka: number; terlambat: number; takBertuan: number; selesai: number };
  perModul: Array<{ modul: string; jumlah: number }>;
  saring: string;
  opsi: Array<{ nilai: string; label: string }>;
  tautan: { register: string };
}

/* ══════════════ Berita, Prosedur, Penanda Tangan ══════════════ */

export interface HalamanDaftarBerita {
  judul: string; subjudul: string;
  berita: Array<{
    id: number; judul: string; tanggal: string | null; cuplikan: string;
    url: string; urlUbah: string; urlHapus: string;
  }>;
  halaman: { kini: number; akhir: number; total: number; tautan: TautanHalaman[] };
  bolehUbah: boolean;
  tautan: { buat: string };
}

export interface HalamanDetailBerita {
  judul: string; subjudul: string;
  berita: { judul: string; tanggal: string | null; isi: string };
  tautan: { daftar: string };
}

export interface HalamanFormBerita {
  judul: string; subjudul: string;
  tersimpan: boolean;
  awal: Record<string, string>;
  tautan: { simpan: string; batal: string };
}

export interface HalamanDaftarProsedur {
  judul: string; subjudul: string;
  prosedur: Array<{
    id: number; kode: string | null; judul: string;
    kategori: string | null; keterangan: string | null; url: string | null;
    urlUbah: string; urlHapus: string;
  }>;
  halaman: { kini: number; akhir: number; total: number; tautan: TautanHalaman[] };
  q: string;
  bolehUbah: boolean;
  tautan: { daftar: string; buat: string };
}

export interface HalamanFormProsedur {
  judul: string; subjudul: string;
  tersimpan: boolean;
  awal: Record<string, string>;
  tautan: { simpan: string; batal: string };
}

export interface PenandaTangan {
  id: number;
  nama: string;
  jabatan: string;
  aktif: boolean;
  perusahaanId: number | null;
  perusahaan: string | null;
  tandaTangan: string | null;
  urlSimpan: string;
  urlHapus: string;
}

export interface HalamanPenandaTangan {
  judul: string; subjudul: string;
  penandaTangan: PenandaTangan[];
  perusahaan: Array<{ id: number; nama: string }>;
  tautan: { tambah: string };
}

/* ══════════════ Sertifikat & Evaluasi ══════════════ */

export interface HalamanDaftarSertifikat {
  judul: string; subjudul: string;
  sertifikat: Array<{
    id: number; kursus: string; nomor: string;
    tanggal: string | null; penerima: string; url: string;
  }>;
  siapTerbit: Array<{ kursus: string | null; url: string }>;
  menungguEvaluasi: string[];
  admin: boolean;
}

export interface HalamanLembarSertifikat {
  judul: string; subjudul: string;
  c: {
    template: string;
    penerima: string; kursus: string; nomor: string;
    nilai: number | null; terbit: string | null;
    pemilik: string | null; lokasi: string | null; logo: string | null;
    ttdNama: string; ttdJabatan: string; ttdGambar: string | null;
    /** SVG barcode digambar server; lihat catatan di CertificateController. */
    barcodeSvg: string;
    barcodeTeks: string;
  };
  markUrl: string;
  tautan: { verifikasi: string; daftar: string };
}

export interface HalamanDaftarEvaluasi {
  judul: string; subjudul: string;
  evaluasi: Array<{
    id: number; peserta: string | null; kursus: string | null;
    trainer: string | null; tanggal: string | null;
    nilai: number; rekomendasi: string | null; url: string;
  }>;
  halaman: { kini: number; akhir: number; total: number; tautan: TautanHalaman[] };
  menunggu: Array<{ peserta: string | null; kursus: string | null; url: string }>;
  bolehMenilai: boolean;
  tautan: { buat: string };
}

export interface HalamanDetailEvaluasi {
  judul: string; subjudul: string;
  ev: {
    peserta: string | null; kursus: string | null; trainer: string | null;
    tanggal: string | null; nilai: number; rekomendasi: string | null;
    strengths: string | null; improvements: string | null; notes: string | null;
  };
  rincian: Array<{ label: string; nilai: number }>;
  bolehUbah: boolean;
  tautan: { daftar: string; ubah: string; hapus: string };
}

export interface HalamanFormEvaluasi {
  judul: string; subjudul: string;
  tersimpan: boolean;
  awal: Record<string, string>;
  opsi: { peserta: Pilihan[]; kursus: Pilihan[]; rekomendasi: string[] };
  /** Peta id peserta → kursus yang diambilnya. */
  kursusPeserta: Record<string, Array<{ id: number; title: string }>>;
  medan: Array<{ nama: string; label: string; ket: string }>;
  catatan: Array<{ nama: string; label: string; ph: string }>;
  tautan: { simpan: string; batal: string };
}

/* ══════════════ Kursus, Belajar, Kuis, SOP ══════════════ */

export interface HalamanDaftarKursus {
  judul: string; subjudul: string;
  kursus: Array<{
    id: number; judul: string; keterangan: string | null;
    kategori: string | null; nadaKategori: string | null; inisial: string;
    sampul: string | null; perluKode: boolean; diikuti: boolean; jumlahModul: number;
    urlBelajar: string; urlDetail: string; urlKelola: string; urlHapus: string;
  }>;
  halaman: { kini: number; akhir: number; total: number; tautan: TautanHalaman[] };
  f: { q: string; kategori: string; status: string; urut: string };
  opsi: { kategori: string[]; status: Pilihan[]; urut: Pilihan[] };
  bolehKelola: boolean;
  tautan: { daftar: string; buat: string };
}

export interface MateriKursus {
  id: number;
  judul: string;
  jenis: string;
  url?: string | null;
}

export interface HalamanDetailKursus {
  judul: string; subjudul: string;
  kursus: {
    judul: string; keterangan: string | null;
    kategori: string | null; nadaKategori: string | null; gambar: string | null;
  };
  modul: Array<{
    id: number; urutan: number; judul: string;
    keterangan: string | null; materi: MateriKursus[];
  }>;
  bolehUbah: boolean;
  tautan: { belajar: string; ubah: string; daftar: string };
}

export interface HalamanFormKursus {
  judul: string; subjudul: string;
  tersimpan: boolean;
  awal: Record<string, string | boolean>;
  gambar: string | null;
  opsi: { sertifikat: Pilihan[] };
  tautan: { simpan: string; batal: string };
}

export interface HalamanKelolaKursus {
  judul: string; subjudul: string;
  kursus: { judul: string; kode: string | null; perluKode: boolean };
  modul: Array<{
    id: number; urutan: number; judul: string; keterangan: string | null;
    materi: Array<MateriKursus & { urlHapus: string }>;
    urlHapus: string; urlTambahMateri: string;
  }>;
  kuis: Array<{
    id: number; judul: string; nilaiLulus: number;
    /** Kunci jawaban hanya ada di halaman admin ini; lihat CourseContentController. */
    soal: Array<{ id: number; soal: string; jawaban: string; urlHapus: string }>;
    urlHapus: string; urlTambahSoal: string;
  }>;
  tautan: { pratinjau: string; info: string; tambahModul: string; tambahKuis: string };
}

export interface HalamanKodeKursus {
  judul: string; subjudul: string;
  kursus: { judul: string };
  tautan: { buka: string; daftar: string };
}

export interface HalamanBelajarKursus {
  judul: string; subjudul: string;
  kursus: { judul: string; keterangan: string | null; progres: number; selesai: boolean };
  modul: Array<{
    id: number; urutan: number; judul: string; keterangan: string | null;
    selesai: boolean; catatan: string; materi: MateriKursus[];
    urlSelesai: string; urlCatatan: string;
  }>;
  kuis: Array<{ id: number; judul: string; nilaiLulus: number; url: string }>;
  tautan: { sertifikat: string };
}

export interface SoalPilihan {
  id: number;
  soal: string;
  pilihan: string[];
}

export interface HalamanKerjakanKuis {
  judul: string; subjudul: string;
  kuis: { judul: string; jumlahSoal: number; nilaiLulus: number };
  soal: SoalPilihan[];
  tautan: { kirim: string };
}

export interface HalamanHasilKuis {
  judul: string; subjudul: string;
  hasil: {
    nilai: number; lulus: boolean; benar: number;
    total: number; nilaiLulus: number; kuis: string;
  };
  tautan: { ulangi: string; dashboard: string };
}

export interface HalamanDaftarSop {
  judul: string; subjudul: string;
  evaluasi: Array<{
    id: number; judul: string; keterangan: string | null; prosedur: string | null;
    durasi: number; nilaiLulus: number;
    terbaik: number | null; lulus: boolean; url: string;
  }>;
}

export interface HalamanKerjakanSop {
  judul: string; subjudul: string;
  ev: { judul: string; jumlahSoal: number; nilaiLulus: number; durasiDetik: number };
  soal: SoalPilihan[];
  tautan: { kirim: string };
}

export interface HalamanHasilSop {
  judul: string; subjudul: string;
  hasil: {
    nilai: number; lulus: boolean; benar: number;
    total: number; nilaiLulus: number; evaluasi: string;
  };
  tautan: { ulangi: string; daftar: string };
}

/* ── Keamanan & jaringan ── */

export interface SesiAktif {
  id: string;
  iniSaya: boolean;
  userId: number | null;
  nama: string;
  email: string | null;
  ip: string;
  perangkat: string;
  terakhir: string;
  detik: number;
}

export interface PeristiwaKeamanan {
  id: number;
  peristiwa: string;
  siapa: string;
  detail: string | null;
  ip: string;
  perangkat: string;
  kapan: string | null;
  gagal: boolean;
}

export interface HalamanKeamanan {
  judul: string; subjudul: string;
  ringkas: {
    gagal24: number; gagal1: number; keadaan: string;
    sesiAktif: number; sesiBasi: number;
    ambang: number; ambangGawat: number;
  };
  menekan: Array<{ ip: string; jumlah: number; sasaran: number; terakhir: string }>;
  sesi: SesiAktif[];
  riwayat: PeristiwaKeamanan[];
  tajuk: Array<{ nama: string; ada: boolean; nilai: string | null; guna: string }>;
  tidurAn: Array<{
    id: number; nama: string; email: string;
    admin: boolean; terakhir: string; url: string;
  }>;
  tautan: {
    putusSesi: string; bersihSesi: string; pangkasJejak: string;
    sistem: string; diagnosa: string; perangkatSaya: string;
  };
}

export interface HalamanPerangkat {
  judul: string; subjudul: string;
  sesi: SesiAktif[];
  riwayat: PeristiwaKeamanan[];
  masukTerakhir: { kapan: string | null; ip: string | null };
  tautan: { putus: string; putusLain: string };
}

/* ── Penetapan pemilik ── */

export interface HalamanPemilik {
  judul: string; subjudul: string;
  jenis: Array<{
    kunci: string; label: string; ket: string; jumlah: number;
    baris: Array<{ id: number; judul: string; ket: string }>;
  }>;
  perusahaan: Array<{ nilai: number; label: string }>;
  tautan: { tetapkan: string; sistem: string; diagnosa: string };
}
