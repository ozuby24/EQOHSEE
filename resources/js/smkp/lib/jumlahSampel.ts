/*
 * Kalkulator jumlah sampel Rencana Audit (port DBEST #23).
 *
 * Pasangan layar App\Support\SmkpSampel — rumusnya harus sama, karena
 * server menghitung ulang setiap baris "Jumlah sampel: …" saat Rencana
 * disimpan. SmkpSampelTest membandingkan keduanya lewat node.
 */

export type CaraHitung = 'slovin' | 'akar' | 'triangulasi' | 'satu' | 'sensus' | 'persen' | 'manual';

export const CARA_HITUNG: Record<CaraHitung, { label: string; ket: string }> = {
  slovin: { label: 'Rumus Slovin', ket: 'n = N ÷ (1 + N × e²), e = batas kesalahan (bawaan 10%). Untuk populasi besar yang seragam, mis. manpower atau pekerja.' },
  akar: { label: 'Best practice (√N)', ket: 'n = √N dibulatkan ke atas (pola sampling IAF MD 1). Untuk populasi seragam: unit, kendaraan, alat, atau dokumen sejenis.' },
  triangulasi: { label: 'Triangulasi', ket: 'n = 3: satu bukti dari setiap sumber — dokumen/rekaman, wawancara, dan observasi — yang saling menguatkan.' },
  satu: { label: 'One sample one population', ket: 'Satu sampel dari setiap populasi: N = jumlah populasi/kelompok (departemen, shift, area, jenis unit), n = N.' },
  sensus: { label: 'Sensus (total sampling)', ket: 'n = N: seluruh populasi diperiksa. Untuk populasi kecil atau berisiko tinggi.' },
  persen: { label: 'Persentase', ket: 'n = p% × N dibulatkan ke atas (bawaan 10%).' },
  manual: { label: 'Ditetapkan auditor', ket: 'n diisi auditor sesuai pertimbangan profesional (tidak melebihi N).' },
};

export const OBJEK_POPULASI = ['manpower', 'pekerja', 'pengawas', 'unit', 'kendaraan', 'alat', 'dokumen', 'rekaman', 'prosedur', 'izin kerja', 'area', 'lokasi kerja', 'departemen', 'shift', 'kontraktor'];

const POLA_JUMLAH = /^jumlah\s+sampel\s*:?\s*(\d{1,7})\s*(?:dari|\/)\s*(?:populasi\s*)?(\d{1,7})\s*([^()]{0,60}?)\s*(?:\(([^()]{0,80})\))?\s*$/iu;

/** PHP round(x, 1) untuk bilangan positif. */
const bulat1 = (x: number) => Math.round(x * 10) / 10;

export function paramHitung(cara: string, param: unknown): number | null {
  const teks = typeof param === 'string' ? param.replace(',', '.').trim() : param;
  const x = teks === null || teks === undefined || teks === '' || isNaN(Number(teks)) ? null : Number(teks);
  if (cara === 'slovin') return x === null ? 10 : Math.max(1, Math.min(30, bulat1(x)));
  if (cara === 'persen') return x === null ? 10 : Math.max(1, Math.min(100, bulat1(x)));
  if (cara === 'manual') return x === null ? null : Math.round(x);
  return null;
}

/** Jumlah sampel n (1..N); null bila populasi atau cara tidak sah. Bilangan bulat, sama dengan server. */
export function hitungSampel(cara: string, populasi: unknown, param: unknown = null): number | null {
  const N = Math.trunc(Number(populasi));
  if (!Number.isFinite(N) || N < 1 || N > 9999999 || !(cara in CARA_HITUNG)) return null;

  const p = paramHitung(cara, param);
  let n: number;
  switch (cara) {
    case 'slovin': {
      const e10 = Math.round((p as number) * 10);
      const bagi = 1000000 + N * e10 * e10;
      n = Math.floor((N * 1000000 + bagi - 1) / bagi);
      break;
    }
    case 'akar':
      n = Math.ceil(Math.sqrt(N));
      while (n * n < N) n++;
      while (n > 1 && (n - 1) * (n - 1) >= N) n--;
      break;
    case 'triangulasi':
      n = 3;
      break;
    case 'persen':
      n = Math.floor((N * Math.round((p as number) * 10) + 999) / 1000);
      break;
    case 'manual':
      if (p === null) return null;
      n = p;
      break;
    default:
      n = N;
  }
  return Math.max(1, Math.min(Math.trunc(n), N));
}

const persen = (x: number) => String(Math.round(x * 10) / 10).replace('.', ',');

export function labelHitung(cara: string, param: unknown = null): string {
  const p = paramHitung(cara, param);
  switch (cara) {
    case 'slovin': return `Slovin, e ${persen(p as number)}%`;
    case 'akar': return 'best practice √N';
    case 'triangulasi': return 'triangulasi 3 sumber';
    case 'satu': return 'one sample one population';
    case 'sensus': return 'sensus';
    case 'persen': return `persentase ${persen(p as number)}%`;
    default: return 'ditetapkan auditor';
  }
}

export function objekPopulasi(objek: unknown): string {
  return String(objek ?? '').replace(/[()[\]]/g, ' ').replace(/\s+/gu, ' ').trim().slice(0, 60);
}

export function barisJumlah(cara: string, populasi: unknown, objek: unknown = '', param: unknown = null): string | null {
  const n = hitungSampel(cara, populasi, param);
  if (n === null) return null;
  const obj = objekPopulasi(objek);
  return `Jumlah sampel: ${n} dari populasi ${Math.trunc(Number(populasi))}${obj ? ' ' + obj : ''} (${labelHitung(cara, param)})`;
}

export interface BacaJumlah { n: number; populasi: number; objek: string; cara: CaraHitung; param: string | number | null }

export function bacaJumlah(baris: string): BacaJumlah | null {
  const l = String(baris ?? '').replace(/[ \t ]+/gu, ' ').trim();
  const m = l.match(POLA_JUMLAH);
  if (!m) return null;
  const teks = (m[4] ?? '').trim().toLowerCase();
  const x = teks.match(/(\d+(?:[.,]\d+)?)\s*%/u);
  const angka = x ? x[1].replace(',', '.') : null;
  let cara: CaraHitung = 'manual';
  let param: string | number | null = Number(m[1]);
  if (teks.includes('slovin')) { cara = 'slovin'; param = angka; }
  else if (/√|akar|best\s*practice|iaf/u.test(teks)) cara = 'akar';
  else if (teks.includes('triangulasi')) cara = 'triangulasi';
  else if (/one\s*sample|satu\s*sampel/u.test(teks)) cara = 'satu';
  else if (/sensus|total\s*sampling|seluruh/u.test(teks)) cara = 'sensus';
  else if (angka !== null || teks.includes('persen')) { cara = 'persen'; param = angka; }
  return { n: Number(m[1]), populasi: Number(m[2]), objek: objekPopulasi(m[3] ?? ''), cara, param };
}

/** Tulis baris jumlah ke isi kotak sampel: menggantikan baris jumlah yang sudah ada, atau ditambahkan di akhir. */
export function pasangBarisJumlah(teks: string, baris: string): string {
  const daftar = (teks ?? '').split(/\r\n|\r|\n/);
  const i = daftar.findIndex((b) => bacaJumlah(b.replace(/^\s*(?:[-•*]\s+)?/u, '')) !== null);
  if (i >= 0) {
    const awal = daftar[i].match(/^\s*(?:[-•*]\s+)?/u)?.[0] ?? '';
    daftar[i] = awal + baris;
    return daftar.join('\n');
  }
  const isi = (teks ?? '').replace(/\s+$/u, '');
  return isi ? `${isi}\n${baris}` : baris;
}
