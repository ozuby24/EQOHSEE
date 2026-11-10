/**
 * Blok penyusun berkas cetak panjang (Berita Acara Tahap I, Laporan Audit).
 * Isi disusun sebagai urutan blok (judul, paragraf, daftar, tabel, tanda
 * tangan) yang diukur di peramban lalu dibagi ke lembar A4 — tabel panjang
 * berlanjut ke lembar berikutnya dengan kepala tabel diulang.
 */

/** Potongan teks; isian pengguna selalu teks polos, gaya hanya untuk teks baku. */
export type Ruas = string | { t: string; b?: boolean; i?: boolean; s?: boolean };
export type Isi = Ruas[];

/** `rata` = isi sel rata kiri-kanan (uraian panjang). */
export interface Kolom { judul?: Isi; lebar: string; tengah?: boolean; rata?: boolean }

export type BlokCetak =
  | { k: 'judul'; tingkat: 0 | 1 | 2 | 3; nomor?: string; isi: Isi }
  | { k: 'teks'; isi: Isi; inden?: 0 | 1 | 2; lekat?: boolean }
  /** `awalan` menomori butir sebagai "3.1.", "3.2.", … (gaya '1.'). */
  | { k: 'daftar'; gaya: '1)' | '1.' | '•'; butir: Array<{ isi: Isi; anak?: Isi[] }>; inden?: 0 | 1 | 2; lekat?: boolean; awalan?: string }
  | { k: 'rincian'; baris: Array<[string, Isi]>; tanpaTitik?: boolean }
  /** Baris bersel tunggal dicetak sebagai judul kelompok selebar tabel. */
  | { k: 'tabel'; kolom: Kolom[]; baris: Isi[][]; kepala: boolean }
  /** Tempat tanda tangan; isinya diberikan halaman lewat slot, dibedakan `id`. */
  | { k: 'ttd'; id?: string };

/** Istilah asing pada teks baku formulir resmi yang dicetak miring. */
const ASING = /(Absence Severity Rate|Morbidity Frequency Rate|Frequency Rate|Severity Rate|absence severity rate|morbidity frequency rate|frequency rate|severity rate|Mandays|Near Miss|Property Damage|Trend|Risk of Present|Risk of Future|Key Leading Indicator|Physical Availability|Mechanical Availability|Sampling|critical point|lesson learned)/;

/** Teks baku → ruas dengan istilah asing dimiringkan. */
export function miring(teks: string, tebal = false): Isi {
    return teks.split(ASING).filter((x) => x !== '').map((x) => (ASING.test(x) ? { t: x, i: true, b: tebal } : tebal ? { t: x, b: true } : x));
}

export const B = (t: string): Ruas => ({ t, b: true });
