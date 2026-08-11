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
}

export interface Modul {
  kunci: string;
  label: string;
  ikon: string;
  url: string;
  aktif: boolean;
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

export interface HalamanPenilaian {
  judul: string;
  subjudul: string;
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
