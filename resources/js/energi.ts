/**
 * Format angka & label modul Energi & Karbon.
 *
 * Pembulatan HANYA saat tampil: tCO₂e dua desimal, GJ dan TOE
 * satu desimal, rupiah tanpa desimal. Angka null digambar "—", bukan nol:
 * nol tCO₂e terbaca "tidak beremisi", padahal null berarti "faktornya
 * tidak tersedia, tidak dapat dihitung".
 */
export function n(v: unknown, d = 1): string {
  if (v === null || v === undefined || v === '') return '—';
  const x = Number(v);
  if (!isFinite(x)) return '—';

  return x.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
}

/** Angka besar dipendekkan: rb, jt, M, T. */
export function ringkas(v: unknown, d = 1): string {
  if (v === null || v === undefined || v === '') return '—';
  const x = Number(v);
  if (!isFinite(x)) return '—';
  const a = Math.abs(x);
  if (a >= 1e12) return n(x / 1e12, d) + ' T';
  if (a >= 1e9) return n(x / 1e9, d) + ' M';
  if (a >= 1e6) return n(x / 1e6, d) + ' jt';
  if (a >= 1e3) return n(x / 1e3, d) + ' rb';

  return n(x, a < 10 ? 2 : 0);
}

export const rp = (v: unknown) => (v === null || v === undefined ? '—' : 'Rp ' + ringkas(v));

export function tgl(v: unknown): string {
  if (!v) return '—';
  const d = new Date(String(v).length <= 10 ? String(v) + 'T00:00:00' : String(v));

  return isNaN(d.getTime()) ? '—' : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

export const persen = (v: unknown, d = 1) => (v === null || v === undefined ? '—' : n(v, d) + '%');

/** Nama parameter registri faktor; satuannya ada di kolom nilai. */
export const PARAMETER: Record<string, string> = {
  densitas: 'Densitas', ncv: 'Nilai kalor (NCV)', ef_co2: 'Faktor emisi CO₂', ef_ch4: 'Faktor emisi CH₄', ef_n2o: 'Faktor emisi N₂O',
  ef_grid: 'Faktor emisi grid', ef_kontrak: 'Faktor emisi kontrak', ef_residual: 'Faktor emisi residual', harga: 'Harga',
  persen_fame: 'Campuran FAME', gwp_ar5: 'GWP (AR5)', gwp_ar6: 'GWP (AR6)', gj_per_toe: 'GJ per TOE', gj_per_sbm: 'GJ per SBM',
  harga_dasar: 'Harga karbon — dasar', harga_rendah: 'Harga karbon — rendah', harga_tinggi: 'Harga karbon — tinggi',
};
export const namaParameter = (k: unknown) => PARAMETER[String(k)] ?? String(k ?? '—');

/** Warna & label status yang dipakai di seluruh modul. */
export const STATUS: Record<string, { label: string; kelas: string }> = {
  draf:          { label: 'Draf',           kelas: 'bg-stone-100 text-stone-600' },
  diajukan:      { label: 'Diajukan',       kelas: 'bg-sky-50 text-sky-700' },
  ditolak:       { label: 'Ditolak',        kelas: 'bg-red-50 text-red-700' },
  terverifikasi: { label: 'Terverifikasi',  kelas: 'bg-emerald-50 text-emerald-700' },
  terkunci:      { label: 'Terkunci',       kelas: 'bg-slate-50 text-slate-700' },
  aktif:         { label: 'Aktif',          kelas: 'bg-emerald-50 text-emerald-700' },
  ditarik:       { label: 'Ditarik',        kelas: 'bg-stone-100 text-stone-600' },
  disetujui:     { label: 'Disetujui',      kelas: 'bg-teal-50 text-teal-700' },
  terbit:        { label: 'Terbit',         kelas: 'bg-emerald-50 text-emerald-700' },
  perlu_revisi:  { label: 'Perlu revisi',   kelas: 'bg-amber-50 text-amber-800' },
  diganti:       { label: 'Diganti',        kelas: 'bg-stone-100 text-stone-600' },
  selesai:       { label: 'Selesai', kelas: 'bg-emerald-50 text-emerald-700' },
  proses:        { label: 'Dalam proses',   kelas: 'bg-sky-50 text-sky-700' },
  belum:         { label: 'Belum',          kelas: 'bg-amber-50 text-amber-800' },
  terlambat:     { label: 'Terlambat',      kelas: 'bg-red-50 text-red-700' },
  tidak_berlaku: { label: 'Tidak berlaku',  kelas: 'bg-stone-100 text-stone-600' },
  terbuka:       { label: 'Terbuka',        kelas: 'bg-sky-50 text-sky-700' },
  verifikasi:    { label: 'Verifikasi',     kelas: 'bg-amber-50 text-amber-800' },
  usulan:        { label: 'Usulan',         kelas: 'bg-sky-50 text-sky-700' },
  disetujui_bod: { label: 'Disetujui BOD',  kelas: 'bg-emerald-50 text-emerald-700' },
  rencana:       { label: 'Rencana',        kelas: 'bg-sky-50 text-sky-700' },
  berjalan:      { label: 'Berjalan',       kelas: 'bg-teal-50 text-teal-700' },
  batal:         { label: 'Batal',          kelas: 'bg-stone-100 text-stone-600' },
  ditindaklanjuti: { label: 'Ditindaklanjuti', kelas: 'bg-teal-50 text-teal-700' },
  tidak_layak:   { label: 'Tidak layak',    kelas: 'bg-stone-100 text-stone-600' },
  direncanakan:  { label: 'Direncanakan',   kelas: 'bg-sky-50 text-sky-700' },
  review:        { label: 'Review',         kelas: 'bg-amber-50 text-amber-800' },
  WAJIB:         { label: 'Wajib manajemen energi', kelas: 'bg-red-50 text-red-700' },
  PERINGATAN:    { label: 'Mendekati ambang', kelas: 'bg-amber-50 text-amber-800' },
  TIDAK_WAJIB:   { label: 'Belum wajib',    kelas: 'bg-emerald-50 text-emerald-700' },
};

export const statusKelas = (s: unknown) => STATUS[String(s)]?.kelas ?? 'bg-stone-100 text-stone-600';
export const statusLabel = (s: unknown) => STATUS[String(s)]?.label ?? String(s ?? '—');

/** RAG dasbor direksi → kelas. */
export const RAG: Record<string, { label: string; kelas: string; garis: string }> = {
  hijau:  { label: 'Sesuai target',     kelas: 'bg-emerald-50 text-emerald-700', garis: '#16A34A' },
  kuning: { label: 'Mendekati batas',   kelas: 'bg-amber-50 text-amber-800',     garis: '#D97706' },
  merah:  { label: 'Melewati target',   kelas: 'bg-red-50 text-red-700',         garis: '#D92D20' },
  netral: { label: 'Tanpa target',      kelas: 'bg-stone-100 text-stone-600',    garis: '#78716C' },
};

function csrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

/** Panggil API JSON internal energi (sesi web). Galat dilempar sebagai pesan yang terbaca. */
export async function json<T = any>(url: string, body?: Record<string, any>, metode = 'POST'): Promise<T> {
  const r = await fetch(url, {
    method: metode,
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(),
               'X-Requested-With': 'XMLHttpRequest' },
    body: metode === 'GET' ? undefined : JSON.stringify(body ?? {}),
  });
  const isi = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(isi?.message ?? `Permintaan gagal (HTTP ${r.status}).`);

  return isi as T;
}
