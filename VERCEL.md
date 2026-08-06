# Penerapan ke Vercel

## Kenapa build gagal sebelumnya

```
✓ built in 1.26s
Error: No Output Directory named "dist" found after the Build completed
```

Vite-nya **berhasil** — yang gagal adalah langkah sesudahnya. Repo ini tidak
punya `vercel.json`, jadi Vercel menebak sendiri jenis proyeknya. Karena
`package.json` berisi `vite build`, Vercel menyimpulkan "ini proyek Vite murni"
lalu mencari hasil build di folder `dist/`.

Laravel tidak memakai `dist/`. `laravel-vite-plugin` menaruh hasilnya di
**`public/build/`** — persis seperti yang terlihat di log:

```
public/build/manifest.json
public/build/assets/app-BtB_hcLN.css
public/build/assets/app-B9qO1Jfl.js
```

Jadi berkasnya ada, hanya Vercel mencarinya di tempat yang salah.

## Yang diperbaiki

| Berkas | Fungsi |
|---|---|
| `vercel.json` | Mematikan deteksi otomatis (`"framework": null`), menetapkan `outputDirectory: "public"`, dan mendaftarkan runtime PHP. |
| `api/index.php` | Titik masuk serverless — mengarahkan direktori tulis Laravel ke `/tmp` sebelum framework boot. |
| `.vercelignore` | Menghindari unggahan `vendor/`, `node_modules/`, dan berkas rahasia. |

Vercel **tidak punya runtime PHP resmi**, jadi dipakai runtime komunitas
[`vercel-php`](https://github.com/vercel-community/php) versi `0.9.0`
(mendukung PHP 8.4/8.5 — cocok dengan `"php": "^8.3"` di `composer.json`).

Alur permintaan: berkas statis di `public/` dilayani langsung; sisanya
diteruskan ke `api/index.php` → `public/index.php` → Laravel.

## Variabel lingkungan yang WAJIB diisi

Isi di **Vercel → Settings → Environment Variables** (jangan ditulis di repo):

| Variabel | Keterangan |
|---|---|
| `APP_KEY` | Wajib. Ambil dari `php artisan key:generate --show` |
| `APP_URL` | Alamat produksi, mis. `https://eqohsee.vercel.app` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` `DB_PORT` `DB_DATABASE` `DB_USERNAME` `DB_PASSWORD` | Kredensial Supabase |

Sisanya (`APP_ENV`, `LOG_CHANNEL=stderr`, dll.) sudah diatur di `vercel.json`.

Skema tabel sudah tersedia di `supabase_schema.sql` — jalankan sekali di
SQL Editor Supabase. Migrasi Laravel tidak bisa dijalankan otomatis saat
build Vercel.

---

## Batasan yang BELUM teratasi

Perbaikan di atas membuat **build lolos dan aplikasi boot**. Tapi Vercel itu
_serverless_ dengan sistem berkas **hanya-baca** (kecuali `/tmp`, yang terhapus
tiap permintaan). Tiga hal berikut tetap tidak akan berfungsi:

### 1. Unggah berkas — rusak

Lima fitur menyimpan berkas ke cakram lokal:

| Berkas | Baris | Fitur |
|---|---|---|
| `CourseController.php` | 96 | Gambar kursus |
| `SignatoryController.php` | 45 | Spesimen tanda tangan |
| `HazardController.php` | 238 | Foto laporan bahaya |
| `Admin/CompanyController.php` | 76 | Logo perusahaan |
| `InspectionController.php` | 159 | Foto inspeksi |

Semuanya memakai `->store(..., 'public')` → masuk ke `storage/app/public`,
yang di Vercel berarti `/tmp` dan **hilang begitu permintaan selesai**.
Selain itu tautan `public/storage` belum pernah dibuat, dan `artisan
storage:link` tidak bisa dijalankan pada sistem berkas hanya-baca.

**Solusi:** pindahkan ke penyimpanan objek — Supabase Storage atau S3 —
lalu ganti disk `'public'` menjadi `'s3'` di lima tempat itu.

### 2. Sertifikat & cadangan JSON — tidak tersimpan

`TpkkpLanjutController::cadangkan()` menulis salinan JSON ke
`storage_path('app')`. Fungsi ini sudah dibungkus `try/catch` sehingga
**tidak akan menjatuhkan aplikasi** — cadangannya saja yang diam-diam hilang.

### 3. Basis data

`DB_CONNECTION=sqlite` bawaan tidak mungkin dipakai (berkasnya hanya-baca dan
sementara). Harus Postgres/Supabase seperti tabel di atas.

---

## Pertimbangan

Kalau tujuannya aplikasi berjalan penuh **termasuk unggah foto bahaya dan
inspeksi**, Vercel bukan pilihan yang nyaman: butuh penyimpanan objek terpisah
dan tetap ada batas waktu eksekusi. Host yang menjalankan PHP secara persisten
— **Railway, Render, Fly.io**, atau VPS biasa — bisa memakai repo ini apa
adanya tanpa mengubah satu baris kode pun.

Vercel tetap masuk akal kalau yang dikejar adalah pratinjau publik yang cepat
dan gratis untuk halaman depan serta modul yang tidak mengunggah berkas.
