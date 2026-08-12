/**
 * Penulisan angka gaya Indonesia.
 *
 * Dipakai bersama oleh halaman gudang. Angka stok ditulis tanpa nol
 * ekor — "12" bukan "12,00" — sebab satuan gudang umumnya bulat dan
 * dua desimal di setiap baris membuat kolomnya sulit dibaca sekilas.
 * Desimalnya tetap muncul ketika memang ada.
 */
export function angka(n: number | string | null | undefined): string {
  if (n === null || n === undefined || n === '') return '—';

  const x = typeof n === 'string' ? parseFloat(n) : n;
  if (!isFinite(x)) return '—';

  return x.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

/** Angka bertanda, untuk selisih dan penyesuaian. */
export function bertanda(n: number): string {
  return (n > 0 ? '+' : n < 0 ? '−' : '') + angka(Math.abs(n));
}
