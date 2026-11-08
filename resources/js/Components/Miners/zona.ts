/**
 * Zona masa berlaku — daftarZona() MasaBerlakuService Safe Track.
 *
 * Disalin dari App\Support\Miners\Zona: batas 30 dan 60 hari, label
 * pendek untuk kartu, label panjang untuk keterangan. Server yang
 * menghitung zona tiap baris; daftar ini hanya untuk menggambarnya.
 */
export type KunciZona = 'expired' | 'kritis' | 'waspada' | 'aman' | 'kosong';

export const ZONA: Record<KunciZona, { pendek: string; label: string }> = {
  expired: { pendek: 'Expired',     label: 'Lewat masa berlaku' },
  kritis:  { pendek: '≤ 30 hari',   label: 'Kurang dari 30 hari' },
  waspada: { pendek: '31-60 hari',  label: '31 - 60 hari lagi' },
  aman:    { pendek: '> 60 hari',   label: 'Lebih dari 60 hari lagi' },
  kosong:  { pendek: 'Belum diisi', label: 'Tanggal belum diisi' },
};

export const URUTAN_ZONA: KunciZona[] = ['expired', 'kritis', 'waspada', 'aman', 'kosong'];

/** Zona sebuah sisa hari; null berarti tanggalnya kosong. */
export function zonaDari(sisa: number | null | undefined): KunciZona {
  if (sisa === null || sisa === undefined) return 'kosong';
  if (sisa < 0) return 'expired';
  if (sisa <= 30) return 'kritis';
  if (sisa <= 60) return 'waspada';
  return 'aman';
}

/** "2026-09-15" → "15-09-2026" — format tanggal layar Safe Track. */
export function tglId(iso: string | null | undefined): string {
  if (!iso) return '—';
  const [y, m, d] = iso.slice(0, 10).split('-');
  return d && m && y ? `${d}-${m}-${y}` : iso;
}

/** Satu medan pada baris saring. */
export interface MedanSaring {
  kunci: string;
  label: string;
  jenis?: 'teks' | 'pilih' | 'tanggal';
  opsi?: { nilai: string | number; label: string }[];
  semua?: string;
  placeholder?: string;
  lebar?: boolean;
}

/** Satu baris rekap per perusahaan. */
export interface BarisRekap {
  perusahaan: string;
  total: number;
  aktif?: number;
  nonaktif?: number;
  expired: number;
  kritis: number;
  waspada: number;
  aman: number;
  kosong: number;
}

/** Satu kolom tabel data. */
export interface KolomTabel {
  kunci: string;
  label: string;
  kelas?: string;
}

/** Tautan paginasi Laravel. */
export interface TautanHalaman {
  url: string | null;
  label: string;
  active: boolean;
}

/** Satu kolom tabel persetujuan — RincianDokumen::alur(). */
export interface LangkahPersetujuan {
  urutan: number;
  peran: string;
  sebutan: string;
  label: string;
  keadaan: string;
  keadaanLabel: string;
  giliran: boolean;
  oleh?: string | null;
  ditugaskan?: string | null;
  atasNama?: boolean;
  pada?: string | null;
  catatan?: string | null;
}
