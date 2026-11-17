/**
 * Warna nilai butir SMKP, selaras dengan ambang kategori temuan pada
 * elemen.json: capaian penuh = kesesuaian, 50% s.d. <100% = minor,
 * <50% = mayor. Dipakai tombol nilai dan tangga kriteria agar auditor
 * langsung melihat makna angka yang dipilih.
 */
export type Nada = 'hijau' | 'amber' | 'rose' | 'slate';

export function nadaNilai(v: number | string, maks: number): Nada {
    if (v === 'N/A' || v === '' || !maks) return 'slate';
    const p = Number(v) / maks;
    return p >= 1 ? 'hijau' : p >= 0.5 ? 'amber' : 'rose';
}

export const KELAS_NADA: Record<Nada, { pilih: string; lembut: string; lencana: string; garis: string }> = {
    hijau: { pilih: 'bg-hijau-600 border-hijau-600 text-white', lembut: 'bg-hijau-50/70 border-hijau-200', lencana: 'bg-hijau-600 text-white', garis: 'border-l-hijau-500' },
    amber: { pilih: 'bg-amber-500 border-amber-500 text-white', lembut: 'bg-amber-50/70 border-amber-200', lencana: 'bg-amber-500 text-white', garis: 'border-l-amber-400' },
    rose:  { pilih: 'bg-rose-600 border-rose-600 text-white', lembut: 'bg-rose-50/70 border-rose-200', lencana: 'bg-rose-600 text-white', garis: 'border-l-rose-500' },
    slate: { pilih: 'bg-slate-500 border-slate-500 text-white', lembut: 'bg-slate-50 border-slate-200', lencana: 'bg-slate-500 text-white', garis: 'border-l-slate-400' },
};
