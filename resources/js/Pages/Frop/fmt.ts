/** Penulisan angka FROP: koma desimal, tanpa nol di belakang. */
export const detik = (v: number | null | undefined, d = 2): string =>
  v === null || v === undefined ? '–'
    : Number(v.toFixed(d)).toLocaleString('id-ID', { maximumFractionDigits: d });

export const bertanda = (v: number | null | undefined): string =>
  v === null || v === undefined ? '–' : (v > 0 ? '+' : v < 0 ? '−' : '') + detik(Math.abs(v));

export const persen = (v: number | null | undefined, d = 0): string =>
  v === null || v === undefined ? '–'
    : (v * 100).toLocaleString('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }) + '%';

const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export const tgl = (iso: string | null | undefined): string => {
  if (!iso) return '–';
  const [y, m, d] = iso.split('-').map(Number);
  return `${d} ${BULAN[m - 1]} ${y}`;
};
