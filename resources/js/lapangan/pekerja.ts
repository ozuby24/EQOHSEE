/**
 * Pemasangan pekerja latar mode lapangan dan pemanasan simpanannya.
 *
 * Pekerja latarnya (public/sw-lapangan.js) menyimpan halaman yang PERNAH
 * dibuka. Yang belum pernah dibuka tidak akan ada saat sinyal hilang —
 * dan yang paling dibutuhkan tanpa sinyal justru layar yang jarang
 * dibuka sebelumnya: formulir lapor dan P2H. Maka selagi masih ada
 * sinyal, layar-layar itu diambil diam-diam sekali, beserta berkas
 * komponennya.
 */

const CAKUPAN = '/lapangan';
const INTI = ['/lapangan', '/lapangan/lapor', '/lapangan/tugas', '/lapangan/p2h', '/lapangan/modul', '/lapangan/profil', '/lapangan/izin', '/lapangan/sertifikat'];
const JEDA_MS = 20 * 60 * 1000;

export async function pasangPekerja(): Promise<void> {
  if (!('serviceWorker' in navigator) || !window.isSecureContext) return;
  try {
    await navigator.serviceWorker.register('/sw-lapangan.js', { scope: CAKUPAN });
  } catch {
    /* Tanpa pekerja latar, mode lapangan tetap jalan selama ada sinyal. */
  }
}

/** Hapus seluruh salinan halaman — dipanggil saat keluar dan di halaman masuk. */
export async function bersihkanSimpanan(): Promise<void> {
  try {
    if ('caches' in window) {
      const nama = await caches.keys();
      await Promise.all(nama.filter((n) => n.startsWith('eqohsee-lapangan-halaman') || n.startsWith('eqohsee-lapangan-data')).map((n) => caches.delete(n)));
    }
    navigator.serviceWorker?.controller?.postMessage('bersihkan');
  } catch { /* tidak ada yang perlu dibersihkan */ }
}

async function ambilData(alamat: string, versi: string | null): Promise<any> {
  const r = await fetch(alamat, {
    credentials: 'same-origin',
    headers: { 'X-Inertia': 'true', 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html, application/xhtml+xml', ...(versi ? { 'X-Inertia-Version': versi } : {}) },
  });
  return r.ok ? r.json().catch(() => null) : null;
}

/**
 * Ambil layar inti selagi ada sinyal, paling sering sekali tiap 20 menit.
 *
 * Hanya bila pekerja latarnya sudah mengendalikan halaman ini — tanpa
 * itu pengambilan ini hanya membuang kuota tanpa menyimpan apa pun.
 */
export async function hangatkan(versi: string | null): Promise<void> {
  if (!navigator.onLine || !navigator.serviceWorker?.controller) return;

  try {
    const lalu = Number(sessionStorage.getItem('lapangan-hangat') ?? 0);
    if (Date.now() - lalu < JEDA_MS) return;
    sessionStorage.setItem('lapangan-hangat', String(Date.now()));
  } catch { /* sessionStorage tertutup — tetap hangatkan */ }

  /* Berkas komponen tiap layar: diunduh sekarang, disimpan pekerja latar. */
  const komponen = import.meta.glob('../Pages/Lapangan/*.vue');
  await Promise.allSettled(Object.values(komponen).map((muat) => muat()));

  const p2h = await ambilData('/lapangan/p2h', versi).catch(() => null);
  const unit: string[] = (p2h?.props?.unit ?? []).map((x: any) => x.url).filter(Boolean).slice(0, 30)
    .map((u: string) => new URL(u, location.origin).pathname);

  for (const alamat of [...INTI.filter((a) => a !== '/lapangan/p2h'), ...unit]) {
    await ambilData(alamat, versi).catch(() => null);
  }

  /* Satu salinan HTML beranda sebagai pintu masuk saat aplikasi dibuka tanpa sinyal. */
  await fetch('/lapangan', { credentials: 'same-origin', headers: { Accept: 'text/html' } }).catch(() => null);
}
