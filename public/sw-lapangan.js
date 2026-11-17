/*
  Pekerja latar mode lapangan — supaya /lapangan tetap terbuka tanpa sinyal.

  Cakupannya hanya /lapangan. Halaman web lengkap tetap selalu bertanya ke
  server; yang boleh tampil dari simpanan perangkat hanyalah layar
  lapangan yang memang dirancang untuk dipakai di pit.

    - Berkas build (/build, /fonts, /brand): simpanan dulu. Namanya
      berhash, jadi isi yang sama selalu bernama sama.
    - Halaman /lapangan dan data Inertia-nya: jaringan dulu. Bila jaringan
      tidak menjawab dalam beberapa detik, yang terakhir tersimpan dipakai
      sementara jaringan tetap ditunggu untuk memperbaruinya.

  Isinya milik orang yang sedang masuk. Simpanannya dihapus saat keluar
  dan saat halaman masuk dibuka (pesan 'bersihkan'), sehingga ponsel yang
  dipakai bergantian tidak memperlihatkan data orang sebelumnya.

  Kiriman (POST) tidak disentuh di sini — antreannya ada di halaman,
  lihat resources/js/lapangan/kotakKeluar.ts.
*/
const VERSI = 'v1';
const HALAMAN = 'eqohsee-lapangan-halaman-' + VERSI;
const DATA = 'eqohsee-lapangan-data-' + VERSI;
const ASET = 'eqohsee-lapangan-aset-' + VERSI;
const TUNGGU_MS = 6000;
const MAKS_ASET = 160;

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    const nama = await caches.keys();
    await Promise.all(nama
      .filter((n) => n.startsWith('eqohsee-lapangan-') && ![HALAMAN, DATA, ASET].includes(n))
      .map((n) => caches.delete(n)));
    await self.clients.claim();
  })());
});

self.addEventListener('message', (e) => {
  if (e.data === 'bersihkan') {
    e.waitUntil(Promise.all([caches.delete(HALAMAN), caches.delete(DATA)]));
  }
});

self.addEventListener('fetch', (e) => {
  const r = e.request;
  if (r.method !== 'GET') return;

  const u = new URL(r.url);
  if (u.origin !== self.location.origin) return;

  if (/^\/(build|fonts|brand)\//.test(u.pathname)) {
    e.respondWith(asetDulu(r));
    return;
  }

  if (!(u.pathname === '/lapangan' || u.pathname.startsWith('/lapangan/'))) return;

  const inertia = r.headers.get('X-Inertia') === 'true';

  if (r.mode === 'navigate' || (!inertia && (r.headers.get('Accept') || '').includes('text/html'))) {
    e.respondWith(jaringanDulu(r, HALAMAN, r.mode === 'navigate'));
    return;
  }

  /* Muat ulang sebagian tidak disimpan: isinya potongan, bukan halaman. */
  if (inertia && !r.headers.has('X-Inertia-Partial-Data')) {
    e.respondWith(jaringanDulu(r, DATA, false));
  }
});

async function asetDulu(r) {
  const simpanan = await caches.open(ASET);
  const ada = await simpanan.match(r);
  if (ada) return ada;

  const jawab = await fetch(r);
  if (jawab.ok && jawab.type === 'basic') {
    await simpanan.put(r, jawab.clone());
    const kunci = await simpanan.keys();
    for (const k of kunci.slice(0, Math.max(0, kunci.length - MAKS_ASET))) await simpanan.delete(k);
  }
  return jawab;
}

async function jaringanDulu(r, nama, halaman) {
  const simpanan = await caches.open(nama);
  const kunci = new URL(r.url).href;

  const jaringan = fetch(r).then(async (jawab) => {
    /* Yang dialihkan (sesi habis → /login) dan yang bukan 200 tidak
       disimpan: menyimpannya berarti memajang halaman masuk atau halaman
       galat sebagai isi layar lapangan. */
    if (jawab.status === 200 && !jawab.redirected && jawab.type === 'basic') {
      await simpanan.put(kunci, jawab.clone());
    }
    return jawab;
  });

  const tersimpan = await simpanan.match(kunci, { ignoreVary: true });

  try {
    if (!tersimpan) return await jaringan;

    /* Ada salinan: jaringan diberi beberapa detik. Di sinyal yang tipis,
       menunggu tanpa batas sama saja dengan layar kosong. */
    const lewat = new Promise((ok) => setTimeout(() => ok(null), TUNGGU_MS));
    const menang = await Promise.race([jaringan.catch(() => null), lewat]);
    return menang || tersimpan;
  } catch {
    if (halaman) {
      const beranda = await simpanan.match(new URL('/lapangan', self.location.origin).href, { ignoreVary: true });
      return beranda || halamanLuring();
    }
    return Response.error();
  }
}

function halamanLuring() {
  return new Response(
    '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    + '<title>Luring — EQOHSEE</title><body style="margin:0;font-family:system-ui,sans-serif;background:#F5F7F9;color:#0B1117;display:grid;place-items:center;min-height:100vh;padding:24px">'
    + '<div style="max-width:340px"><div style="font-size:22px;font-weight:700">Belum ada sinyal</div>'
    + '<p style="font-size:15px;line-height:1.5;color:#3A4450">Halaman ini belum pernah dibuka di perangkat ini, jadi belum tersimpan. '
    + 'Laporan dan P2H yang sudah disusun tetap aman di perangkat dan terkirim saat sinyal kembali.</p>'
    + '<a href="/lapangan" style="display:inline-flex;align-items:center;height:48px;padding:0 18px;border-radius:12px;background:#F57C00;color:#0B1117;font-weight:700;text-decoration:none">Coba lagi</a></div>',
    { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } },
  );
}
