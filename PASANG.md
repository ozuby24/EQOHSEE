# Update 16 — Celah keamanan npm ditutup, Node 24 LTS

```bash
# Di VPS, dari /var/www/EQOHSEE
bash deploy/deploy.sh
```
Tanpa migrasi. `npm audit` kini 0 (sebelumnya 7: 2 critical, 4 high,
1 moderate) — Vue 3.5.45, Vite 8.3.8, concurrently 9.2.5, beserta
shell-quote, source-map-js, dan postcss-selector-parser di dalamnya.

Peringatan `EBADENGINE` (pdfjs-dist, nanoid) muncul karena VPS memakai
Node 20 bawaan Debian, yang sudah habis masa dukungannya April 2026.
`deploy.sh` kini memeriksa versinya: bila di bawah 22.13, ia memasang
Node 24 LTS dari repositori NodeSource SEKALI (sebelum situs diturunkan),
lalu deploy berikutnya melewatinya. Bila pemasangannya gagal, repositori
itu dicabut lagi, Node lama dipulihkan, dan deploy tetap berlanjut.
Jalur lain: `EQOHSEE_NODE_MAYOR=22 bash deploy/deploy.sh`.

Memasangnya sendiri tanpa deploy:
```bash
curl -fsSL https://deb.nodesource.com/setup_24.x | bash -
apt-get remove -y npm; apt-get install -y nodejs
node -v   # v24.x
```

# Update 15 — Fitur audit DBEST: susunan kegiatan, simpan otomatis, akses, grup

```bash
# Di VPS, dari /var/www/EQOHSEE (atau dari mesin lokal: bash deploy/kirim.sh)
cp database/database.sqlite ~/cadangan-sebelum-update-15.sqlite   # pgsql: pg_dump
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan optimize:clear
```
Dua migrasi baru, keduanya hanya MENAMBAH: tabel `audit_akses` dan kolom
`lingkup`, `perusahaan_luar`, `jenis_luar` pada `smkp_audits`. Data lama
tidak diubah.

## Rencana Audit — susunan kegiatan terstruktur
- Tiap sesi: tanggal, waktu, **metode**, **area**, **fungsi/jabatan**,
  **kriteria** (mis. `I.1, III.2.1` atau `1.1, 3.2.1`), kegiatan lain, dan
  auditor. Lembar cetak dan unduhan Word menyusunnya seperti formulir:
  "1. Audit Elemen I Kebijakan — Sub Elemen 1.1, 1.5". Baris lama tetap terbaca.
- **Unduh templat Excel / Unggah Excel** di bagian susunan kegiatan. Hasil
  unggahan masuk ke formulir dan baru tersimpan setelah Simpan.
- Sampel per kriteria boleh multi-baris. Tulis "Wawancara 5 orang:" lalu
  rinciannya ("2 orang KTT", "3 orang Operator") — jumlahnya dicocokkan.

## Simpan otomatis formulir audit
Tahap I, Rencana Audit, Penilaian, Isian Laporan, dan Formulir tersimpan
otomatis beberapa detik setelah berhenti mengetik ("Tersimpan otomatis
pukul …"). Bila isian sudah diubah akun/tab lain, simpan otomatis berhenti
agar tidak saling menimpa — muat ulang halaman, atau tekan Simpan untuk
menimpanya. Ketikan yang belum tersimpan ditawarkan kembali saat halaman
dibuka lagi.

## Word
Unduhan Word kini Arial 11 pt; **Laporan Audit Arial 12 pt**.

## Aturan Akses Audit (Pusat Kendali → Aturan Akses Audit)
Per perusahaan: **auditor** yang boleh mengaudit dan **pengunggah data**
yang mengunggah bukti/menjawab temuan. Perusahaan tanpa penugasan tetap
memakai aturan lama — tidak ada yang berubah sampai Anda mulai menugaskan.
Auditor dari luar grup (mis. konsultan) yang ditugaskan ikut menjangkau
audit perusahaan itu saja. Satu akun tidak boleh menjadi auditor sekaligus
pengunggah perusahaan yang sama.

## Audit SMKP → Perusahaan Grup (administrator)
Audit perusahaan satu grup dengan **nama diketik manual** (Pemegang IUP /
Jasa pertambangan). Isinya lengkap seperti audit biasa, tetapi tidak ikut
Daftar Audit, Dasbor, statistik, tindak lanjut lintas audit, lonceng, dan
pengingat email.

# Update 14 — Dasbor SMKP, RTP Audit Lingkungan, Miners ala DBEST

```bash
# Di VPS, dari /var/www/EQOHSEE (atau dari mesin lokal: bash deploy/kirim.sh)
cp database/database.sqlite ~/cadangan-sebelum-update-14.sqlite   # pgsql: pg_dump
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan optimize:clear
```
Dua migrasi baru, keduanya hanya MENAMBAH (tidak mengubah data lama):
`env_audit_rtps` (tabel baru) dan kolom `sebab_nonaktif`,
`rekomendasi_rekrut`, `nonaktif_oleh` pada `mnr_pekerja` + tabel
`mnr_pelanggaran`.

Acuan modul audit kini **DBEST** (keputusan pemilik, 16 Nov 2026).

## Audit SMKP → Dasbor (baru)
Menu **Audit SMKP → Dasbor** (`/smkp/dasbor`), port dasbor v2 DBEST:
KPI per babak, rata-rata nilai akhir dan selisihnya terhadap tahun
pembanding (pilih tahun atau "tanpa pembanding"), tren empat tahun,
sebaran tingkat, sorotan otomatis, benchmark per perusahaan dengan rincian
tujuh elemen, radar elemen, temuan per elemen & kategori, analisis temuan
& tindak lanjut (Top 10 sub-elemen, status, tren per bulan/kuartal/tahun),
tindakan mendesak, agenda 30 hari, dan **Ekspor Excel** empat lembar.
Hanya audit dalam jangkauan pengguna yang dihitung.

## Audit Lingkungan → RTP (baru)
Tombol **⚑ RTP** di ikhtisar audit (`/audit-lingkungan/{id}/rtp`).
- Kriteria dengan nilai **verifikasi 0–2** otomatis menerbitkan RTP saat
  nilai disimpan; naik ke 3 menutup/menghapusnya.
- Auditee mengisi rencana, PIC, target, lalu mengunggah bukti (otomatis
  diajukan). Auditor (admin/auditor) **Setujui** sekaligus memberi nilai
  baru, atau **Tolak** dengan alasan.
- **Aturan target:** nilai hanya dapat naik bila diverifikasi paling
  lambat pada target selesai RTP. Target yang sudah lewat hanya dapat
  diubah auditor.
- Audit lama: migrasi menerbitkan RTP untuk nilai verifikasi 0–2 yang sudah ada.

## Miners — isi mengikuti DBEST
- **Menu** disusun seperti bilah samping DBEST: Register, Penambahan,
  History, Outstanding, Master Data, Master Penambahan, Monitoring,
  Aktivitas. SPIP/Uji Kelayakan/Unit Commissioning menaut ke modul KO;
  Hazard Report, Inspeksi, Perizinan Kerja, Cuti Tahunan, dan Field Break
  (Pengumuman) menaut ke modulnya masing-masing.
- **Riwayat Karyawan** (Miners → Monitoring): cek orang lewat NIK/nama
  lintas perusahaan **dalam satu grup IUP** (induk + mitranya), daftar
  karyawan nonaktif beserta alasan & rekomendasi rekrut kembali, dan
  rekap pelanggaran. Catatan pelanggaran dari mitra **menunggu
  verifikasi** admin/HSE IUP sebelum terlihat perusahaan lain. NIK
  perusahaan lain disamarkan. Data pribadi (UU 27/2022): hanya untuk
  pertimbangan rekrutmen dan keselamatan kerja.

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
