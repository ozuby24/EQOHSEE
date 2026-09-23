/**
 * Tipe halaman Observasi Operator (FROP).
 *
 * Ditaruh bersama halamannya, bukan di types.ts: seluruh bentuknya
 * hanya dipakai delapan halaman di folder ini.
 */

export type Nada = 'baik' | 'ingat' | 'gawat' | 'netral';

export interface Nilai { kode: string; teks: string; nada?: Nada }

export interface TautanFrop {
  index: string; buat: string; operatorRekap: string; kpi: string;
  tracker: string; coachingLog: string; impor: string; panduan: string;
}

export interface OpsiBulan { nilai: string; label: string }

export interface BarisSesi {
  id: number; url: string; tanggal: string; shift: number | null; jam: string | null;
  unit: string; operator: string; observer: string | null;
  level: string; level_label: string;
  digging: number | null; swl: number | null; dump: number | null; swe: number | null;
  ct: number | null; plan: number | null; selisih: number | null; on: boolean | null;
  pty: number | null;
  kesimpulan: Nilai; rekomendasi: Nilai;
  temuan: string | null; kategori: string[]; status_ca: string;
}

export interface Komponen {
  kunci: string; label: string; ket: string;
  aktual: number | null; plan: number | null; selisih: number | null;
  ok: boolean | null; porsi: number | null;
}

export interface RekapOperator {
  total: number; rata_ct: number | null; terbaik: number | null; terburuk: number | null;
  on_target: number; konsistensi: number | null; rata_spot: number | null; rata_dig: number | null;
  rata_pty: number | null; heap: number | null; tren: number | null;
  arah: 'membaik' | 'menurun' | 'stabil' | null;
  performer: Nilai; catatan: string;
}

export interface BarisCoaching {
  id: number; tanggal: string; operator: string; unit: string | null; materi: string;
  respons: string | null; coach: string | null; follow_up: string | null;
  target_selesai: string | null; status: string;
  observasi_id: number | null; observasi_url: string | null;
  ubah: string; hapus: string;
}

export interface IsianCoaching {
  observasi_id: string; tanggal: string; operator: string; unit: string; materi: string;
  respons: string; coach: string; follow_up: string; target_selesai: string; status: string;
}

export const KATEGORI_WARNA: Record<string, string> = {
  unit: '#7C3AED', jalan: '#0891B2', hauler: '#DC6E00', material: '#B45309',
  metode: '#2563EB', front: '#15803D', lain: '#78716C',
};
