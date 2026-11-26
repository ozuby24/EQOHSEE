/**
 * Bantu bersama halaman MERANTI — Reklamasi & Nursery.
 *
 * Warnanya mengikuti MERANTI: hijau daun untuk P0, emas untuk P1, toska
 * untuk P2 — supaya yang terbiasa membaca grafik aplikasi lamanya tidak
 * perlu belajar ulang.
 */
export const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
export const BULAN_PENDEK = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export const WARNA_PEKERJAAN: Record<string, string> = { P0: '#4CA771', P1: '#C9A227', P2: '#2D8C8C' };
export const LABEL_PEKERJAAN: Record<string, string> = { P0: 'Penanaman', P1: 'Pemeliharaan I', P2: 'Pemeliharaan II' };

export const WARNA_GOLONGAN: Record<string, string> = {
  Pioneer: '#4CA771', Lokal: '#2E7D55', MPTS: '#C9A227', 'Cover Crop': '#2D8C8C', Lainnya: '#94A3B8',
};

export const WARNA_KATEGORI_PETA: Record<string, string> = {
  Blok: '#2E7D55', Petak: '#4CA771', 'Batas Area': '#C9A227', 'Titik Tanam': '#B45309', Lainnya: '#64748B',
};

export const warnaKategori = (k: unknown) => WARNA_KATEGORI_PETA[String(k ?? '')] ?? WARNA_KATEGORI_PETA.Lainnya;

/* Garis di atas citra satelit: hijau tua tenggelam di atas hutan dan lahan
   revegetasi yang juga hijau, jadi batasnya digambar terang — kuning untuk
   blok, seperti batas KML di Google Earth — dengan pinggiran gelap. */
const GARIS_SATELIT: Record<string, string> = {
  Blok: '#FACC15', Petak: '#4ADE80', 'Batas Area': '#FB923C', 'Titik Tanam': '#F97316', Lainnya: '#E2E8F0',
};
export const garisSatelit = (k: unknown) => GARIS_SATELIT[String(k ?? '')] ?? GARIS_SATELIT.Lainnya;

/** Kelas lencana untuk nilai berstatus — kategori skor, status, jenis, pekerjaan. */
const LENCANA: Record<string, string> = {
  Baik: 'mr-hijau', Sedang: 'mr-emas', Buruk: 'mr-merah',
  Ditempatkan: 'mr-hijau', 'Sebagian Dicairkan': 'mr-emas', Dicairkan: 'mr-abu',
  Diajukan: 'mr-biru', Disetujui: 'mr-hijau', Ditolak: 'mr-merah', Dibayar: 'mr-toska',
  Aktif: 'mr-hijau', Tutup: 'mr-abu', Masuk: 'mr-biru', Keluar: 'mr-emas',
  Vendor: 'mr-emas', 'In House': 'mr-hijau',
  P0: 'mr-p0', P1: 'mr-p1', P2: 'mr-p2',
  Pioneer: 'mr-hijau', Lokal: 'mr-tua', MPTS: 'mr-emas', 'Cover Crop': 'mr-toska',
  Blok: 'mr-tua', Petak: 'mr-hijau', 'Batas Area': 'mr-emas', 'Titik Tanam': 'mr-cokelat',
};
export const kelasLencana = (v: unknown) => LENCANA[String(v ?? '')] ?? (String(v ?? '').startsWith('Minggu') ? 'mr-biru' : 'mr-abu');

/** Kategori Matrik 17 dari skor: > 80 Baik, 60–80 Sedang, < 60 Buruk. */
export function kategoriSkor(skor: number | null | undefined): string {
  if (skor === null || skor === undefined || Number.isNaN(skor)) return '—';
  return skor > 80 ? 'Baik' : skor >= 60 ? 'Sedang' : 'Buruk';
}

export function matrik17(p: unknown, r: unknown, s: unknown): number {
  const a = (v: unknown) => Number(v) || 0;
  return Math.round((0.6 * a(p) + 0.2 * a(r) + 0.2 * a(s)) * 100) / 100;
}

/** Warna persen capaian terhadap target. */
export function warnaCapaian(p: number | null | undefined): string {
  if (p === null || p === undefined) return '#CBD5E1';
  if (p >= 100) return '#15803D';
  if (p >= 75) return '#65A30D';
  if (p >= 50) return '#F59E0B';
  return '#DC2626';
}

/** Urutan alami nama blok: Blok 1, Blok 2, Blok 10. */
export function urutBlok(a: string, b: string): number {
  const na = Number((a.match(/\d+/) ?? ['0'])[0]);
  const nb = Number((b.match(/\d+/) ?? ['0'])[0]);
  return na !== nb ? na - nb : a.localeCompare(b, 'id', { numeric: true });
}
