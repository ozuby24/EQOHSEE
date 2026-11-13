# Update 13 — Audit SMKP baru dan Kelayakan Karyawan (Miners) baru

```bash
# Di VPS, dari /var/www/EQOHSEE (atau dari mesin lokal: bash deploy/kirim.sh)
cp database/database.sqlite ~/cadangan-sebelum-update-13.sqlite   # pgsql: pg_dump
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan optimize:clear
php artisan storage:link        # hanya bila public/storage belum ada
```
`deploy/deploy.sh` mengerjakan semua langkah itu (termasuk cadangan database
otomatis sebelum migrasi, `npm ci`, dan `storage:link`) — pakai `kirim.sh`
bila biasanya deploy dari mesin lokal. Tanpa `storage:link`, logo pada kartu
cetak mengembalikan 403.

## Sebelum migrasi — wajib
Data SMKP lama **dipindahkan ke struktur baru**. Cadangkan database, lalu
**latih migrasinya pada salinan data produksi** dulu. Data lama yang tidak
punya padanan disimpan utuh di tabel arsip `smkp_arsip_eqohsee`
(kolom `asli`); jangan dihapus sebelum audit lama diperiksa di aplikasi baru.
Jalur pembaruan dari versi sebelumnya sudah dilatih dengan data contoh di
SQLite, PostgreSQL 16, dan MariaDB 10.11. Migrasi selesai tanpa galat,
jumlah datanya sama di ketiganya, dan `migrate:rollback` juga berjalan.
MySQL 8 belum diuji.

## Audit SMKP (menu: Audit SMKP)
Modul diganti dengan alur audit baru (porting Aurorasys). Menu sampingnya:
**Daftar Audit**, **Audit Baru**, **Tindak Lanjut Temuan**, **Kriteria &
Acuan**. Langkah per audit dibuka dari halaman audit itu sendiri:
Tahap I (Permulaan Audit), Rencana Audit, Rapat, Penilaian per elemen,
Bukti, Temuan, Formulir, Laporan. Status audit berjalan lewat tiga tahap:
Tahap I — Permulaan Audit, Tahap II — Audit Lapangan, dan Pelaporan.
Kriteria memakai `resources/data/smkp/elemen.json`: 7 elemen, 100 butir,
nilai maksimal 339.

- **Cetak:** paket tujuh formulir (Rencana Audit, Kriteria Audit,
  Kesesuaian, Ketidaksesuaian & Tindak Lanjut, Rekapitulasi, Rekomendasi &
  OFI, Rencana Tindak Lanjut), ditambah berita acara dan daftar hadir rapat.
- **Asisten AI** (rekomendasi, akar masalah, RTP): hidup setelah kunci API
  diisi di **Pusat Kendali → Integrasi AI** (`/admin/ai`). Tanpa kunci,
  asisten mati; bagian lain tidak terpengaruh.
- **Kop formulir per perusahaan:** Admin → Kelola Perusahaan → isian
  **Kop Formulir Audit SMKP**. Kosong berarti kop bawaan EQOHSEE.
- **Peran:** dipegang `users.audit_role` — `auditor` (menyusun audit) atau
  `company` (auditi: unggah bukti dan tindak lanjut). Migrasi memberi peran
  auditor kepada **semua akun non-admin yang belum berperan audit**.
  **Tinjau** di Admin → Kelola Pengguna (isian *Peran Audit*) siapa yang
  seharusnya auditor dan siapa auditi; auditi harus terhubung ke perusahaan.
- **Akun nonaktif kini benar-benar tertutup** di semua halaman, termasuk
  sesi yang sedang terbuka dan cookie "Ingat saya" (middleware
  `PastikanAktif`). Sebelumnya akun nonaktif masih bisa bekerja.
- **Pengingat tindak lanjut:** `smkp:pengingat-tindak-lanjut` mengirim
  surel batas waktu ke akun auditi setiap hari pukul 07.15 WIB. Perlu
  `MAIL_*` yang sungguhan di `.env` dan cron penjadwal (lihat bawah).
  Sakelar dan pratinjaunya ada di halaman **Pengingat Tindak Lanjut**
  (`/admin/pengingat`).
- **Yang sudah tidak ada:** Dasbor SMKP (Performa SMKP) dan fitur Banding.
  Metode & Sampel serta OFI tidak lagi berupa halaman menu sendiri —
  keduanya kini bagian Rencana Audit dan Formulir Rekomendasi & OFI.

## Kelayakan Karyawan (menu: Miners)
Modul dibangun ulang. Alurnya: **MCU → Induksi → Mine Permit → SIMPER**,
dengan SIMPER Lanjutan (Penambahan Unit, Upgrade, Perpanjangan SIMPER, dan
Perpanjangan Mine Permit). Penyetuju tiap alur:

| Alur | Penyetuju berurutan |
|---|---|
| MCU | Pengaju → Dokter Pemeriksa |
| Induksi, Upgrade | Pengaju → HSE (Verifikasi) |
| Mine Permit, SIMPER, Penambahan Unit | Pengaju → HSE → Kepala Teknik Tambang |
| Perpanjangan SIMPER / Mine Permit | Pengaju → Dokter → HSE → KTT |

**Penyetuju diisi dengan NAMA per IUP** di **Miners → Administrasi → Alur
Persetujuan**. Selama satu IUP belum lengkap, pengajuannya tidak dapat
dikirim dan persetujuan tidak punya siapa-siapa untuk bertindak.

- **Identitas kartu:** Miners → Master Data → Perusahaan (hanya
  administrator): *Logo grup (kartu)* dan *Tanda tangan KTT*.
- **Kartu:** Miners → Monitoring → Cetak Kartu — CR80 54 × 86 mm atau
  lembar A4, menurut tata cetak yang disimpan.
- **Verifikasi publik:** kode QR di kartu membuka `/v/{kode}` tanpa login
  (30 permintaan per menit). Kodenya HMAC-SHA256 dari **`APP_KEY`** —
  mengganti `APP_KEY` membuat seluruh QR yang sudah tercetak tidak
  valid. Jangan diganti tanpa mencetak ulang kartu.
- **Monitoring:** Miners → Monitoring → **Masa Berlaku**.
- **Pengingat surel:** `miners:pengingat` setiap hari 07.25 waktu tambang
  (zona aplikasi, bawaan Asia/Makassar) untuk MCU, Induksi, Mine Permit,
  dan SIMPER yang berakhir ≤ 60 hari atau lewat. Isi di `.env`:
  `MINERS_SUREL_OHSE` (kotak surel tim OHSE) dan `MINERS_SUREL_TEMBUSAN`
  (tembusan tetap, dipisah koma). `miners:sinkron` (06.55, juga otomatis)
  menyelaraskan masa berlaku Mine Permit dengan MCU dan menandai yang
  kedaluwarsa.
- **Sekali jalan (opsional):** usulan masa berlaku SIM untuk SIMPER lama:
  `php artisan miners:deteksi-sim --uji-coba` (hanya menampilkan), lalu
  tanpa `--uji-coba` untuk menyimpan usulan. Tidak menimpa data apa pun.
- **Upload Data Lama:** Miners → Master Data → Upload Data Lama, dua templat
  Excel (Mine Permit dan SIMPER), untuk administrator dan HSE perusahaan.
  Daftar pada halaman Miners juga dapat diekspor ke Excel.
- **Siapa melihat apa:** admin dan HSE IUP melihat semua dalam jangkauannya.
  Pengguna lain hanya melihat karyawan yang mereka daftarkan, dokumen yang
  mereka ajukan, dan dokumen yang menunggu persetujuan mereka. Ini berlaku
  di daftar, monitoring, dasbor, ekspor, dan lampiran. Hasil MCU, catatan
  dokter, rujukan, sertifikat MCU, dan surat rujukan hanya untuk admin,
  HSE/OHSE, paramedis, dan dokter penyetuju. Nomor SIM disamarkan bagi
  selain admin/HSE. Cetak kartu hanya untuk admin/HSE.
- **Pendaftar karyawan** kini dicatat di kolom `dibuat_oleh`. Versi lama
  menyimpannya di `user_id`, kolom yang juga dipakai HR untuk menautkan
  akun layanan mandiri karyawan. Akibatnya, di data lama, pendaftar bisa
  tampak sebagai akun milik karyawan pertama yang ia daftarkan. Periksa di
  HR karyawan yang tertaut ke akun admin, HSE, atau staf administrasi, lalu
  lepaskan tautan yang keliru.

## Penjadwal
Dua pengingat di atas hanya berjalan bila `schedule:run` dipanggil tiap
menit oleh cron (satu baris, jalur mengikuti direktori aplikasi di VPS):
```
* * * * * cd /var/www/EQOHSEE && php artisan schedule:run >> /dev/null 2>&1
```
