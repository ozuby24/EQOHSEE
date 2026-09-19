/**
 * Tangkapan layar tiap fitur, satu folder per fitur — bahan jual.
 *
 * ── DIJALANKAN ULANG, BUKAN DISIMPAN SEKALI ──
 *
 * Tampilan berubah tiap pekan. Kumpulan gambar yang dibuat sekali lalu
 * dipakai bertahun akan memajang layar yang sudah tidak ada lagi kepada
 * calon pembeli — dan yang pertama menyadarinya adalah orang yang baru
 * saja membayar. Karena itu yang disimpan di repositori ini adalah
 * PEMBUATNYA, dan gambarnya dapat diterbitkan ulang kapan saja.
 *
 * Daftar halamannya diturunkan dari App\Support\Menu — sumber yang sama
 * dengan yang menggambar bilah samping. Diketik ulang di sini, ia akan
 * berbeda isi cepat atau lambat, dan yang terlewat adalah halaman yang
 * tidak pernah ditawarkan kepada siapa pun.
 *
 * Cara pakai:
 *   php artisan serve --port=8123
 *   php artisan tinker --execute='...'   (menulis modul.json, lihat README)
 *   PETA=modul.json TUJUAN=docs/jual node tools/tembak-fitur.mjs
 *
 * Ubahsuai lewat lingkungan:
 *   HANYA=smkp,hazrep   hanya modul itu
 *   MUTU=70             mutu JPEG
 *   ALAMAT, SUREL, SANDI
 */
import { chromium } from '/opt/node22/lib/node_modules/playwright/index.mjs';
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const PETA   = process.env.PETA   || 'modul.json';
const TUJUAN = process.env.TUJUAN || 'docs/jual';
const ALAMAT = process.env.ALAMAT || 'http://127.0.0.1:8123';
const SUREL  = process.env.SUREL  || 'admin@uji.test';
const SANDI  = process.env.SANDI  || 'rahasia123';
const MUTU   = Number(process.env.MUTU || 70);
const HANYA  = (process.env.HANYA || '').split(',').filter(Boolean);

const rupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

const modul = JSON.parse(readFileSync(PETA, 'utf8'));
const kunci = HANYA.length ? HANYA : Object.keys(modul);

const b = await chromium.launch({
  executablePath: process.env.PERAMBAN || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
  args: ['--no-sandbox'],
});
const p = await b.newPage({ viewport: { width: 1440, height: 900 } });

await p.goto(`${ALAMAT}/login`, { waitUntil: 'domcontentloaded' });
await p.waitForTimeout(1200);
await p.fill('#email', SUREL);
await p.fill('#password', SANDI);
await p.click('form button[type=submit]');
await p.waitForTimeout(2500);

if (p.url().includes('/login')) {
  console.error('Gagal masuk. Periksa SUREL/SANDI, dan pastikan Turnstile mati di .env setempat.');
  process.exit(1);
}

const bermasalah = [];
let total = 0;

for (const k of kunci) {
  const m = modul[k];
  if (!m) { console.error(`  lewat: ${k} tidak ada di peta`); continue; }

  const dir = join(TUJUAN, k);
  mkdirSync(dir, { recursive: true });

  const daftar = [];
  let n = 0;

  for (const h of m.halaman) {
    n++;
    const slug = String(h.path).replace(/[^a-zA-Z0-9]+/g, '-').replace(/^-|-$/g, '') || 'halaman';
    const nama = `${String(n).padStart(2, '0')}-${slug}.jpg`;
    let status = 'ok';

    try {
      const r = await p.goto(`${ALAMAT}/${h.path}`, { waitUntil: 'domcontentloaded', timeout: 25000 });
      await p.waitForTimeout(1600);

      const kode = r?.status() ?? 0;
      if (kode >= 400) status = `HTTP ${kode}`;

      await p.screenshot({ path: join(dir, nama), fullPage: true, type: 'jpeg', quality: MUTU });
      total++;
    } catch (e) {
      status = 'GAGAL: ' + String(e.message).split('\n')[0].slice(0, 70);
    }

    daftar.push({ berkas: nama, label: h.label, grup: h.grup, path: h.path, status });
    if (status !== 'ok') bermasalah.push(`${k}/${h.path} — ${status}`);
  }

  /* README per folder: yang menjual fitur ini membacanya lebih dulu,
     bukan menebak isi foldernya dari nama berkas. */
  const baris = daftar.map((d) =>
    `| \`${d.berkas}\` | ${d.label} | ${d.grup || '—'} | ${d.status === 'ok' ? '' : '⚠ ' + d.status} |`).join('\n');

  writeFileSync(join(dir, 'README.md'),
`# ${m.jual}

${m.semboyan ? `_${m.semboyan}_\n` : ''}
${m.ket ? m.ket + '\n' : ''}
**Harga katalog:** ${m.harga > 0 ? rupiah(m.harga) : 'belum ditetapkan'}
**Halaman:** ${daftar.length}

Tangkapan layar di bawah memakai data contoh (\`php artisan demo:pasang\`),
bukan data pelanggan.

| Berkas | Halaman | Kelompok | Catatan |
|---|---|---|---|
${baris}

---
Diterbitkan ulang dengan \`node tools/tembak-fitur.mjs\` — lihat \`docs/jual/README.md\`.
`);

  writeFileSync(join(dir, 'daftar.json'),
    JSON.stringify({ kunci: k, ...m, halaman: daftar }, null, 2));

  console.log(`${k.padEnd(13)} ${String(daftar.length).padStart(3)} halaman`);
}

console.log(`\nSelesai: ${total} gambar. Bermasalah: ${bermasalah.length}`);
bermasalah.forEach((x) => console.log('  ', x));

await b.close();
