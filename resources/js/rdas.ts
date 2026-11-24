/**
 * Bahasa visual Rehabilitasi DAS, dipakai bersama seluruh halamannya.
 *
 * Fase bergerak dari abu (penetapan) ke amber (P0, tanah baru dibuka)
 * lalu makin hijau sampai BAST. Persen tumbuh memakai ambang penilaian:
 * < 75% merah (wajib sulam), 75–80% kuning (peringatan), ≥ 80% hijau.
 */
type Lencana = { label: string; pendek: string; kelas: string; warna: string };

export const FASE: Record<string, Lencana> = {
  penetapan:    { label: 'Lokasi ditetapkan', pendek: 'Penlok', kelas: 'pr-abu', warna: '#94A3B8' },
  rkp:          { label: 'RKP disahkan', pendek: 'RKP', kelas: 'pr-ungu', warna: '#A78BFA' },
  p0:           { label: 'P0 — penanaman', pendek: 'P0', kelas: 'pr-kuning', warna: '#F59E0B' },
  p1:           { label: 'P1 — pemeliharaan tahun ke-1', pendek: 'P1', kelas: 'pr-hijau', warna: '#84CC16' },
  p2:           { label: 'P2 — pemeliharaan tahun ke-2', pendek: 'P2', kelas: 'pr-hijau', warna: '#22C55E' },
  siap_nilai:   { label: 'Usia ≥ 36 bulan — siap dinilai', pendek: 'Siap dinilai', kelas: 'pr-biru', warna: '#0EA5E9' },
  penilaian:    { label: 'Dalam penilaian tim', pendek: 'Penilaian', kelas: 'pr-biru', warna: '#2563EB' },
  serah_terima: { label: 'Diserahterimakan (BAST)', pendek: 'BAST', kelas: 'pr-hijau', warna: '#15803D' },
};
export const fase = (s: unknown) => FASE[String(s)] ?? FASE.penetapan;

export const TUMBUH: Record<string, Lencana> = {
  berhasil:   { label: 'Berhasil ≥ 80%', pendek: 'Berhasil', kelas: 'pr-hijau', warna: '#15803D' },
  peringatan: { label: 'Peringatan 75–80%', pendek: 'Peringatan', kelas: 'pr-kuning', warna: '#D97706' },
  sulam:      { label: 'Wajib sulam < 75%', pendek: 'Wajib sulam', kelas: 'pr-merah', warna: '#DC2626' },
  belum:      { label: 'Belum dipantau', pendek: 'Belum dipantau', kelas: 'pr-abu', warna: '#A8A29E' },
};
export const tumbuh = (s: unknown) => TUMBUH[String(s ?? 'belum')] ?? TUMBUH.belum;

/** Skala warna persen tumbuh untuk peta dan batang. */
export function warnaTumbuh(p: number | null | undefined): string {
  if (p === null || p === undefined) return '#CBD5E1';
  if (p < 60) return '#991B1B';
  if (p < 75) return '#EF4444';
  if (p < 80) return '#F59E0B';
  if (p < 90) return '#65A30D';
  return '#15803D';
}

export const PENILAIAN: Record<string, Lencana> = {
  diajukan:       { label: 'Permohonan diajukan', pendek: 'Diajukan', kelas: 'pr-abu', warna: '#94A3B8' },
  tim_dibentuk:   { label: 'SK tim penilai terbit', pendek: 'Tim dibentuk', kelas: 'pr-ungu', warna: '#7C3AED' },
  dinilai:        { label: 'Penilaian lapangan', pendek: 'Dinilai', kelas: 'pr-biru', warna: '#2563EB' },
  berhasil:       { label: 'Berhasil — menunggu BAST', pendek: 'Berhasil', kelas: 'pr-hijau', warna: '#22C55E' },
  tidak_berhasil: { label: 'Tidak berhasil — pemeliharaan lanjutan', pendek: 'Tidak berhasil', kelas: 'pr-merah', warna: '#DC2626' },
  ditunda:        { label: 'Ditunda', pendek: 'Ditunda', kelas: 'pr-kuning', warna: '#D97706' },
  serah_terima:   { label: 'Diserahterimakan (BAST)', pendek: 'BAST', kelas: 'pr-hijau', warna: '#15803D' },
};
export const penilaian = (s: unknown) => PENILAIAN[String(s)] ?? PENILAIAN.diajukan;

export const LAPORAN: Record<string, Lencana> = {
  tepat:       { label: 'Terkirim tepat waktu', pendek: 'Tepat waktu', kelas: 'pr-hijau', warna: '#15803D' },
  terlambat:   { label: 'Terlambat', pendek: 'Terlambat', kelas: 'pr-merah', warna: '#DC2626' },
  jatuh_tempo: { label: 'Jatuh tempo ≤ 30 hari', pendek: 'Jatuh tempo', kelas: 'pr-kuning', warna: '#D97706' },
  belum:       { label: 'Belum jatuh tempo', pendek: 'Belum', kelas: 'pr-abu', warna: '#A8A29E' },
};

export const KEJADIAN_STATUS: Record<string, Lencana> = {
  terbuka:   { label: 'Terbuka', pendek: 'Terbuka', kelas: 'pr-merah', warna: '#DC2626' },
  ditangani: { label: 'Ditangani', pendek: 'Ditangani', kelas: 'pr-kuning', warna: '#D97706' },
  selesai:   { label: 'Selesai', pendek: 'Selesai', kelas: 'pr-hijau', warna: '#15803D' },
};

export const WAKTU: Record<string, Lencana> = {
  aman:     { label: 'Waktu aman', pendek: 'Aman', kelas: 'pr-hijau', warna: '#15803D' },
  mendesak: { label: 'Batas ≤ 180 hari', pendek: 'Mendesak', kelas: 'pr-kuning', warna: '#D97706' },
  lewat:    { label: 'Lewat batas penyelesaian', pendek: 'Lewat batas', kelas: 'pr-merah', warna: '#DC2626' },
};

export const KATEGORI_JENIS: Record<string, { label: string; warna: string }> = {
  lokal: { label: 'Lokal berdaur panjang', warna: '#166534' },
  cepat: { label: 'Cepat tumbuh', warna: '#84CC16' },
  mpts:  { label: 'MPTS', warna: '#D97706' },
};

export function tanggal(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso.length === 10 ? iso + 'T00:00:00' : iso);
  return isNaN(d.getTime()) ? '—' : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function sisa(hari: number | null | undefined): string {
  if (hari === null || hari === undefined) return '—';
  if (hari < 0) return `lewat ${Math.abs(hari)} hari`;
  if (hari > 400) return `${Math.round(hari / 365 * 10) / 10} tahun lagi`;
  return `${hari} hari lagi`;
}
