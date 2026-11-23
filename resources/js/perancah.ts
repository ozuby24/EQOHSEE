/**
 * Bahasa visual tag perancah, dipakai bersama seluruh halaman Scaffolding.
 *
 * Warnanya semantik K3 (ISO 3864): hijau aman, kuning bersyarat, merah
 * tidak layak. Kedaluwarsa tampil merah tua — bukan kuning — karena artinya
 * sama dengan merah: tidak layak digunakan.
 */
export const TAG: Record<string, { label: string; pendek: string; warna: string; muda: string; kelas: string }> = {
  hijau:       { label: 'Hijau — layak digunakan',    pendek: 'Layak',        warna: '#15803D', muda: '#DCFCE7', kelas: 'pr-hijau' },
  kuning:      { label: 'Kuning — layak digunakan dengan syarat', pendek: 'Layak bersyarat', warna: '#D97706', muda: '#FEF3C7', kelas: 'pr-kuning' },
  merah:       { label: 'Merah — tidak layak digunakan', pendek: 'Tidak layak',      warna: '#DC2626', muda: '#FEE2E2', kelas: 'pr-merah' },
  kedaluwarsa: { label: 'Kedaluwarsa — tidak layak digunakan, wajib inspeksi ulang', pendek: 'Kedaluwarsa', warna: '#9F1239', muda: '#FFE4E6', kelas: 'pr-merah' },
  dibongkar:   { label: 'Sudah dibongkar',            pendek: 'Dibongkar',   warna: '#78716C', muda: '#F5F5F4', kelas: 'pr-abu' },
};

export const tag = (s: unknown) => TAG[String(s)] ?? TAG.merah;

export const PUTUSAN: Record<string, { label: string; kelas: string }> = {
  layak:       { label: 'Layak',            kelas: 'pr-hijau' },
  bersyarat:   { label: 'Layak bersyarat',  kelas: 'pr-kuning' },
  tidak_layak: { label: 'Tidak layak',      kelas: 'pr-merah' },
  aman:        { label: 'Layak',            kelas: 'pr-hijau' },
  hati_hati:   { label: 'Layak bersyarat',  kelas: 'pr-kuning' },
  bahaya:      { label: 'Tidak layak',      kelas: 'pr-merah' },
};

/** "dalam 2 hari", "lewat 5 jam" — sisa masa berlaku tag. */
export function sisa(iso: string | null | undefined): string {
  if (!iso) return '—';
  const ms = new Date(iso).getTime() - Date.now();
  const j = Math.abs(ms) / 36e5;
  const teks = j >= 48 ? `${Math.round(j / 24)} hari` : `${Math.max(1, Math.round(j))} jam`;
  return ms >= 0 ? `${teks} lagi` : `lewat ${teks}`;
}

export function waktu(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return isNaN(d.getTime()) ? '—' : d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
