#!/usr/bin/env node
/**
 * Pemeriksa tipe TypeScript & Vue yang muat di VPS kecil.
 *
 * `vue-tsc --noEmit` memuat SELURUH halaman sekaligus. Pada ukuran proyek
 * sekarang — hampir tiga ratus berkas Vue — satu kali jalan memerlukan
 * sekitar 1,2 GB heap, dan deploy di VPS dengan jatah 1 GB berhenti dengan
 * "JavaScript heap out of memory". Kodenya tidak salah; pemeriksanya yang
 * tidak muat. Menambah swap hanya menunda: tiap halaman baru menambah
 * kira-kira 4,5 MB, dan batasnya akan terlampaui lagi.
 *
 * Di sini halaman diperiksa BERGILIR, beberapa puluh sekali jalan. Tiap
 * jalan memuat halaman-halamannya beserta semua yang diimpornya — Layout,
 * Components, types.ts — jadi tidak ada berkas yang terlewat; yang
 * berbeda hanya puncak memorinya. Terukur: dasar bersama ≈ 260 MB, lalu
 * ≈ 4,5 MB per halaman.
 *
 * Ukuran tiap giliran dihitung dari jatah memori (EQOHSEE_TIPE_MB). Bila
 * satu giliran tetap kehabisan memori — halaman yang jauh lebih berat
 * daripada rata-ratanya — giliran itu dibelah dua dan diulang, sampai
 * tinggal beberapa halaman. Baru bila sekecil itu pun tidak muat,
 * kegagalannya dilaporkan sebagai kekurangan memori, dengan kalimat yang
 * sama seperti sebelumnya sehingga deploy.sh tetap membedakannya dari
 * galat tipe.
 *
 * Tanpa EQOHSEE_TIPE_MB, atau bila jatahnya cukup untuk semuanya, yang
 * dijalankan tetap satu kali seperti `npm run typecheck`.
 *
 * Keluar 0 bila bersih, 1 bila ada galat tipe, 2 bila kehabisan memori.
 */
import { spawnSync } from 'node:child_process';
import { readdirSync, rmSync, writeFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const AKAR = fileURLToPath(new URL('..', import.meta.url));
const VUE_TSC = join(AKAR, 'node_modules', 'vue-tsc', 'bin', 'vue-tsc.js');
const CONFIG = join(AKAR, 'tsconfig.periksa.tmp.json');

const DASAR_MB = 260;         // grafik bersama: Layout, Components, types.ts, d.ts
const PER_HALAMAN_MB = 4.5;
const RUANG_GC = 1.5;         // heap harus lebih lapang daripada yang terpakai
const GILIRAN_TERKECIL = 5;

const jatah = Number(process.env.EQOHSEE_TIPE_MB || 0) || null;

function berkas(dir) {
  const out = [];
  for (const n of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, n.name);
    if (n.isDirectory()) out.push(...berkas(p));
    else if (/\.(vue|ts)$/.test(n.name) && !n.name.includes('.bak')) out.push(relative(AKAR, p));
  }
  return out.sort();
}

const semua = berkas(join(AKAR, 'resources', 'js'));
const halaman = semua.filter((f) => f.startsWith(join('resources', 'js', 'Pages')));
const lainnya = semua.filter((f) => !halaman.includes(f));

const HEAP_HABIS = /heap out of memory|Reached heap limit|JavaScript heap/;

/** Jalankan vue-tsc atas sekumpulan berkas; null = seluruh proyek. */
function jalankan(daftar) {
  const args = [VUE_TSC, '--noEmit', '--pretty', 'false'];

  if (daftar) {
    writeFileSync(CONFIG, JSON.stringify({ extends: './tsconfig.json', include: daftar }, null, 1));
    args.push('-p', CONFIG);
  }

  const env = { ...process.env };
  if (jatah) env.NODE_OPTIONS = `--max-old-space-size=${jatah}`;

  const r = spawnSync(process.execPath, args, { cwd: AKAR, env, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  const keluaran = `${r.stdout ?? ''}${r.stderr ?? ''}`;

  return {
    ok: r.status === 0,
    habis: HEAP_HABIS.test(keluaran) || r.signal === 'SIGABRT' || r.status === 134,
    keluaran,
  };
}

/**
 * Galat tipe dari keluaran vue-tsc, tanpa duplikat.
 *
 * Berkas bersama (Layout, types.ts) ikut termuat di setiap giliran, jadi
 * galat di sana muncul sekali per giliran; yang ditampilkan cukup sekali.
 */
const galat = new Map();
function kumpulkan(keluaran) {
  let kini = null;
  for (const baris of keluaran.split('\n')) {
    if (/\(\d+,\d+\): error TS\d+/.test(baris)) {
      kini = baris.trim();
      if (!galat.has(kini)) galat.set(kini, []);
    } else if (kini && /^\s+\S/.test(baris)) {
      galat.get(kini).push(baris);
    } else {
      kini = null;
    }
  }
}

function selesai(kode) {
  rmSync(CONFIG, { force: true });
  process.exit(kode);
}

function laporGalat() {
  if (!galat.size) return false;
  for (const [kepala, isi] of galat) {
    console.log(kepala);
    for (const b of isi) console.log(b);
  }
  console.log(`\n${galat.size} galat tipe.`);
  return true;
}

const perluSemua = DASAR_MB + halaman.length * PER_HALAMAN_MB;

/* Jalur cepat: jatahnya lapang, atau tidak disebut sama sekali. */
if (!jatah || jatah >= perluSemua * RUANG_GC) {
  const r = jalankan(null);
  if (r.ok) selesai(0);
  if (r.habis && !jatah) {
    console.log(r.keluaran.split('\n').filter((b) => HEAP_HABIS.test(b)).join('\n'));
    selesai(2);
  }
  if (!r.habis) {
    kumpulkan(r.keluaran);
    if (!laporGalat()) console.log(r.keluaran);
    selesai(1);
  }
  /* Kehabisan memori padahal perkiraannya cukup: turun ke jalur bergilir. */
}

/* EQOHSEE_TIPE_GILIRAN memaksa ukuran awal — untuk menguji pembelahan. */
const ukuran = Number(process.env.EQOHSEE_TIPE_GILIRAN || 0)
  || Math.max(GILIRAN_TERKECIL, Math.floor((jatah / RUANG_GC - DASAR_MB) / PER_HALAMAN_MB));
const antrean = [];
for (let i = 0; i < halaman.length; i += ukuran) antrean.push(halaman.slice(i, i + ukuran));

console.log(`Memeriksa ${semua.length} berkas bergilir: ${antrean.length} giliran × ≤ ${ukuran} halaman, jatah ${jatah} MB per giliran.`);

/* Berkas di luar Pages (Components, Layouts, *.ts) ikut giliran pertama,
   supaya yang tidak diimpor halaman mana pun tetap diperiksa. */
let luarSudah = false;
let ke = 0;

while (antrean.length) {
  const giliran = antrean.shift();
  const daftar = [join('resources', 'js', 'env.d.ts'), ...(luarSudah ? [] : lainnya), ...giliran];
  const mulai = Date.now();
  const r = jalankan(daftar);

  if (r.habis) {
    if (giliran.length <= GILIRAN_TERKECIL) {
      console.log(`Giliran ${giliran.length} halaman tetap kehabisan memori pada jatah ${jatah} MB.`);
      console.log('FATAL ERROR: Reached heap limit — JavaScript heap out of memory');
      selesai(2);
    }
    const tengah = Math.ceil(giliran.length / 2);
    antrean.unshift(giliran.slice(0, tengah), giliran.slice(tengah));
    console.log(`  giliran ${giliran.length} halaman kehabisan memori — dibelah dua dan diulang`);
    continue;
  }

  luarSudah = true;
  ke++;
  kumpulkan(r.keluaran);
  if (!r.ok && !/error TS\d+/.test(r.keluaran)) {
    /* Gagal tanpa galat tipe dan tanpa kehabisan memori: tampilkan
       keluarannya apa adanya, jangan dilaporkan sebagai bersih. */
    console.log(r.keluaran);
    selesai(1);
  }
  console.log(`  giliran ${ke}: ${giliran.length} halaman — ${r.ok ? 'bersih' : 'ada galat'} (${Math.round((Date.now() - mulai) / 1000)} dtk)`);
}

selesai(laporGalat() ? 1 : 0);
