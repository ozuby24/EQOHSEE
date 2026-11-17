# Panduan Pasang di Localhost (VS Code)

Panduan singkat untuk menjalankan EQOHSEE di komputer sendiri lewat VS Code. Ikuti urut dari atas.

## 1. Prasyarat

Pastikan sudah terpasang di komputer:

- **PHP 8.4** atau lebih baru (`php -v`)
- **Composer 2.x** (`composer --version`)
- **Node.js 22** atau lebih baru + **npm** (`node -v`, `npm -v`)
- **Git**

## 2. Clone repo

```bash
git clone https://github.com/ozuby24/EQOHSEE.git
cd EQOHSEE
```

Buka folder ini di VS Code (`code .`).

## 3. Pasang dependency

```bash
composer install
npm install
```

## 4. Siapkan file .env

```bash
cp .env.example .env
php artisan key:generate
```

Tidak perlu diubah apa-apa — bawaannya sudah dikonfigurasi untuk lokal:
- `DB_CONNECTION=sqlite` (tidak perlu install MySQL/PostgreSQL)
- `MAIL_MAILER=log` (email tidak benar-benar terkirim, cuma dicatat di `storage/logs/laravel.log`)
- `GEMINI_API_KEY` boleh dikosongkan (asisten AI di halaman Bantuan otomatis nonaktif tanpa kunci ini)

## 5. Siapkan database

```bash
touch database/database.sqlite
php artisan migrate
php artisan db:seed
php artisan db:seed --class=DemoSeeder
```

Perintah terakhir mengisi data contoh (perusahaan, kursus, kuis, SOP, berita) supaya tampilan tidak kosong.

## 6. Jadikan akun contoh sebagai admin (opsional, biar bisa lihat semua fitur)

```bash
php artisan tinker --execute="\$u = App\Models\User::first(); \$u->is_admin = true; \$u->email_verified_at = now(); \$u->save();"
```

Login pakai:
- Email: `test@example.com`
- Password: `password`

## 7. Bangun aset frontend

```bash
npm run build
```

## 8. Buat folder storage bisa diakses publik

```bash
php artisan storage:link
```

## 9. Jalankan

Paling gampang, satu perintah ini menjalankan server + queue + log + Vite sekaligus:

```bash
composer run dev
```

Atau manual, cukup:

```bash
php artisan serve
```

Buka **http://127.0.0.1:8000** di browser.

## Yang TIDAK perlu dipakai untuk localhost

File-file berikut khusus untuk deploy ke hosting produksi (Vercel/VPS) — aman diabaikan saat kerja di lokal:

- `vercel.json`, `api/index.php`, `.vercelignore` — konfigurasi Vercel
- `deploy/`, `VPS.md` — script & panduan untuk VPS
- `VERCEL.md` — panduan deploy ke Vercel

## Kalau ada masalah

- **`SQLSTATE... no such table`** → jalankan ulang `php artisan migrate`
- **Halaman blank / CSS tidak muncul** → jalankan `npm run build` lagi, lalu `php artisan optimize:clear`
- **Port 8000 sudah dipakai** → jalankan `php artisan serve --port=8001` lalu buka `http://127.0.0.1:8001`

---

## Riwayat Update

### Update 12 — Enam Pilar di Halaman Depan

```bash
cd ~/eqohsee && git pull && npm run build && php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```
Tanpa migrasi baru. Buka `http://127.0.0.1:8000/` (tanpa login).

**Seksi baru: "Enam Pilar"**
Halaman depan kini menjelaskan makna nama EQOHSEE — sesuatu yang sebelumnya
tak pernah muncul untuk pengunjung publik. Ditambahkan satu seksi (setelah Hero,
sebelum Modul) dengan latar gelap `cam-ink`:

1. **Wordmark hidup** — tulisan `EQOHSEE` besar, tiap huruf diberi warna pilarnya
   (E·Q·O·H·S·E·E → Energy, Quality, Occupational Health, Safety, Environment,
   Engineering). O dan H sama-sama warna Occupational Health.
2. **Enam kartu pilar** — ikon heksagon, nama, dan deskripsi tiap pilar, dengan
   aksen garis atas berwarna pilar. Ikon sama dengan yang dipakai halaman `/pilar`.
3. Tautan **Pilar** ditambahkan ke navigasi lengket.

Seksi ini membaca langsung dari registry `App\Support\Pillars::all()` —
bukan menyalin ulang data. Memakai token dan komponen yang sudah ada
(`glass-panel`, `card-hover`, `font-display`, warna `cam-*`, sistem `.stat`).
Tidak ada dependensi baru, ikon pilar berupa SVG inline sehingga tetap
tampil walau tanpa internet (penting untuk penggunaan lokal di Termux).
