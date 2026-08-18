/**
 * Pemeriksa tampilan: hal-hal yang rusak tanpa menimbulkan galat.
 *
 * Uji PHPUnit membuktikan server menjawab benar. Ia tidak pernah tahu
 * bahwa tulisan sebuah tombol keluar dari kotaknya, bahwa ikon menumpuk
 * di atas labelnya, atau bahwa empat kolom tabel berada di luar layar dan
 * tidak dapat digulir. Ketiganya pernah terjadi di aplikasi ini dalam satu
 * hari, ketiganya lolos seluruh 1.551 uji, dan ketiganya ditemukan oleh
 * orang yang membukanya di ponselnya.
 *
 * Berkas ini memeriksa keempat kelas kesalahan itu dengan menggambar
 * halamannya sungguh-sungguh di peramban:
 *
 *   TATA LETAK TERTIMPA  kelas menyebut flex/grid, peramban menggambar
 *                        yang lain. Inilah yang terjadi ketika sebuah
 *                        aturan global menyentuh `display`: setiap
 *                        `<a class="flex …">` menjadi inline, dan ikon
 *                        bilah samping menumpuk di atas labelnya — di
 *                        seluruh modul sekaligus.
 *
 *   MELUAP TAK TERGULIR  elemen melewati tepi layar tanpa satu pun
 *                        leluhur yang dapat digulir. Isinya bukan sekadar
 *                        tersembunyi; ia tidak dapat dijangkau siapa pun.
 *
 *   SASARAN TERLALU KECIL  kendali di bawah 24px, batas terendah
 *                        WCAG 2.2 AA (2.5.8).
 *
 *   KENDALI TANPA LABEL  input tanggal dan select yang tidak menyebut
 *                        apa pun kepada pembaca layar.
 *
 * Menjalankannya:
 *
 *     php artisan serve --port=8899 &
 *     node tools/periksa-tampilan.mjs surel@contoh.id sandi
 *
 * Sengaja TIDAK dijalankan otomatis oleh deploy maupun CI: ia menuntut
 * aplikasi yang hidup beserta datanya, dan pemeriksa yang gagal karena
 * lingkungannya sendiri akan segera dimatikan orang. Jalankan setelah
 * menyentuh CSS, tata letak, atau komponen yang dipakai banyak halaman.
 */
/*
  Playwright diimpor secara dinamis, dan SENGAJA tidak dicatat sebagai
  dependensi proyek.

  `npm ci` berjalan pada setiap deploy di VPS. Menambahkan Playwright ke
  package.json berarti tiap deploy mengunduh peramban berukuran ratusan
  megabita untuk sesuatu yang tidak pernah dijalankan di sana — memperlambat
  penerbitan demi alat yang hanya dipakai saat mengembangkan.

  Yang belum memasangnya menerima kalimat yang menyebut cara memasangnya,
  bukan jejak tumpukan.
*/
let chromium, devices;
try {
  /* Playwright berupa modul CommonJS: `await import()` menaruh ekspornya
     di bawah `.default`, bukan langsung di ruang namanya. Keduanya
     ditangani supaya berkas ini tidak bergantung pada bentuk pemasangan. */
  const pw = await import(process.env.EQ_PLAYWRIGHT ?? 'playwright');
  ({ chromium, devices } = pw.chromium ? pw : pw.default);
} catch {
  console.error(
    'Playwright belum terpasang.\n\n'
    + '    npm i -D playwright && npx playwright install chromium\n\n'
    + 'Atau tunjuk pemasangan yang sudah ada:\n\n'
    + '    EQ_PLAYWRIGHT=/jalur/ke/playwright/index.js node tools/periksa-tampilan.mjs …\n',
  );
  process.exit(2);
}

const ALAMAT = process.env.EQ_ALAMAT ?? 'http://127.0.0.1:8899';
const SUREL  = process.argv[2];
const SANDI  = process.argv[3];

if (!SUREL || !SANDI) {
  console.error('Pakai: node tools/periksa-tampilan.mjs <surel> <sandi>');
  process.exit(2);
}

const HALAMAN = [
  '/dashboard', '/hazard', '/inspeksi', '/temuan', '/dokumen', '/gudang/barang',
  '/energi', '/smkp', '/tpkkp', '/ko', '/penirisan', '/geoteknik', '/lingkungan',
  '/izin-kerja', '/peledakan', '/biaya', '/konservasi', '/operasi-tambang',
  '/pemeliharaan', '/angkutan/muatan', '/miners', '/courses', '/personalia/direktori',
];

/** Dijalankan DI DALAM halaman; tidak boleh menyentuh apa pun dari luar. */
function periksaHalaman() {
  const lebar = window.innerWidth;
  const hasil = { tertimpa: [], meluap: [], kecil: [], tanpaLabel: [] };

  const dapatDigulir = (el) => {
    let a = el.parentElement;
    while (a && a !== document.body) {
      const s = getComputedStyle(a);
      if (s.overflowX === 'auto' || s.overflowX === 'scroll') return true;
      a = a.parentElement;
    }
    return false;
  };

  document.querySelectorAll('[class*="flex"], [class*="grid"]').forEach((el) => {
    const kelas = ' ' + String(el.className) + ' ';
    const d = getComputedStyle(el).display;
    if (d === 'none') return;                       // tersembunyi, bukan tertimpa
    const mauFlex = /\s(flex|inline-flex)\s/.test(kelas);
    const mauGrid = /\s(grid|inline-grid)\s/.test(kelas);
    if (mauFlex && !d.includes('flex')) hasil.tertimpa.push(`flex→${d}  ${el.tagName.toLowerCase()}.${String(el.className).slice(0, 40)}`);
    if (mauGrid && !d.includes('grid')) hasil.tertimpa.push(`grid→${d}  ${el.tagName.toLowerCase()}.${String(el.className).slice(0, 40)}`);
  });

  document.querySelectorAll('body *').forEach((el) => {
    const b = el.getBoundingClientRect();
    if (!(b.width > 0 && b.right > lebar + 2)) return;
    const s = getComputedStyle(el);
    if (s.position === 'absolute' || s.position === 'fixed') return;   // hiasan
    if (dapatDigulir(el)) return;
    hasil.meluap.push(`${el.tagName.toLowerCase()} "${(el.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 30)}" kanan=${Math.round(b.right)}`);
  });

  document.querySelectorAll('button, a[href], summary').forEach((el) => {
    const b = el.getBoundingClientRect();
    if (b.width > 0 && b.height > 0 && b.height < 24) {
      hasil.kecil.push(`${Math.round(b.height)}px "${(el.innerText || '').trim().slice(0, 24)}"`);
    }
  });

  document.querySelectorAll('input:not([type=hidden]):not([type=submit]), select, textarea').forEach((el) => {
    if (el.type === 'text' || el.tagName === 'TEXTAREA') return;       // biasanya berplaceholder
    if (el.labels?.length || el.getAttribute('aria-label') || el.closest('label')
        || el.getAttribute('placeholder') || el.getAttribute('title')) return;
    hasil.tanpaLabel.push(`${el.tagName.toLowerCase()}[${el.type || ''}]`);
  });

  Object.keys(hasil).forEach((k) => { hasil[k] = [...new Set(hasil[k])]; });
  return hasil;
}

const peramban = await chromium.launch({
  executablePath: process.env.EQ_CHROMIUM,
  args: ['--no-sandbox'],
});

let cacat = 0;

for (const [nama, opsi] of [
  ['ponsel ', { ...devices['Pixel 5'] }],
  ['desktop', { viewport: { width: 1280, height: 900 } }],
]) {
  const ctx = await peramban.newContext(opsi);
  const p = await ctx.newPage();

  await p.goto(`${ALAMAT}/login`, { waitUntil: 'networkidle' });
  await p.fill('input[type=email]', SUREL);
  await p.fill('input[type=password]', SANDI);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle' }), p.click('button[type=submit]')]);

  console.log(`\n═══════════ ${nama} (${p.viewportSize().width}px)`);

  for (const jalur of HALAMAN) {
    let r;
    try {
      await p.goto(ALAMAT + jalur, { waitUntil: 'networkidle', timeout: 25000 });
      await p.waitForTimeout(120);
      r = await p.evaluate(periksaHalaman);
    } catch (e) {
      console.log(`  ${jalur.padEnd(24)} TIDAK TERBUKA — ${e.message.split('\n')[0].slice(0, 60)}`);
      cacat++;
      continue;
    }

    const baris = [];
    if (r.tertimpa.length)   baris.push(`tata letak tertimpa: ${r.tertimpa.slice(0, 2).join(' | ')}`);
    if (r.meluap.length)     baris.push(`meluap tak tergulir: ${r.meluap.slice(0, 2).join(' | ')}`);
    if (r.kecil.length)      baris.push(`sasaran <24px: ${r.kecil.slice(0, 3).join(' | ')}`);
    if (r.tanpaLabel.length) baris.push(`kendali tanpa label: ${r.tanpaLabel.length}`);

    if (baris.length) {
      cacat += baris.length;
      console.log(`  ${jalur.padEnd(24)} ${baris.join('\n' + ' '.repeat(27))}`);
    }
  }

  await p.close();
}

await peramban.close();

console.log(cacat === 0
  ? '\nBersih: tidak ada tata letak tertimpa, tidak ada yang meluap tanpa dapat digulir,\n'
    + 'tidak ada sasaran di bawah 24px, dan tidak ada kendali tanpa label.'
  : `\n${cacat} temuan. Tiap barisnya menyebut halaman dan elemennya.`);

process.exit(cacat === 0 ? 0 : 1);
