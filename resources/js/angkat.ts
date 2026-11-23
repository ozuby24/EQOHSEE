/**
 * Bahasa visual Lifting & Rigging Management, dipakai bersama seluruh
 * halamannya. Warna semantik K3: hijau layak, kuning bersyarat/segera,
 * merah tidak layak. Kedaluwarsa dan karantina sama-sama merah karena
 * artinya sama di lapangan: TIDAK LAYAK DIGUNAKAN.
 */
type Lencana = { label: string; pendek: string; kelas: string; warna: string };

export const SIMULASI: Record<string, Lencana> = {
  go:        { label: 'GO — layak diangkat', pendek: 'GO', kelas: 'pr-hijau', warna: '#15803D' },
  bersyarat: { label: 'GO bersyarat — kendali tambahan wajib', pendek: 'GO bersyarat', kelas: 'pr-kuning', warna: '#D97706' },
  nogo:      { label: 'NO-GO — tidak layak diangkat', pendek: 'NO-GO', kelas: 'pr-merah', warna: '#DC2626' },
};
export const simulasi = (s: unknown) => SIMULASI[String(s)] ?? SIMULASI.nogo;

export const KLAS: Record<string, Lencana> = {
  rutin:    { label: 'Rutin', pendek: 'Rutin', kelas: 'pr-biru', warna: '#2A78D6' },
  rekayasa: { label: 'Rekayasa', pendek: 'Rekayasa', kelas: 'pr-ungu', warna: '#7C3AED' },
  kritis:   { label: 'Kritis — persetujuan KTT', pendek: 'Kritis', kelas: 'pr-merah', warna: '#DC2626' },
};
export const klas = (s: unknown) => KLAS[String(s)] ?? KLAS.rutin;

export const STATUS: Record<string, Lencana> = {
  draf:      { label: 'Draf', pendek: 'Draf', kelas: 'pr-abu', warna: '#78716C' },
  diajukan:  { label: 'Menunggu persetujuan', pendek: 'Menunggu', kelas: 'pr-kuning', warna: '#D97706' },
  disetujui: { label: 'Disetujui', pendek: 'Disetujui', kelas: 'pr-hijau', warna: '#15803D' },
  ditolak:   { label: 'Ditolak', pendek: 'Ditolak', kelas: 'pr-merah', warna: '#DC2626' },
  selesai:   { label: 'Selesai dilaksanakan', pendek: 'Selesai', kelas: 'pr-biru', warna: '#2A78D6' },
  batal:     { label: 'Dibatalkan', pendek: 'Batal', kelas: 'pr-abu', warna: '#A8A29E' },
};
export const status = (s: unknown) => STATUS[String(s)] ?? STATUS.draf;

/** Status efektif crane & alat bantu. */
export const KELAYAKAN: Record<string, Lencana> = {
  layak:       { label: 'Layak digunakan', pendek: 'Layak', kelas: 'pr-hijau', warna: '#15803D' },
  segera:      { label: 'Layak digunakan — riksa uji ≤ 30 hari', pendek: 'Segera riksa', kelas: 'pr-kuning', warna: '#D97706' },
  kedaluwarsa: { label: 'Tidak layak digunakan — masa uji habis', pendek: 'Kedaluwarsa', kelas: 'pr-merah', warna: '#9F1239' },
  karantina:   { label: 'Tidak layak digunakan — dikarantina', pendek: 'Karantina', kelas: 'pr-merah', warna: '#DC2626' },
  afkir:       { label: 'Diafkirkan', pendek: 'Afkir', kelas: 'pr-abu', warna: '#78716C' },
};
export const kelayakan = (s: unknown) => KELAYAKAN[String(s)] ?? KELAYAKAN.kedaluwarsa;

export const FASE: Record<string, Lencana> = {
  lulus:   { label: 'Lulus', pendek: 'Lulus', kelas: 'pr-hijau', warna: '#15803D' },
  waspada: { label: 'Waspada', pendek: 'Waspada', kelas: 'pr-kuning', warna: '#D97706' },
  gagal:   { label: 'Gagal', pendek: 'Gagal', kelas: 'pr-merah', warna: '#DC2626' },
};
export const fase = (s: unknown) => FASE[String(s)] ?? FASE.gagal;

export const KAJIAN: Record<string, Lencana> = {
  memenuhi:       { label: 'Memenuhi', pendek: 'Memenuhi', kelas: 'pr-hijau', warna: '#15803D' },
  bersyarat:      { label: 'Bersyarat', pendek: 'Bersyarat', kelas: 'pr-kuning', warna: '#D97706' },
  tidak_memenuhi: { label: 'Tidak memenuhi', pendek: 'Tidak memenuhi', kelas: 'pr-merah', warna: '#DC2626' },
};
export const kajian = (s: unknown) => KAJIAN[String(s)] ?? KAJIAN.bersyarat;

export const BUTIR: Record<string, Lencana> = {
  penuh:    { label: 'Terpenuhi', pendek: 'Penuh', kelas: 'pr-hijau', warna: '#15803D' },
  sebagian: { label: 'Sebagian', pendek: 'Sebagian', kelas: 'pr-kuning', warna: '#D97706' },
  tidak:    { label: 'Tidak terpenuhi', pendek: 'Tidak', kelas: 'pr-merah', warna: '#DC2626' },
  belum:    { label: 'Belum dinilai', pendek: 'Belum', kelas: 'pr-abu', warna: '#A8A29E' },
};

export const RIKSA: Record<string, Lencana> = {
  layak:       { label: 'LAYAK DIGUNAKAN', pendek: 'Layak', kelas: 'pr-hijau', warna: '#15803D' },
  tidak_layak: { label: 'TIDAK LAYAK DIGUNAKAN', pendek: 'Tidak layak', kelas: 'pr-merah', warna: '#DC2626' },
};
export const riksa = (s: unknown) => RIKSA[String(s)] ?? RIKSA.tidak_layak;

/** Warna triwulan alat bantu angkat. */
export const WARNA_KODE: Record<string, string> = { PUTIH: '#F5F5F4', HIJAU: '#16A34A', BIRU: '#2563EB', KUNING: '#EAB308', MERAH: '#DC2626' };

export function tanggal(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso.length === 10 ? iso + 'T00:00:00' : iso);
  return isNaN(d.getTime()) ? '—' : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function waktu(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return isNaN(d.getTime()) ? '—' : d.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

/** "12 hari lagi", "lewat 3 hari". */
export function sisaHari(iso: string | null | undefined): string {
  if (!iso) return 'belum pernah diuji';
  const h = Math.round((new Date(iso + (iso.length === 10 ? 'T23:59:59' : '')).getTime() - Date.now()) / 864e5);
  return h >= 0 ? `${h} hari lagi` : `lewat ${-h} hari`;
}
