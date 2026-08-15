# Update 12 — Enam Pilar di Halaman Depan

```bash
cd ~/eqohsee && git pull && npm run build && php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```
Tanpa migrasi baru. Buka `http://127.0.0.1:8000/` (tanpa login).

## Seksi baru: "Enam Pilar"
Halaman depan kini menjelaskan **makna nama EQOHSEE** — sesuatu yang sebelumnya
tak pernah muncul untuk pengunjung publik. Ditambahkan satu seksi (setelah Hero,
sebelum Modul) dengan latar gelap `cam-ink`:

1. **Wordmark hidup** — tulisan `EQOHSEE` besar, tiap huruf diberi warna pilarnya
   (E·Q·O·H·S·E·E → Energy, Quality, Occupational Health, Safety, Environment,
   Engineering). O dan H sama-sama warna Occupational Health.
2. **Enam kartu pilar** — ikon heksagon, nama, dan deskripsi tiap pilar, dengan
   aksen garis atas berwarna pilar. Ikon sama dengan yang dipakai halaman `/pilar`.
3. Tautan **Pilar** ditambahkan ke navigasi lengket.

## Satu sumber data
Seksi ini membaca langsung dari registry **`App\Support\Pillars::all()`** —
bukan menyalin ulang data. Jadi kalau warna/nama/deskripsi pilar diubah di satu
tempat, halaman depan dan halaman `/pilar` ikut berubah serentak.

## Tetap konsisten
Memakai token dan komponen yang sudah ada (`glass-panel`, `card-hover`,
`font-display`, warna `cam-*`, sistem `.stat`). Tidak ada dependensi baru,
tidak ada file gambar eksternal — ikon pilar berupa SVG inline sehingga tetap
tampil walau tanpa internet (penting untuk penggunaan lokal di Termux).
