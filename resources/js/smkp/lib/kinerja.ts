/*
 * Kinerja keselamatan Tahap I (port DBEST #23): pembacaan angka gaya
 * Indonesia, perbandingan dengan rata-rata nasional Surat Edaran, saran
 * faktor penyesuaian 4, 5, 7, dan kalkulator FR · SR · ASR · MFR.
 *
 * Pasangan layar App\Support\SmkpTahap::bilangan() / saranFaktor() —
 * SmkpRataNasionalTest membandingkan keduanya lewat node.
 */

export interface RataNasional { fr: number; sr: number; asr: number; mfr: number; nomor: string; sumber: string; tahun: number; tanggal?: string | null }
export type Saran = { kecelakaan: boolean | null; penyakit: boolean | null; pak: boolean | null };

/** "0,09" / "1.234,5" / "133.91" → angka; kosong/tidak sah → null. */
export function bilangan(v: unknown): number | null {
  let t = String(v ?? '').replace(/[\s ]/g, '');
  if (t === '') return null;
  if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.');
  if (!/^[-+]?(\d+\.?\d*|\.\d+)([eE][-+]?\d+)?$/.test(t)) return null;
  return Number(t);
}

/** Kondisi 4 (kecelakaan), 5 (penyakit), 7 (PAK) dari Data Kinerja; null = data belum cukup. */
export function saranFaktor(kinerja: Record<string, unknown>, n: Pick<RataNasional, 'fr' | 'sr' | 'asr' | 'mfr'>): Saran {
  const atas = (a: 'fr' | 'asr', b: 'sr' | 'mfr') => {
    const x = bilangan(kinerja[a]);
    const y = bilangan(kinerja[b]);
    return x === null || y === null ? null : x > n[a] && y > n[b];
  };
  const kptk = bilangan(kinerja.kptk);
  const pak = bilangan(kinerja.pak);
  return {
    kecelakaan: atas('fr', 'sr'),
    penyakit: atas('asr', 'mfr'),
    pak: kptk === null && pak === null ? null : (kptk || 0) > 0 || (pak || 0) > 0,
  };
}

/** Selisih terhadap rata-rata nasional untuk satu nilai: 'atas' | 'sama' | 'bawah' | null. */
export function banding(v: unknown, nasional: number): 'atas' | 'sama' | 'bawah' | null {
  const x = bilangan(v);
  if (x === null) return null;
  return x > nasional ? 'atas' : x === nasional ? 'sama' : 'bawah';
}

export interface IsianRate { jam: unknown; kecelakaan: unknown; hariHilang: unknown; hariAbsen: unknown; kasusSakit: unknown }

/**
 * Rumus per 1.000.000 jam kerja orang: FR = kecelakaan × 1.000.000 ÷ jam,
 * SR = hari hilang × 1.000.000 ÷ jam, ASR = hari absen sakit × 1.000.000 ÷ jam,
 * MFR = kasus sakit × 1.000.000 ÷ jam. Dibulatkan dua desimal.
 */
export function hitungRate(i: IsianRate): { fr: number | null; sr: number | null; asr: number | null; mfr: number | null } {
  const jam = bilangan(i.jam);
  const per = (x: unknown) => {
    const v = bilangan(x);
    return jam === null || jam <= 0 || v === null || v < 0 ? null : Math.round((v * 1_000_000 / jam) * 100) / 100;
  };
  return { fr: per(i.kecelakaan), sr: per(i.hariHilang), asr: per(i.hariAbsen), mfr: per(i.kasusSakit) };
}

/** Angka untuk diisikan ke kotak kinerja: koma desimal, tanpa nol berlebih. */
export function tulisAngka(v: number | null): string {
  if (v === null) return '';
  return String(Math.round(v * 100) / 100).replace('.', ',');
}
