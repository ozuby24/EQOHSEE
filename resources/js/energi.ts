/**
 * Format angka gaya Indonesia, dipakai seluruh halaman Energy.
 *
 * Sepadan dengan App\Support\Energi::ringkas() di server: angka besar
 * dipendekkan dengan sufiks T/B/M/K agar kartu KPI tetap terbaca.
 */
export function angka(n: number, desimal = 0): string {
  return n.toLocaleString('id-ID', { minimumFractionDigits: desimal, maximumFractionDigits: desimal });
}

export function ringkas(n: number, desimal = 1): string {
  const abs = Math.abs(n);
  if (abs >= 1e12) return angka(n / 1e12, desimal) + 'T';
  if (abs >= 1e9) return angka(n / 1e9, desimal) + 'B';
  if (abs >= 1e6) return angka(n / 1e6, desimal) + 'M';
  if (abs >= 1e3) return angka(n / 1e3, desimal) + 'K';
  return angka(n, desimal);
}

export function rupiah(n: number, desimal = 1): string {
  return 'Rp ' + ringkas(n, desimal);
}
