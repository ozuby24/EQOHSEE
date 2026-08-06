# Update 11 — Halaman Depan Profesional

```bash
cd ~/storage/downloads && unzip -o eqohsee-update11.zip -d ~/
cp -r ~/eqohsee-update11/. ~/eqohsee/
cd ~/eqohsee && npm run build && php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```
Tanpa migrasi baru. Buka `http://127.0.0.1:8000/` (tanpa login).

## Halaman depan baru (landing)
Sebelumnya `/` langsung melempar ke login. Sekarang ada halaman depan lengkap:

1. **Navigasi lengket** — logo, tautan Modul/Fitur/Cara Kerja, tombol Masuk.
2. **Hero** — ilustrasi tambang terbuka berundak, judul besar, 4 angka kunci
   (194 item · 7 elemen · 6 modul · 24/7), dua tombol aksi.
3. **Enam modul** — LMS & TPKKP bertanda **Aktif**; SMKP, Hazard Report, SIGAP,
   ISO bertanda **Segera** — jelas apa saja isi platform.
4. **Enam fitur unggulan** — sertifikat ber-barcode, penilaian TPKKP, kuesioner
   bebas akses, evaluasi trainer, kunci jawaban aman, kendali penuh admin.
5. **Cara kerja** — 4 langkah bernomor.
6. **CTA penutup** + footer.

## Halaman login diperkaya
Panel kiri tidak lagi kosong: ada **ilustrasi tambang**, daftar **6 modul**
dengan ikon dan penanda Aktif/Segera, serta 4 angka kunci. Logo bisa diklik
untuk kembali ke halaman depan.

## Ilustrasi tambang — dibuat sebagai SVG
Tidak memakai file foto atau layanan gambar daring, melainkan **SVG buatan sendiri**
(`resources/views/partials/art-mine.blade.php`): tambang terbuka berundak,
jalan angkut, ekskavator, dua truk angkut, kabut debu. Alasannya: tajam di semua
ukuran layar, ringan, dan **tetap tampil walau tanpa internet** — penting karena
aplikasi ini dijalankan lokal di Termux.

Kalau nanti ingin memakai foto asli tambang milik perusahaan, cukup ganti isi
partial itu dengan `<img>`; seluruh halaman lain tidak perlu diubah.

## Standar tipografi angka
Sistem `.stat` / `.num` dari update 10 dipakai konsisten di halaman depan, dan
menjadi patokan tetap untuk semua modul berikutnya (ISO, Hazrep, KO, SIGAP).
