# Update 22 — Aplikasi baru: MERANTI · Reklamasi & Nursery

```bash
# Di VPS, dari /var/www/EQOHSEE
cp database/database.sqlite ~/cadangan-sebelum-update-22.sqlite   # pgsql: pg_dump
bash deploy/deploy.sh          # migrate + build
php artisan pembelian:katalog  # menambah APP-MERANTI (Rp0, tidak aktif)

# Muat data lembar Google Sheets "MERANTI" — paling mudah lewat halaman
# MERANTI → Data Master → Impor Google Sheets (unggah .xlsx dari ponsel).
# Atau dari terminal, sesudah berkas .xlsx diunggah ke VPS:
php artisan meranti:impor ~/MERANTI.xlsx --perusahaan="Cemerlang Asa Mandiri" --kering   # pratinjau dulu
php artisan meranti:impor ~/MERANTI.xlsx --perusahaan="Cemerlang Asa Mandiri"
```
Satu migrasi baru, hanya MENAMBAH: 25 tabel modul `meranti_*`, tabel
`meranti_log` dan `meranti_setelan`, serta kolom `users.meranti_role`.
Tidak ada tabel lama yang diubah, tidak ada penjadwal baru. Bila nama
perusahaannya cocok dengan lebih dari satu baris, perintah impor
menampilkan daftar ID-nya — ulangi dengan `--perusahaan=ID`.

## MERANTI (`/meranti`, pilar Environment)
Monitoring, Evaluation of Reclamation and Nursery Information —
aplikasi Google Apps Script/Supabase MERANTI v4 dipindahkan utuh ke
EQOHSEE, satu tabel bertipe per modul dengan batas perusahaan.
- **Dashboard Analitik**: total stok bibit, realisasi rehabilitasi DAS
  terhadap target, luas reklamasi dan PKAR, skor keberhasilan Matrik 17;
  tren realisasi per bulan, komposisi P0/P1/P2, realisasi per blok,
  rencana vs realisasi reklamasi, peta spasial, mutasi nursery 30 hari,
  jaminan, dan yang perlu ditangani (stok habis, pengajuan menunggu).
  Saringan tahun.
- **Nursery**: Stok Bibit, Bibit Masuk/Keluar/Kembali/Mati/Rusak, Master
  Tanaman. **Material Stok**: Stok Material, Material Masuk/Keluar.
- **Rehabilitasi DAS**: Progres Mingguan (tahun → bulan → minggu,
  P0 Penanaman · P1 Pemeliharaan I · P2 Pemeliharaan II), **Aktual
  Pencapaian** (Σ realisasi ÷ Σ Target Blok per blok, garis 100%),
  Target Blok, Arsip File Capaian (tombol folder Google Drive), dan Aktual
  Penanaman (lembar "Actual Penanaman" versi lama, tidak dibuang).
- **Reklamasi**: Rencana Reklamasi, **Penilaian Keberhasilan Matrik 17**
  (60% penatagunaan + 20% revegetasi + 20% penyelesaian; > 80 Baik,
  60–80 Sedang, < 60 Buruk — dihitung server, tidak dapat diketik),
  Jaminan Reklamasi.
- **Administrasi**: Surat, Pengajuan Dana & Control Budget, Berita Acara,
  Periode Pengajuan. **Data Master**: Project, Pelaksana, Project Vendor.
- **Peta Spasial**: unggah KML / KMZ / GeoJSON — tiap placemark jadi satu
  fitur, MultiPolygon dipecah, luas dihitung bila tidak disebut; peta
  digambar tanpa ubin internet; ekspor GeoJSON.
- **Setiap daftar**: cari, saring tahun/bulan, urut kolom, total, tambah /
  ubah / hapus (terkonfirmasi), saran isian dari data master, CSV, cetak.
- **Empat peran MERANTI** (`users.meranti_role`, diatur di Data Master →
  Pengguna & Peran atau form admin pengguna): Data Master (penuh + data
  master, peran, log, impor), EMC (tambah/ubah/hapus), Crew (tambah),
  Manager (lihat). Tanpa peran = bawaan setara Crew; administrator
  EQOHSEE selalu penuh. Semua perubahan tercatat di Log Aktivitas.

## Data dari Google Sheets "MERANTI"
Data tidak ikut di repositori — data operasional perusahaan tempatnya di
basis data, bukan di riwayat Git. Pindahkan lewat **Data Master → Impor
Google Sheets**:
1. Buka lembar Google Sheets **MERANTI** → File → Download → Microsoft
   Excel (.xlsx). Di aplikasi Google Sheets ponsel: ⋮ → Bagikan & ekspor →
   Simpan sebagai → Excel.
2. Pilih perusahaan tujuan (administrator: pemilih perusahaan di atas),
   unggah berkasnya, tekan **Impor berkas**.
- Setiap lembar ("MERANTI - Stok Bibit", "MERANTI - Peta Spasial", …)
  masuk ke modulnya; nama lembar yang terpotong 31 karakter tetap
  dikenali; lembar "Actual Penanaman" versi lama masuk ke Aktual Penanaman;
  Log Aktivitas lama ikut sebagai riwayat. **Lembar Users tidak dimuat** —
  akun MERANTI lama tidak dipindahkan; beri peran kepada akun EQOHSEE.
- Aman diulang: baris membawa asal (`stok_bibit#7`) dan diperbarui, bukan
  digandakan; data yang diketik langsung di EQOHSEE tidak disentuh.
- Jalan lain: tautan lembar yang dibagikan "siapa saja yang memiliki link"
  (server mengambilnya sendiri), atau **CSV dari tombol Export CSV MERANTI**
  (satu modul per berkas, dikenali dari nama berkasnya).
- Lembar Google Sheets versi Apps Script **tidak memuat Progres Mingguan dan
  Target Blok** (dibuat MERANTI v4 versi Supabase). Bila datanya ada di
  aplikasi MERANTI Supabase, ekspor CSV kedua modul itu dari sana lalu
  unggah di halaman Impor — Aktual Pencapaian baru terisi sesudahnya.

## Sesudah deploy — wajib dikerjakan orang
1. **Impor lembar MERANTI** (langkah di atas) untuk PT Cemerlang Asa
   Mandiri, lalu periksa angkanya di Dashboard Analitik.
2. **Beri peran MERANTI**: minimal satu Data Master per perusahaan; EMC
   untuk yang boleh mengubah/menghapus; Manager untuk yang hanya melihat.
3. **Isi folder Google Drive** arsip capaian di halaman Arsip File Capaian
   (tombol "Atur folder Drive").
4. **Aktifkan APP-MERANTI** di katalog pembelian untuk perusahaan yang
   memakainya.

---

# Update 21 — Aplikasi baru: Rehabilitasi DAS

```bash
# Di VPS, dari /var/www/EQOHSEE
cp database/database.sqlite ~/cadangan-sebelum-update-21.sqlite   # pgsql: pg_dump
bash deploy/deploy.sh          # migrate + build
php artisan pembelian:katalog  # menambah APP-RDAS (Rp0, tidak aktif)
php artisan demo:pasang        # opsional: data contoh rehabilitasi DAS
```
Satu migrasi baru, hanya MENAMBAH tabel `rdas_*` (ppkh, blok, petak,
pantau, kegiatan, penilaian, laporan, kejadian). Tidak ada tabel lama
yang diubah, tidak ada penjadwal baru — fase blok, sisa waktu kewajiban,
dan keadaan laporan semesteran dihitung dari tanggal setiap kali dibaca.

## Rehabilitasi DAS (`/rehab-das`, pilar Environment)
Diadaptasi dari bahan Bimtek RDAS BPDAS Mahakam Berau (24 November 2026)
dan modul RehabDAS, mengacu PP 26/2020, Permen LHK P.59/2019, Permen LHK
7/2021, Permenhut P.60/2009, Permen LHK 10/2022, dan SE MenLHK 1/2024.
- **Kewajiban PPKH**: luas kewajiban dihitung otomatis 1 : 1 luas PAK
  (rasio dapat diubah), batas penyelesaian ½ jangka waktu PPKH (> 5 tahun)
  atau ½N + 1 tahun (≤ 5 tahun), sisa hari aman / mendesak (≤ 180 hari) /
  lewat. Corong kewajiban → ditetapkan → ditanam → diserahterimakan.
- **Lokasi & petak tanam**: blok dengan SK penetapan lokasi, DAS/Sub-DAS,
  fungsi kawasan, ekosistem (daratan, riparian, mangrove, gambut),
  jarak tanam dan kerapatan, komposisi jenis (lokal ≥ 40%, cepat tumbuh
  ≤ 60%), RKP, kontraktor, dan anggaran. Poligon petak dapat ditempel
  sebagai GeoJSON atau daftar "lat, lng"; peta poligon interaktif
  (geser, perbesar, warna menurut fase atau persen tumbuh) dan ekspor
  GeoJSON.
- **Fase TIDAK dipilih**: P0 / P1 / P2 / siap dinilai diturunkan dari
  tanggal tanam; penilaian dan serah terima dari status permohonan.
- **Petak Ukur**: intensitas sampling 5%, PU 0,1 ha; persen tumbuh,
  batang sehat/ha, status berhasil (≥ 80%) / peringatan (75–80%) /
  sulam (< 75%) dan kebutuhan bibit sulam dihitung langsung di form.
- **Kegiatan lapangan** (penanaman, penyulaman, pemupukan, penyiangan,
  patroli, sekat bakar, dll.) dengan HOK, bibit, dan biaya; verifikasi
  empat mata — pencatat tidak dapat memverifikasi sendiri, penolakan
  wajib beralasan.
- **Kelayakan penilaian 10 prasyarat** (SK penlok, RKP, usia ≥ 36 bulan,
  tumbuh ≥ 75%, ≥ 500 batang sehat/ha, geotagging 100% tanpa duplikasi,
  petak berpeta, tidak ada gangguan terbuka, laporan tertib, komposisi).
  Blok yang belum memenuhi tidak dapat diajukan.
- **Penilaian & BAST**: SK tim penilai → hasil tim (berhasil bila ≥ 75%
  dan ≥ 500 batang sehat/ha) → BAST. Serah terima dapat bertahap; blok
  selesai bila seluruh tahap BAST menutup luasnya.
- **Laporan semesteran** (31 Juli & 31 Januari) dengan status tepat /
  terlambat / jatuh tempo; **kejadian & sanksi** 4 tahap (teguran I–III,
  pembatalan lokasi, pencabutan PPKH, penindakan hukum). Kebakaran tidak
  dapat ditutup sebelum dilaporkan dan exit strategy dipilih (pemeliharaan
  lanjutan/penanaman ulang, pengusulan relokasi, atau serah terima
  bertahap).
- **Reklamasi & RURH**: luas terganggu vs direklamasi dan nilai
  keberhasilan (baik ≥ 80, sedang 60–79, buruk < 60).
- **Dasbor**: corong kewajiban, peta poligon, sebaran fase, tren persen
  tumbuh per kuartal, histogram petak, HOK/bibit/biaya per bulan,
  komposisi jenis, serapan biaya vs progres fisik, dan peringatan
  (batas mendekat, petak wajib sulam, laporan terlambat). Ekspor CSV.

## Sesudah deploy — wajib dikerjakan orang
1. **Daftarkan setiap PPKH** di **Kewajiban PPKH** (nomor & tanggal SK,
   jangka, luas PAK). Jadwal laporan semesteran terbentuk otomatis sejak
   tahun SK; tandai yang sudah dikirim.
2. **Samakan angka batas** di `app/Support/Rdas/Katalog.php` bila
   BPDAS setempat memakai ketentuan lain (mis. kerapatan 1.100 batang/ha
   untuk mangrove, ambang peringatan 180 hari).
3. **Aktifkan APP-RDAS** di katalog pembelian untuk perusahaan pemegang
   PPKH.

---

# Update 20 — Aplikasi baru: Lifting & Rigging Management

```bash
# Di VPS, dari /var/www/EQOHSEE
cp database/database.sqlite ~/cadangan-sebelum-update-20.sqlite   # pgsql: pg_dump
bash deploy/deploy.sh          # migrate + build
php artisan pembelian:katalog  # menambah APP-ANGKAT (Rp0, tidak aktif)
php artisan demo:pasang        # opsional: data contoh pengangkatan
```
Satu migrasi baru, hanya MENAMBAH tabel `angkat_*` (alat, gear, riksa,
rencana, kajian, jejak). Tidak ada tabel lama yang diubah, tidak ada
penjadwal baru — status kelayakan crane dan alat bantu dihitung dari
tanggal riksa uji setiap kali dibaca.

## Lifting & Rigging Management (`/pengangkatan`, pilar Safety & Engineering)
Diadaptasi dari LiftGuard Mining Pro (Permenaker 8/2020 tentang K3
Pesawat Angkat & Angkut, Kepmen ESDM 1827 K/30/MEM/2018, ASME B30.5 /
B30.9 / B30.26, BS 7121) untuk pengangkatan di tambang.
- **Register PAA & alat bantu**: crane (mobile, crawler, overhead, truck
  crane, hoist) dengan SUKET dan operator ber-SIO; sling, rantai,
  webbing, shackle, spreader dengan WLL, faktor keamanan, dan kode warna
  triwulan. Status efektif: layak / segera riksa (≤ 30 hari) /
  kedaluwarsa / karantina / afkir.
- **Riksa uji**: beban uji dihitung otomatis (crane 125% statis + 110%
  dinamis 10 menit; sling/rantai/shackle 200% 5 menit; spreader 125%),
  butir visual & fungsi per kategori, ukuran (keausan, kawat putus,
  pemuluran rantai, bukaan kait, deformasi), NDT. Putusan TIDAK dipilih
  pemeriksa: satu butir kritis gagal, NDT cacat, atau satu ukuran
  melewati batas ASME → TIDAK LAYAK DIGUNAKAN dan alat langsung
  dikarantina (label merah). Laporan siap cetak dengan riwayat uji.
- **Rencana & simulasi**: beban kotor (bersih + rigging + kait) × DAF,
  kapasitas pada radius & boom, utilisasi (batas 85%, tandem 75%,
  man-basket 50%), tegangan kaki sling (sudut, CoG asimetris, hitch,
  spreader), tekanan outrigger vs daya dukung tanah tambang, ruang bebas
  ujung boom, angin, jarak SUTT. Klasifikasi rutin/rekayasa/kritis
  otomatis, simulasi 4 fase → GO / GO bersyarat / NO-GO, kurva beban
  interaktif dengan radius maksimum aman dan berat maksimum.
- **Alat yang tidak layak digunakan tidak dapat dipilih** — crane
  ber-SUKET habis atau sling yang dikarantina ditolak saat rencana
  disimpan, dan disembunyikan dari pilihan form.
- **Kajian teknis 6 pilar** (geoteknik, PAA, rekayasa, lingkungan
  tambang, kompetensi, IBPR/JSA): butir yang terbukti dari angka dinilai
  sistem; butir lapangan dinilai peninjau. Dihitung ulang setiap rincian
  dibuka selama rencana belum disetujui.
- **Alur persetujuan** draf → diajukan → disetujui → selesai. NO-GO
  dan kajian "tidak memenuhi" tidak dapat diajukan; pengangkatan kritis
  wajib seluruh butir lapangan terisi dan disetujui KTT; pembuat tidak
  dapat menyetujui rencananya sendiri; penolakan wajib beralasan.
- **Dasbor**: pengangkatan per bulan per klasifikasi, sebaran beban vs
  radius (klik untuk membuka), histogram utilisasi, radar 6 pilar, alat
  bantu per status, Pareto penyebab tidak layak riksa uji, jadwal 14 hari,
  dan riksa uji yang jatuh tempo. Ekspor CSV rencana.

## Sesudah deploy — wajib dikerjakan orang
1. **Daftarkan crane dan alat bantu** di **Register PAA & Alat Bantu**,
   lalu catat riksa uji terakhir masing-masing. Alat yang baru didaftarkan
   belum ber-SUKET dan tidak dapat dipilih di rencana sampai riksa ujinya
   dicatat LAYAK.
2. **Samakan angka batas** di `app/Support/Angkat/Katalog.php` bila
   prosedur perusahaan lebih ketat (mis. utilisasi kritis 70%, angin
   maksimum 30 km/jam).

# Update 19 — Aplikasi baru: Scaffolding Management & Life Cycle Assessment

```bash
# Di VPS, dari /var/www/EQOHSEE
cp database/database.sqlite ~/cadangan-sebelum-update-19.sqlite   # pgsql: pg_dump
bash deploy/deploy.sh          # migrate + lca:pasang + build
php artisan pembelian:katalog  # menambah APP-PERANCAH & APP-LCA (Rp0, tidak aktif)
php artisan demo:pasang        # opsional: data contoh kedua aplikasi
```
Dua migrasi baru, keduanya hanya MENAMBAH tabel: `perancah_*` (unit,
hitung, kajian, inspeksi, tag) dan `lca_*` (faktor, studi, inventori,
hasil, skenario, jejak). Tidak ada tabel lama yang diubah.

## Scaffolding Management (`/perancah`, pilar Safety)
Diadaptasi dari ScaffGuard Pro (OSHA 1926 Subpart L, BS EN 12811-1,
Permenaker 01/MEN/1980) untuk tambang dan fasilitas pengolahannya.
- **Daftarkan & Hitung**: kalkulator BoM (standard, ledger, transom,
  bracing, plank, coupler, tangga), beban mati/hidup, beban kaki, faktor
  keamanan, rasio H/B, tekanan tumpuan — dihitung server dan tampil
  langsung bersama sketsa tampak depan. GPS diambil dari perangkat.
- **Kajian kelayakan** lima kriteria: SF ≥ 4, H/B & ikatan, daya dukung
  tumpuan, angin & desain khusus (> 15 m / kantilever wajib P.E.), dan
  proteksi jatuh.
- **Inspeksi 16 butir** di ponsel, tombol besar, putusan tag berubah
  seketika: satu butir KRITIS gagal = merah.
- **Scafftag otomatis** hijau/kuning/merah — warnanya diputuskan sistem,
  tidak dapat dipilih tangan; hijau hanya bila kajian layak DAN inspeksi
  lulus penuh. Berlaku 7 hari; kartu siap cetak dengan QR.
- **Verifikasi QR tanpa masuk** di `/scafftag/{token}`: menjawab "layak
  digunakan / tidak layak digunakan" dari tag yang BERLAKU saat dipindai.
- **Daftar & Peta** titik pemasangan (tanpa ubin peta dari luar), ekspor
  GeoJSON & CSV; dasbor kepatuhan inspeksi, tren 12 minggu, Pareto butir
  gagal, zona, dan material terpasang.
- **Penjadwal**: `perancah:kedaluwarsa` tiap jam (menit ke-7) menurunkan
  tag yang lewat 7 hari menjadi merah. Pratinjau: `php artisan
  perancah:kedaluwarsa --pratinjau`. Pastikan cron `schedule:run` aktif.

## Life Cycle Assessment (`/lca`, pilar Environment)
Diadaptasi dari EcoTrace LCA (ISO 14040/14044, ISO 14067) untuk produk
tambang (batubara, nikel, emas, batu gamping).
- **Studi** goal & scope: unit fungsional, batas sistem (cradle-to-gate /
  grave / gate-to-gate / cradle-to-cradle), metode, alokasi, cut-off.
- **Inventori lima tahap** (ekstraksi, angkutan, pengolahan, distribusi,
  akhir hidup) dengan pratinjau dampak langsung, konversi satuan, t·km,
  alokasi, konten daur ulang, dan pedigree matrix → DQR & ketidakpastian.
- **Tarik dari Energy Performance**: solar per kategori alat, listrik,
  genset, ANFO/emulsi, bensin, LPG — dibagi produksi periode studi.
- **LCIA enam kategori** (GWP100, asidifikasi, eutrofikasi, kelangkaan
  air, energi kumulatif, ozon), matriks kategori × tahap, hotspot Pareto,
  radar profil dampak.
- **Simulator skenario** lima tuas (PLTS, efisiensi BBM, logistik,
  material, limbah) dengan nilai karbon Rp dan potensi EU CBAM.
- **Tinjauan kritis ISO 14044**, alur draf → tinjauan → terverifikasi →
  terbit, pembuat tidak dapat memverifikasi sendiri, studi terverifikasi
  dikunci (revisi = versi baru), jejak audit.

## Sesudah deploy — wajib dikerjakan orang
1. **Cocokkan faktor LCA** di **Life Cycle Assessment → Faktor Dampak**.
   Pustaka bawaan (`lca:pasang`) bertanda "belum dicocokkan"; faktor grid
   per sistem kelistrikan terutama harus disamakan dengan penetapan
   Ditjen Gatrik terbaru. Faktor yang sudah dicocokkan tidak ditimpa
   saat `lca:pasang` dijalankan lagi.
2. **Kapasitas tiang** di `App\Support\Perancah\Katalog::JENIS` adalah
   nilai tipikal; samakan dengan lembar data pabrikan sistem perancah
   yang dipakai kontraktor sebelum hasil kajian dijadikan acuan.

# Update 18 — Carbon Management jadi aplikasi sendiri, tampilan Energi & Karbon diperbarui

```bash
# Di VPS, dari /var/www/EQOHSEE
bash deploy/deploy.sh          # tidak ada migrasi baru; cukup pull + build
php artisan pembelian:katalog  # menambah butir APP-KARBON (Rp0, tidak aktif)
```
Tidak ada perubahan skema. Yang berubah:

- **Carbon Management** kini aplikasi terpisah di pemilih aplikasi
  (pilar Environment), dengan dasbornya sendiri di `/karbon`: jejak karbon
  Scope 1–3, intensitas tCO₂e/ton, komposisi scope, emisi bulanan, kategori
  IPCC, sumber emisi terbesar, emisi per gas, jalur dekarbonisasi, biaya
  karbon tiga skenario harga, aksi mitigasi, dan kewajiban pelaporan emisi.
  Halamannya pindah dari Energy Performance:

  | Lama | Baru |
  |---|---|
  | `/energi/grk` | `/karbon/inventaris` |
  | `/energi/grk/batas` | `/karbon/batas` |
  | `/energi/grk/scope3` | `/karbon/scope3` |
  | `/energi/grk/mitigasi` | `/karbon/mitigasi` |
  | `/energi/faktor` | `/karbon/faktor` |
  | `/energi/karbon` | `/karbon/inventaris` |

  Alamat lama **dialihkan permanen (301)**, jadi markah dan tautan di
  laporan lama tetap sampai. Data aktivitas (BBM, listrik, produksi) tetap
  diinput di Energy Performance; Carbon Management membaca dan
  menghitungnya dengan mesin yang sama dengan laporan GRK resmi.
- **Tampilan Energi & Karbon**: panel utama bergradien dengan siluet
  tambang dan motif kontur, kartu KPI berwarna per kategori dengan ikon,
  status target, arah perubahan terhadap tahun lalu, dan sparkline; Control
  Tower direksi mendapat ringkasan status di bagian atas. Ikon dari Lucide
  (ISC), disimpan lokal — tidak memanggil CDN.
- **Perbaikan angka**: saat administrator memilih satu perusahaan di
  "perusahaan dilihat", catatan BBM kini ikut tersaring (sebelumnya
  terjumlah dari semua perusahaan); grafik harian tidak lagi bergerigi
  karena format tanggal campuran; tahun berjalan pada jalur dekarbonisasi
  diproyeksikan setahun penuh, bukan dibandingkan mentah dengan target
  tahunan; peringkat unit boros membandingkan tiap unit dengan rata-rata
  kategori di perusahaannya sendiri.
- **Data contoh** Energi & Karbon dibuat ulang agar realistis (armada
  PC2000/HD785/D375A, produksi ±4.800 t/hari, genset musim hujan, PLTS,
  rekonsiliasi BBM, baseline & target). Muat ulang perusahaan contoh bila
  ingin melihatnya: `php artisan demo:pasang` (hanya menyentuh perusahaan
  contoh).

# Update 17 — Modul Manajemen Energi & Karbon (PRD/FSD Energi-Karbon)

```bash
# Di VPS, dari /var/www/EQOHSEE
cp database/database.sqlite ~/cadangan-sebelum-update-17.sqlite   # pgsql: pg_dump
bash deploy/deploy.sh          # menjalankan migrate, energi:pasang, build
```
Atau manual:
```bash
git pull && composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan energi:pasang      # paket faktor bersama — WAJIB, aman diulang
npm ci && npm run build && php artisan optimize:clear
```
Dua migrasi baru, keduanya hanya MENAMBAH: 15 tabel `energi_*`, kolom alur
(status, sumber data, verifikator, site, persen FAME) pada lima tabel
catatan harian, kolom M&V pada `energy_opportunities`, kolom status
persetujuan pada `energy_baselines`, dan `users.energi_role`. Baris lama
diberi status **terverifikasi**; baseline lama **disetujui BOD**.

## Sesudah deploy — wajib dikerjakan orang
1. **Cocokkan faktor acuan.** `energi:pasang` memasang nilai bawaan IPCC
   2006 (NCV, EF CO₂/CH₄/N₂O), GWP AR5/AR6, campuran B30–B50, dan konversi
   TOE/SBM. Yang bertanda **"perlu dicocokkan"** (densitas, NCV FAME,
   harga, faktor grid, ANFO) disebut perintahnya di akhir keluaran. Buka
   **Energi → Registri Faktor** dan ganti/cocokkan dengan dokumen sumbernya.
2. **Faktor grid per sistem.** Yang terpasang hanya nilai warisan 0,87
   tCO₂e/MWh. Salin nilai per sistem ketenagalistrikan dari lampiran Kepmen
   ESDM 163.K/HK.02/MEM.S/2021 (atau penetapan terbaru Ditjen Gatrik) sebagai
   versi faktor `listrik_pln / ef_grid` berwilayah, lalu isi **sistem grid**
   tiap site di Master Data.
3. **Tetapkan peran** di Admin → Pengguna → *Peran Energi & Karbon*:
   operator, verifikator, manajer_energi, petugas_grk, keuangan, bod,
   auditor_tamu. Akun **tanpa** peran tetap dapat menginput seperti
   sebelumnya; fitur tata kelola (faktor, kunci periode, laporan, Control
   Tower) hanya terbuka bagi peran yang sesuai dan administrator.
4. **Atur Manajemen Energi → Pengaturan**: kategori pengguna energi
   (ambang 4.000/6.000 TOE — kategorisasi tambang belum dipastikan [A-01]),
   sistem grid bawaan, emiten (POJK 51/PSPK 2), target dekarbonisasi.

## Yang baru di menu Energy Performance
- **Input Lapangan**: formulir *Gas & Energi Lain* (bensin, LPG, gas alam,
  ANFO/emulsi, refrigeran, SF₆), site, persen FAME per pengiriman, sumber
  data. Baris baru berstatus *diajukan* sampai diverifikasi.
- **Karbon & GRK**: inventarisasi Scope 1/2 (location & market-based)/3,
  per gas (CO₂, CH₄, N₂O, HFC, PFC, SF₆), CO₂ biogenik FAME terpisah,
  ketidakpastian IPCC Approach 1; batas organisasi & tahun dasar; Scope 3
  per kategori (kat. 11 disajikan terpisah); registri aksi mitigasi SRN PPI;
  registri faktor berversi dengan persetujuan & uji dampak.
- **Manajemen Energi**: status wajib PP 33/2023 (TOE tahun lalu, proyeksi),
  checklist Permen ESDM 8/2025, Manajer & Auditor Energi bersertifikat,
  program konservasi, audit energi + rekomendasi (tenggat 3 tahun, masuk
  Tindak Lanjut), monev; EnPI/EnB regresi ISO 50006; Pareto SEU.
- **Pelaporan**: verifikasi massal & kunci periode bulanan (4 syarat),
  koreksi pasca-kunci; Pusat Pelaporan 10 templat (Laporan Manajemen Energi
  ke ESDM, Monev, Inventarisasi GRK, ISO 14064-1, PROPER, Laporan
  Keberlanjutan, PSPK 2, SRN PPI, kinerja bulanan, board pack) dengan alur
  draf → diajukan → disetujui → terbit, snapshot beku ber-hash SHA-256,
  ekspor XLSX/CSV/JSON dan cetak PDF; kalender kepatuhan.
- **Kontrol Direksi → Control Tower** (`/energi/bod`): kartu KPI ber-RAG,
  filter di alamat, tren & proyeksi akhir tahun, jalur dekarbonisasi, peta
  panas kepatuhan, drill-down sampai catatan harian + faktornya, pusat
  peringatan, portofolio inisiatif, eksposur biaya karbon, simulator
  skenario, instruksi BOD (→ Tindak Lanjut), persetujuan target, board pack,
  mode layar besar.

## Perubahan perilaku yang perlu diketahui
- Angka GJ/tCO₂e/Rp di halaman lama kini dihitung dari registri faktor:
  1 L solar B0 = 36,12 MJ (sebelumnya 35,8; selisih ±0,9%), dan solar
  dengan FAME dipisah CO₂ fosil vs biogenik — emisi B50 per liter hampir separuh dari
  angka lama. Jalankan Pusat Pelaporan untuk perbandingan.
- **Hapus unit alat** yang sudah punya catatan BBM kini **menonaktifkan**
  unit, bukan menghapus catatannya (cacat D5).
- Peluang hemat berstatus **selesai** mensyaratkan hasil M&V (IPMVP A–D).
- Kalkulator energi menghitung di server (cacat D2); bug status peluang
  `diusulkan` diperbaiki (D1).

## Penjadwal
`energi:pengingat` harian 07:10 (zona aplikasi): tenggat kewajiban H-60,
H-30, H-7, H-1, terlambat; sertifikat manajer/auditor energi H-90, H-30.
Pratinjau tanpa mengirim: `php artisan energi:pengingat --pratinjau`.

## Belum termasuk (PRD §4.2 / fase F4)
Aplikasi seluler Flutter, ingest API bertoken untuk FMS/flow meter, akses
tamu verifikator bertoken, notifikasi dorong, dan integrasi API portal
pemerintah (POME/SRN PPI) — v1 menyediakan berkas siap unggah.

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
