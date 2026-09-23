/**
 * Pembantu bersama halaman Audit Kinerja Lingkungan.
 */

/**
 * Warna seri bagian A–F: urutan kategori tetap, tidak pernah diputar
 * ulang, sehingga bagian B selalu jingga di setiap bagan dan kartu.
 * Nilainya variabel CSS (akl.css) — terang dan gelap masing-masing
 * sudah divalidasi untuk buta warna.
 */
export const WARNA_BAGIAN: Record<string, string> = {
  a: 'var(--akl-s1)', b: 'var(--akl-s2)', c: 'var(--akl-s3)',
  d: 'var(--akl-s4)', e: 'var(--akl-s5)', f: 'var(--akl-s6)',
};

/** Angka gaya Indonesia: koma desimal. */
export const angka = (n: number, d = 2) =>
  n.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });

/** Bagian yang harus bernilai PENUH agar predikat terbit. */
export const WAJIB = ['a', 'b'];
