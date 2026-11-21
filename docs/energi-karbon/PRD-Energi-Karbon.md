# PRD — Modul Manajemen Energi & Karbon EQOHSEE

**Nama kerja:** Energy & Carbon Management (EnCarb) — perluasan "Energy Performance Center" (`/energi`)
**Jenis dokumen:** Product Requirements Document (PRD)
**Versi:** 1.0 (draf untuk ditinjau)
**Tanggal:** 21 November 2026
**Pemilik produk:** Febrian (EQOHSEE)
**Dokumen pasangan:** [FSD-Energi-Karbon.md](FSD-Energi-Karbon.md) (spesifikasi fungsional & teknis)

> **Cara membaca tanda di dokumen ini**
> - **[R-xx]** merujuk ke daftar regulasi & referensi di §12, lengkap dengan status verifikasinya.
> - **[A-xx]** adalah **asumsi** yang belum dikonfirmasi dan harus diputuskan pemilik produk / konsultan hukum sebelum implementasi. Daftar lengkap di §11.
> - **WAJIB / SEHARUSNYA / BOLEH** dipakai sebagai tingkat prioritas kebutuhan (setara MUST/SHOULD/MAY).

---

## 1. Ringkasan eksekutif

EQOHSEE sudah punya modul **Energy Performance Center** (`/energi`): input harian solar per unit, listrik per area (PLN/genset), produksi, rekonsiliasi BBM, perhitungan GJ, GJ/ton, tCO₂e dan rupiah, dasbor, KPI, baseline & target, peluang hemat, halaman Karbon, dan Laporan Energi. Fondasinya benar — data disimpan mentah dan angka turunan dihitung saat dibaca — tetapi modul ini belum bisa dipakai untuk **pelaporan resmi** karena:

1. faktor emisi & konversi masih konstanta kode (tanpa tahun, tanpa sumber, tanpa versi);
2. belum ada pembagian **Scope 1 / 2 / 3** maupun gas CH₄ dan N₂O;
3. belum ada keluaran yang mengikuti format laporan pemerintah (Laporan Manajemen Energi ke Menteri ESDM, inventarisasi GRK Pelaku Usaha, PROPER, Laporan Keberlanjutan OJK/PSPK 2);
4. belum ada alur persetujuan, kunci periode, dan jejak audit yang dibutuhkan verifikator;
5. belum ada dasbor tingkat direksi (BOD) untuk kontrol dan pengambilan keputusan.

PRD ini mendefinisikan modul tambahan yang **dibangun di atas modul yang ada, bukan dari nol**, agar EQOHSEE menjadi satu sumber data energi & karbon tambang yang: (a) memenuhi kewajiban manajemen energi PP 33/2023 dan Permen ESDM 8/2025, (b) menghasilkan inventarisasi GRK perusahaan sesuai Perpres 110/2025 dan standar ISO 14064-1/GHG Protocol/IPCC, (c) mendukung PROPER dan pengungkapan iklim PSPK 2 (berlaku 1 Januari 2027), dan (d) memberi BOD dasbor interaktif untuk memantau, membandingkan, dan menindaklanjuti.

---

## 2. Latar belakang & masalah

### 2.1 Kondisi modul saat ini (hasil pembacaan repo, 21 Nov 2026)

| Komponen | Lokasi | Keadaan |
|---|---|---|
| Rute `/energi/*` (13 halaman) | `routes/web.php` blok "WEBSITE #6" | Lengkap secara rute |
| Controller | `app/Http/Controllers/EnergyController.php` (687 baris) | Input, ringkasan, KPI, baseline, peluang, karbon, laporan, data induk |
| Rumus & faktor | `app/Support/Energi.php` | Konstanta: solar 0,0358 GJ/L & 2,68 kgCO₂e/L; listrik 0,0036 GJ/kWh & ±0,87 kgCO₂e/kWh; gas 0,0373 GJ/m³ & 2,02 kgCO₂e/m³; harga Rp/L, Rp/kWh, Rp/m³ |
| Tabel | `energy_equipment`, `energy_fuel_logs`, `energy_power_logs`, `energy_other_logs`, `energy_production`, `energy_baselines`, `energy_opportunities`, `energy_fuel_recon` | Data mentah; `company_id` sudah ditambahkan ke production/recon/other_logs lewat migrasi `2026_08_16_000011`; `fuel_logs` mewarisi perusahaan lewat `equipment_id` |
| Tampilan | `resources/js/Pages/Energi/Halaman.vue` + `Dasbor.vue` | Hanya Dasbor yang punya tampilan jadi; mode lain masih "tampilan darurat" (kisi kunci mentah) |
| Laporan | mode `laporan` + `KopDokumen::untuk('laporan-energi')` | Dokumen terkendali dengan kop, belum mengikuti format regulator |
| Uji | `tests/Feature/EnergiTest.php` | Ada |
| Aplikasi seluler | `mobile/` (Flutter) | Belum ada layar energi |

### 2.2 Celah yang harus ditutup

| # | Celah | Dampak |
|---|---|---|
| G1 | Faktor emisi/konversi hardcode, tanpa tahun & sumber | Laporan tahun lalu berubah diam-diam saat konstanta diubah; verifikator tidak bisa menelusuri sumber faktor |
| G2 | Tidak ada Scope 1/2/3, tidak ada CH₄/N₂O, tidak ada GWP | Tidak sesuai ISO 14064-1, GHG Protocol, Perpres 110/2025 Ps. 11 (gas CO₂, CH₄, N₂O, HFC, PFC, SF₆) |
| G3 | Gas tidak punya form input; sumber lain (bensin, LPG, pelumas, bahan peledak, refrigeran, biodiesel FAME) tidak tercatat | Inventarisasi tidak lengkap |
| G4 | Campuran biodiesel (B40 → B50 sejak 1 Juli 2026) tidak dibedakan | CO₂ biogenik ikut terhitung sebagai fosil → emisi lebih tinggi dari seharusnya |
| G5 | Tidak ada konversi ke TOE/SBM | Tidak bisa menentukan apakah perusahaan melewati ambang wajib manajemen energi (≥4.000/6.000 TOE) |
| G6 | Tidak ada alur persetujuan, kunci periode, versi laporan | Data bisa diubah setelah dilaporkan; tidak layak audit |
| G7 | Halaman non-dasbor masih tampilan darurat | Tidak layak dipakai harian |
| G8 | Bug: form peluang default `status: 'diusulkan'` padahal daftar sah `usulan` | Simpan peluang gagal validasi (FSD §15) |
| G9 | Kalkulator Vue hardcode faktor dan tanpa CO₂ | Angka kalkulator berbeda dengan server |
| G10 | Tidak ada dasbor BOD lintas site/perusahaan | Direksi tidak punya alat kontrol |

---

## 3. Tujuan & ukuran keberhasilan

### 3.1 Tujuan produk

| Kode | Tujuan |
|---|---|
| T1 | **Kepatuhan:** menghasilkan seluruh laporan energi & GRK wajib dari satu sumber data, tepat waktu (Laporan Manajemen Energi 30 Juni; inventarisasi GRK Pelaku Usaha paling lambat Maret) |
| T2 | **Akurasi & keterlacakan:** setiap angka tCO₂e dapat ditelusuri ke data aktivitas, faktor (dengan sumber & tahun), dan pengguna yang menginput/menyetujui |
| T3 | **Efisiensi operasional:** menurunkan intensitas energi (GJ/ton, L/BCM) melalui deteksi unit boros, rekonsiliasi BBM, dan pelacakan peluang hemat |
| T4 | **Kontrol direksi:** BOD dapat memantau kinerja vs target, risiko kepatuhan, dan biaya karbon secara real-time dan menindaklanjuti dari dasbor |
| T5 | **Siap pasar karbon & pengungkapan:** data siap untuk SRN PPI/SRUK, PROPER, dan PSPK 2 |

### 3.2 Indikator keberhasilan (KPI produk)

| KPI | Target 12 bulan setelah rilis |
|---|---|
| Kelengkapan data aktivitas harian (hari terisi / hari operasi) | ≥ 98% |
| Waktu menyusun Laporan Manajemen Energi tahunan | dari ±10 hari kerja → ≤ 1 hari kerja |
| Selisih rekonsiliasi BBM (distribusi vs tercatat) | ≤ 2% bulanan |
| Temuan verifikator terkait keterlacakan data | 0 temuan mayor |
| Pemakaian dasbor BOD | ≥ 1 sesi/minggu per anggota BOD |
| Peluang hemat yang terverifikasi tercatat | ≥ 5 per site per tahun |

---

## 4. Ruang lingkup

### 4.1 Termasuk (in scope)

1. **Registri faktor & konversi berversi** (per tahun, per sumber, per wilayah grid) — menggantikan konstanta `Energi.php`.
2. **Inventarisasi GRK perusahaan** Scope 1, Scope 2 (location-based & market-based), dan Scope 3 terpilih; gas CO₂, CH₄, N₂O (+ HFC/PFC/SF₆ dari refrigeran & switchgear bila ada).
3. **Perluasan data aktivitas:** gas, bensin, LPG, pelumas, bahan peledak (ANFO/emulsi), refrigeran, PLTS/EBT di lokasi, pembelian listrik dari pembangkit IUPTLS/captive, REC/sertifikat EBT, perjalanan dinas & angkutan pihak ketiga (Scope 3).
4. **Manajemen energi PP 33/2023:** penghitung TOE dan status ambang, registri Manajer Energi & Auditor Energi bersertifikat, program konservasi, audit energi (3 tahunan, SEU), tindak lanjut rekomendasi (maks. 3 tahun), monev tahunan ke manajemen puncak, Laporan tahunan ke Menteri (format Lampiran I Permen ESDM 8/2025).
5. **Kerangka ISO 50001:** konteks, kebijakan energi, tinjauan energi, SEU, EnPI & EnB (ISO 50006), sasaran & rencana aksi, M&V penghematan (ISO 50015 / IPMVP), tinjauan manajemen.
6. **Alur kerja & tata kelola data:** input → validasi otomatis → verifikasi supervisor → persetujuan Manajer Energi/HSE → kunci periode → laporan terbit berversi; jejak audit lengkap.
7. **Pusat Pelaporan** (Report Center): templat laporan regulator & standar, ekspor PDF (KopDokumen), XLSX (PhpSpreadsheet), CSV/JSON untuk unggah ke portal.
8. **Dasbor BOD interaktif** (Executive Control Tower) — lihat §6.
9. **Penyelesaian tampilan** seluruh halaman `/energi` yang masih "tampilan darurat".
10. **Input lapangan seluler** (Flutter) untuk solar per unit, hour meter, dan foto bukti — luring dengan sinkronisasi.
11. **Notifikasi & kalender kepatuhan** (tenggat 30 Juni, Maret, PROPER, PSPK, kedaluwarsa sertifikat manajer/auditor energi).

### 4.2 Tidak termasuk (out of scope) versi 1.0

- Integrasi langsung API ke portal pemerintah (SIMEBTKE/POME, SRN PPI, SIGN-SMART, APPLE-Gatrik) — v1 menyediakan berkas siap unggah; integrasi API menunggu dokumentasi resmi [A-07].
- Perdagangan karbon/transaksi di IDXCarbon dan pencatatan unit karbon SRUK (hanya penyiapan data & registri proyek mitigasi).
- Pemantauan emisi pencemar udara (SO₂, NOx, PM) via CEMS/SISPEK — tetap di modul Lingkungan.
- Pengukuran IoT real-time (flow meter, smart meter) — v1 menyediakan endpoint ingest; perangkat kerasnya di luar cakupan.
- Life Cycle Assessment (LCA) lengkap untuk PROPER Hijau/Emas — v1 menyediakan data energi & emisi sebagai masukan LCA.

---

## 5. Pengguna & peran

| Peran | Siapa | Kebutuhan utama | Hak di modul |
|---|---|---|---|
| **Operator lapangan** | Fuelman, pengawas listrik, admin produksi | Input cepat harian, bisa luring | Input data aktivitas site sendiri |
| **Supervisor/Verifikator site** | Supervisor plant/fuel, SPV produksi | Memeriksa & menyetujui data harian, menangani anomali | Verifikasi, koreksi dengan alasan |
| **Manajer Energi** (bersertifikat, wajib PP 33) | Ditunjuk perusahaan | Program konservasi, audit, EnPI, laporan ke ESDM | Kelola baseline/target, peluang, audit, setujui laporan energi |
| **Petugas GRK / HSE-Environment** | Dept. Lingkungan/HSE | Inventarisasi GRK, PROPER, data untuk laporan keberlanjutan | Kelola registri faktor, hitung inventarisasi, setujui laporan GRK |
| **Kepala Teknik Tambang (KTT)** | Penanggung jawab teknis | Kepatuhan kaidah teknik pertambangan & K3/LH | Lihat semua, tanda tangan laporan |
| **Finance/Sustainability** | Corp. sustainability, finance | PSPK 2, biaya energi, harga karbon bayangan | Lihat, ekspor, kelola harga |
| **BOD / Direksi & Komisaris** | Direktur Utama, Direktur Operasi, Direktur Keuangan, Komite Keberlanjutan | Gambaran eksekutif, tren, risiko, keputusan | Dasbor BOD (baca), persetujuan target tahunan, komentar/instruksi |
| **Auditor/Verifikator eksternal** | LVV/verifikator ISO 14064-3, auditor energi | Akses baca terbatas waktu ke bukti & jejak audit | Akses tamu baca-saja berbatas waktu |
| **Administrator EQOHSEE** | Admin sistem | Konfigurasi, multi-perusahaan | Penuh |

Peran dipetakan ke sistem peran yang sudah ada (`is_admin`, `ohse_role`, `lms_role` KTT, `Gate::define`) — detail di FSD §4.

---

## 6. Dasbor interaktif BOD — "Executive Energy & Carbon Control Tower"

Permintaan tambahan Febrian (21 Nov 2026): dasbor interaktif sebagai **alat kontrol BOD** untuk monitoring.

### 6.1 Tujuan
Memberi direksi satu layar untuk menjawab lima pertanyaan: **(1)** Apakah kita on-track terhadap target energi & emisi tahun ini? **(2)** Site/unit mana yang menyimpang dan berapa nilai rupiahnya? **(3)** Apa risiko kepatuhan terdekat? **(4)** Berapa eksposur biaya karbon kita? **(5)** Tindak lanjut apa yang sudah/belum berjalan?

### 6.2 Kebutuhan fungsional dasbor BOD

| ID | Kebutuhan | Prioritas |
|---|---|---|
| BOD-01 | **Kartu KPI utama** dengan status RAG (merah/kuning/hijau) vs target: total energi (GJ & TOE), intensitas GJ/ton & L/BCM, total tCO₂e (S1, S2, S3), intensitas tCO₂e/ton, biaya energi (Rp & Rp/ton), % EBT, selisih rekonsiliasi BBM | WAJIB |
| BOD-02 | **Filter global** periode (MTD/QTD/YTD/rolling 12 bulan/kustom), perusahaan, site, area, kategori alat; disimpan di URL agar tautan dapat dibagikan | WAJIB |
| BOD-03 | **Drill-down** dari grup → perusahaan → site → area/kategori → unit → catatan harian & bukti | WAJIB |
| BOD-04 | **Grafik tren** aktual vs baseline vs target, dengan proyeksi akhir tahun (run-rate & regresi terhadap produksi) | WAJIB |
| BOD-05 | **Jalur dekarbonisasi**: emisi aktual vs lintasan target jangka menengah (mis. NZE perusahaan / target ENDC sektoral) dan kontribusi tiap inisiatif (waterfall/MACC) | SEHARUSNYA |
| BOD-06 | **Peta panas kepatuhan**: status tiap kewajiban (PP 33, Permen ESDM 8/2025, Perpres 110, PROPER, PSPK 2, ISO 50001/14064) per perusahaan: patuh / dalam proses / terlambat / tidak berlaku, dengan tenggat terdekat | WAJIB |
| BOD-07 | **Eksposur biaya karbon**: tCO₂e × harga karbon bayangan (dapat diatur, mis. tarif pajak karbon UU HPP Rp30/kgCO₂e sebagai acuan minimum) dan skenario harga | SEHARUSNYA |
| BOD-08 | **Simulator skenario ("what-if")**: ubah volume produksi, campuran B40/B50, % listrik PLN vs genset vs PLTS, harga solar → lihat dampak ke GJ, tCO₂e, Rp | SEHARUSNYA |
| BOD-09 | **Pusat peringatan**: anomali konsumsi (z-score/IQR per unit), unit "boros", selisih rekonsiliasi > ambang, data belum terverifikasi, laporan mendekati tenggat | WAJIB |
| BOD-10 | **Portofolio inisiatif hemat**: status (usulan → selesai), penghematan direncanakan vs terverifikasi (M&V), CAPEX, payback, penanggung jawab | WAJIB |
| BOD-11 | **Instruksi & tindak lanjut dari dasbor**: BOD memberi komentar/instruksi pada kartu atau site; tercatat sebagai `TindakLanjut` dengan PIC & tenggat, dipantau sampai tutup | WAJIB |
| BOD-12 | **Benchmark antar-site** (peringkat intensitas, kuartil) dan terhadap acuan industri yang diinput admin | SEHARUSNYA |
| BOD-13 | **Board pack**: ekspor satu klik PDF (kop dokumen terkendali) & PPTX/PDF ringkas untuk rapat direksi/komisaris; snapshot terkunci dengan tanggal data | WAJIB |
| BOD-14 | **Indikator kualitas data** di setiap kartu (% terverifikasi, % estimasi, tanggal data terakhir) agar BOD tahu seberapa bisa dipercaya angkanya | WAJIB |
| BOD-15 | **Mode presentasi/layar besar** (TV ruang kontrol) dengan rotasi otomatis dan penyegaran berkala | BOLEH |
| BOD-16 | **Akses seluler** (responsif & aplikasi Flutter: ringkasan KPI + peringatan + notifikasi dorong) | SEHARUSNYA |
| BOD-17 | **Persetujuan target tahunan** oleh BOD (baseline, target intensitas & emisi) dengan paraf digital (`PersetujuanParaf`) | WAJIB |

### 6.3 Prinsip desain dasbor BOD
- Angka utama terbaca dalam 5 detik; setiap angka punya pembanding (target/periode lalu) dan arah (lebih baik/buruk).
- Tidak ada angka tanpa sumber: klik angka → lihat rumus, faktor yang dipakai (dengan versi), dan data asal.
- Bahasa Indonesia, angka format `id-ID`, satuan konsisten (GJ, TOE, tCO₂e, Rp juta/miliar).
- Hak akses: BOD melihat seluruh perusahaan grup yang diizinkan; tidak dapat mengubah data aktivitas.

---

## 7. Kebutuhan fungsional (ringkas per epik)

Detail tiap kebutuhan, aturan validasi, dan kriteria penerimaan ada di FSD.

### E1 — Registri Faktor Emisi & Konversi (menutup G1, G4, G9)
- F1.1 WAJIB: Tabel faktor berversi: jenis bahan bakar/energi, parameter (NCV, densitas, EF CO₂/CH₄/N₂O, faktor grid), nilai, satuan, **tahun berlaku**, **sumber** (dokumen, tabel, halaman, URL), tingkat metodologi (IPCC Tier 1/2/3), status (draf/aktif/kedaluwarsa), penyetuju.
- F1.2 WAJIB: Faktor grid listrik per **sistem ketenagalistrikan** (mis. Jawa-Madura-Bali, Sumatera, Kalimantan, Sulawesi, isolated) mengikuti penetapan Kementerian ESDM (Kepmen ESDM 163.K/HK.02/MEM.S/2021 dan pembaruannya) [R-10][A-03].
- F1.3 WAJIB: GWP per laporan dapat dipilih (default IPCC AR5 100-tahun, sejalan dengan kerangka transparansi UNFCCC) [A-04].
- F1.4 WAJIB: Perhitungan memakai faktor yang berlaku pada **tanggal aktivitas**; laporan yang sudah dikunci menyimpan snapshot faktor yang dipakai.
- F1.5 WAJIB: Fraksi biodiesel (B40, B50, dll.) per periode/per pemasok; CO₂ dari FAME dilaporkan terpisah sebagai **biogenik**, CH₄/N₂O tetap dihitung [R-17][R-18].
- F1.6 SEHARUSNYA: Faktor spesifik pemasok (Tier 2) bila ada sertifikat uji NCV/kandungan karbon.
- F1.7 WAJIB: Kalkulator di frontend mengambil faktor dari API server, bukan konstanta.

### E2 — Data Aktivitas (menutup G3, G7)
- F2.1 WAJIB: Pertahankan input yang ada (produksi, solar per unit, listrik per area, rekonsiliasi), tambah **form gas & sumber lain** (`energy_other_logs` diperluas).
- F2.2 WAJIB: Sumber baru: bensin, LPG, pelumas (opsional), bahan peledak (ANFO/emulsi → CO₂ dari dekomposisi) [A-09], refrigeran (isi ulang kg per jenis), SF₆ switchgear, PLTS/EBT lokasi, listrik dari IUPTLS/captive, REC.
- F2.3 WAJIB: Atribut wajib setiap catatan: perusahaan, site, tanggal, sumber data (meter/nota/estimasi), lampiran bukti opsional, penginput.
- F2.4 SEHARUSNYA: Impor massal XLSX/CSV (templat resmi) dengan validasi baris & laporan galat.
- F2.5 SEHARUSNYA: Ingest API (token per perusahaan) untuk sistem FMS/dispatch/flow meter.
- F2.6 WAJIB: Validasi otomatis: rentang wajar per kategori alat (L/jam), HM tidak mundur, duplikasi, lonjakan > 3σ, rekonsiliasi stok.
- F2.7 SEHARUSNYA: Input seluler luring (Flutter) untuk solar & HM dengan foto nota/flow meter.

### E3 — Inventarisasi GRK (menutup G2)
- F3.1 WAJIB: Batas organisasi (pendekatan kendali operasional default; ekuitas/kendali finansial dapat dipilih) & batas operasional per perusahaan/site [R-21][R-22].
- F3.2 WAJIB: Kategorisasi otomatis ke Scope 1 (pembakaran stasioner, bergerak, proses, fugitive), Scope 2 (location-based & market-based), Scope 3 (kategori 1–15 GHG Protocol; v1 minimal kat. 3 energi hulu, 4 angkutan hulu, 6 perjalanan dinas, 7 komuter karyawan, 10–11 pengolahan & penggunaan produk terjual untuk batubara [A-10]).
- F3.3 WAJIB: Tahun dasar emisi, kebijakan rekalkulasi (ambang perubahan struktural/metodologi, default 5%).
- F3.4 WAJIB: Analisis ketidakpastian sederhana (propagasi galat IPCC Approach 1) dan penanda kualitas data.
- F3.5 WAJIB: Laporan inventarisasi GRK perusahaan tahunan (Perpres 110/2025 Ps. 11 ayat (1) huruf e, Ps. 14: dilaporkan paling lambat Maret) [R-03].
- F3.6 SEHARUSNYA: Registri aksi mitigasi (proyek PLTS, elektrifikasi, efisiensi) dengan baseline & capaian penurunan emisi, siap didaftarkan ke SRN PPI [R-05].

### E4 — Manajemen Energi PP 33/2023 & ISO 50001
- F4.1 WAJIB: Penghitung TOE tahunan per perusahaan & status ambang (penyedia energi ≥6.000 TOE; industri/transportasi ≥4.000 TOE; bangunan gedung ≥500 TOE) dengan peringatan dini saat proyeksi tahun berjalan melewati ambang [R-01][R-02][A-01].
- F4.2 WAJIB: Registri Manajer Energi & Auditor Energi (nomor sertifikat kompetensi, masa berlaku, pengingat kedaluwarsa) [R-02].
- F4.3 WAJIB: Program konservasi energi tahunan (sasaran, kegiatan, anggaran, PIC).
- F4.4 WAJIB: Manajemen audit energi: jadwal ≥1× per 3 tahun, cakupan minimal satu SEU, auditor internal/eksternal, rekomendasi & tenggat implementasi ≤3 tahun [R-02].
- F4.5 WAJIB: Tinjauan energi & SEU (Pareto pemakaian energi, variabel relevan), EnPI & EnB dengan normalisasi (regresi terhadap produksi/jarak angkut/stripping ratio) mengikuti ISO 50006 [R-13].
- F4.6 WAJIB: M&V penghematan (ISO 50015/IPMVP Opsi A–D) untuk peluang yang selesai; hanya penghematan terverifikasi yang masuk KPI "terwujud" [R-14].
- F4.7 WAJIB: Laporan monev tahunan Manajer Energi ke manajemen puncak & Laporan Manajemen Energi ke Menteri ESDM (tenggat 30 Juni tahun berikutnya, elektronik) [R-02].
- F4.8 SEHARUSNYA: Modul tinjauan manajemen ISO 50001 (masukan/keluaran klausul 9.3).

### E5 — Alur kerja, kunci periode, audit trail (menutup G6)
- F5.1 WAJIB: Status data harian: `draf → diajukan → terverifikasi → disetujui → terkunci`.
- F5.2 WAJIB: Kunci periode bulanan & tahunan; koreksi setelah kunci hanya lewat **pengajuan koreksi** dengan alasan & persetujuan, menghasilkan revisi laporan baru.
- F5.3 WAJIB: Jejak audit per baris (siapa, kapan, nilai lama → baru, alasan) — memperluas `ActivityLog`.
- F5.4 WAJIB: Akses verifikator eksternal baca-saja berbatas waktu.

### E6 — Pusat Pelaporan
- F6.1 WAJIB: Templat laporan (detail FSD §10): Laporan Manajemen Energi (Lampiran I Permen ESDM 8/2025), Laporan Inventarisasi GRK Perusahaan, Ringkasan data energi & emisi untuk PROPER (efisiensi energi, penurunan emisi), Data metrik iklim PSPK 2 / Laporan Keberlanjutan POJK 51/2017, Laporan GRK ISO 14064-1, Laporan Kinerja Energi bulanan (yang sudah ada, ditingkatkan), Board pack BOD.
- F6.2 WAJIB: Setiap laporan berversi, bernomor dokumen (KopDokumen), berparaf (PersetujuanParaf), dan menyimpan snapshot data & faktor.
- F6.3 WAJIB: Ekspor PDF, XLSX; SEHARUSNYA CSV/JSON.

### E7 — Notifikasi & kalender kepatuhan
- F7.1 WAJIB: Kalender kewajiban otomatis per perusahaan dengan pengingat H-60/H-30/H-7.
- F7.2 WAJIB: Notifikasi surel & dalam aplikasi; SEHARUSNYA notifikasi dorong seluler.

### E8 — Dasbor BOD (lihat §6)

### E9 — Perbaikan modul yang ada
- F9.1 WAJIB: Seluruh mode `/energi` yang masih tampilan darurat dibangun ulang sebagai komponen Vue jadi.
- F9.2 WAJIB: Perbaikan bug status peluang (`diusulkan` → `usulan`).
- F9.3 WAJIB: `Energi.php` dipertahankan sebagai fasad dengan API yang sama, tetapi membaca dari registri faktor (kompatibel mundur; uji `EnergiTest` tetap hijau).

---

## 8. Kebutuhan non-fungsional

| Area | Kebutuhan |
|---|---|
| Multi-perusahaan | Semua tabel baru memakai `company_id` + global scope `MilikPerusahaan`; tambahan `site_id` untuk perusahaan multi-site |
| Kinerja | Dasbor BOD ≤ 2 detik untuk 3 tahun data, 10 site, 500 unit (memakai tabel agregat bulanan/harian yang dimaterialisasi) |
| Keterlacakan | Setiap angka laporan dapat ditelusuri ke baris data & versi faktor |
| Keamanan | Otorisasi per peran & per perusahaan; token API berlingkup; berkas bukti disimpan privat; mengikuti UU 27/2022 Pelindungan Data Pribadi untuk data karyawan (Scope 3 komuter) [R-29] |
| Ketersediaan | Mengikuti deploy yang ada (VPS/Vercel); backup harian |
| Bahasa & format | UI Bahasa Indonesia; angka `id-ID`; tanggal `dd/mm/yyyy`; zona waktu per site (WIB/WITA/WIT) |
| Aksesibilitas | Kontras WCAG AA, grafik dengan label teks/tabel alternatif |
| Seluler | Input luring dan sinkronisasi; layar BOD ringkas |
| Retensi | Data aktivitas & laporan disimpan ≥10 tahun [A-11] |
| Kompatibilitas | Laravel 13, PHP 8.4, Inertia + Vue 3 + Tailwind, SQLite (dev) / PostgreSQL (prod) — mengikuti repo |

---

## 9. Peta regulasi → fitur

| Regulasi/standar | Kewajiban utama | Fitur yang memenuhi |
|---|---|---|
| **PP 33/2023** Konservasi Energi [R-01] | Pengguna energi ≥ ambang TOE wajib manajemen energi: tunjuk manajer energi, program konservasi, audit energi berkala, laksanakan rekomendasi, lapor tahunan | E4 (F4.1–F4.7), E7 |
| **Permen ESDM 8/2025** (pelaksanaan manajemen energi) [R-02] | Manajer energi bersertifikat (Ps. 4); monev tahunan ke manajemen puncak (Ps. 10); audit ≥1×/3 tahun min. satu SEU (Ps. 13); rekomendasi ≤3 tahun (Ps. 16); laporan ke Menteri paling lambat 30 Juni, elektronik, format Lampiran I (Ps. 17); disinsentif (Ps. 28–29) | F4.2, F4.4, F4.7, Report Center |
| **Permen ESDM 3/2025** [R-02b] | Manajemen energi untuk instansi pemerintah/pemda | Tidak berlaku langsung bagi pemegang IUP; dicatat sebagai referensi |
| **Perpres 110/2025** NEK & Pengendalian Emisi GRK Nasional (mengganti Perpres 98/2021) [R-03] | Pelaku Usaha melakukan inventarisasi emisi GRK perusahaan (Ps. 11 ayat (1) huruf e), sumber termasuk pengadaan & penggunaan energi (Ps. 12), metodologi IPCC (Ps. 13), lapor tahunan paling lambat Maret (Ps. 14); instrumen NEK (perdagangan karbon, pembayaran berbasis kinerja, pungutan atas karbon); registri SRN PPI & SRUK | E3, F3.5, F3.6, BOD-07 |
| **Permen LHK 21/2022** Tata Laksana NEK [R-04] | MRV, SRN PPI, SPE-GRK | E3, E5 (MRV), F3.6 — status pasca-Perpres 110/2025 perlu dipantau [A-05] |
| **SRN PPI** [R-05] | Registrasi aksi mitigasi & capaian | F3.6 (ekspor data aksi mitigasi) |
| **Permen ESDM 22/2019** & **Kepmen ESDM 163.K/HK.02/MEM.S/2021** [R-09][R-10] | Pedoman inventarisasi GRK sektor energi; penetapan faktor emisi GRK sistem ketenagalistrikan | F1.2 |
| **Permen ESDM 16/2022** & APPLE-Gatrik [R-11] | NEK subsektor pembangkit (PTBAE-PU, persetujuan teknis); pelaporan emisi pembangkit | Relevan bila perusahaan mengoperasikan pembangkit captive (IUPTLS) — penanda "pembangkit terkena PTBAE" [A-06] |
| **UU 7/2021 HPP** Pajak Karbon [R-12] | Tarif minimum Rp30/kgCO₂e; penerapan ditunda, peta jalan bertahap (pembangkit lalu transportasi) | BOD-07 harga karbon bayangan |
| **PROPER** — Permen LHK 1/2021, diperbarui Permen LH/BPLH 7/2025 [R-06] | Penilaian ketaatan & beyond compliance; indikator efisiensi energi & air, penurunan emisi; Emas mensyaratkan skema NEK dalam model bisnis; LCA | Templat PROPER (F6.1), registri inisiatif & M&V |
| **Kepmen ESDM 1827 K/30/MEM/2018** & **Permen ESDM 26/2018** [R-07] | Pedoman kaidah teknik pertambangan yang baik (termasuk pengelolaan lingkungan & konservasi minerba); masuk RKAB | Tautan ke modul SMKP/Konservasi; data energi sebagai bagian pelaporan RKAB [A-08] |
| **POJK 51/POJK.03/2017** + SEOJK 16/2021 [R-15] | Laporan Keberlanjutan emiten: kinerja lingkungan termasuk penggunaan energi & emisi; perubahan POJK sedang dikonsultasikan (2026) | Templat Laporan Keberlanjutan |
| **PSPK 1 & PSPK 2** (DSK IAI, adopsi IFRS S1/S2) [R-16] | Berlaku untuk periode mulai 1 Jan 2027: emisi Scope 1/2/3 (GHG Protocol), keringanan Scope 3 tiga tahun, target iklim, risiko transisi | E3, templat PSPK 2, BOD-05/07 |
| **Kepmen ESDM 257.K/EK.01/MEM.E/2026** (B50, berlaku 1 Juli 2026) & mandatori B40 sebelumnya [R-17][R-18] | Kandungan FAME dalam solar | F1.5 |
| **ISO 50001:2018**, ISO 50006, ISO 50015, ISO 50002 [R-13][R-14] | SMEn, EnPI/EnB, M&V, audit energi | E4 |
| **ISO 14064-1:2018**, ISO 14064-2, ISO 14064-3:2019 [R-19] | Kuantifikasi & pelaporan GRK organisasi, proyek, verifikasi | E3, E5 |
| **GHG Protocol** Corporate Standard, Scope 2 Guidance, Scope 3 Standard [R-21][R-22] | Batas, scope, metode location/market-based | E3 |
| **IPCC 2006 Guidelines** + 2019 Refinement; IPCC AR5/AR6 GWP [R-20] | Faktor default Tier 1, metode | E1 |
| **UU 27/2022 PDP** [R-29] | Pelindungan data pribadi | NFR keamanan |

---

## 10. Rilis bertahap (roadmap)

| Fase | Durasi indikatif | Isi | Keluaran |
|---|---|---|---|
| **F0 — Perbaikan dasar** | 2 minggu | Bug status peluang, kalkulator ambil faktor dari server, tampilan jadi untuk mode darurat prioritas (input, bahan bakar, listrik, karbon) | Modul harian layak pakai |
| **F1 — Fondasi data** | 4–6 minggu | Registri faktor berversi, site, sumber energi baru & form gas, fraksi biodiesel, alur verifikasi & kunci periode, audit trail | Data siap audit |
| **F2 — Inventarisasi GRK & Manajemen Energi** | 6–8 minggu | Scope 1/2/3, TOE & ambang, manajer/auditor energi, audit energi, EnPI/EnB, M&V | Laporan Manajemen Energi & Inventarisasi GRK |
| **F3 — Dasbor BOD & Report Center** | 4–6 minggu | Control Tower, drill-down, peringatan, instruksi BOD, board pack, templat PROPER/PSPK 2 | Alat kontrol direksi |
| **F4 — Seluler & integrasi** | 4 minggu | Input Flutter luring, ingest API, notifikasi dorong, simulator skenario | Pengumpulan data otomatis |

Target: F0–F2 selesai sebelum siklus laporan 30 Juni 2027; PSPK 2 pertama untuk tahun buku 2027 (dilaporkan 2028).

---

## 11. Asumsi, risiko & pertanyaan terbuka

| Kode | Asumsi / pertanyaan | Dampak bila salah | Tindakan |
|---|---|---|---|
| A-01 | Perusahaan tambang batubara/mineral dikategorikan "industri" (ambang 4.000 TOE) atau "penyedia energi" (6.000 TOE) — kategorisasi untuk tambang batubara **belum dipastikan** | Status wajib salah | Ambang dibuat konfigurasi per perusahaan; konfirmasi ke Ditjen EBTKE |
| A-02 | Konversi 1 TOE = 41,868 GJ (definisi IEA); SBM memakai faktor Handbook Statistik ESDM | Angka TOE meleset kecil | Faktor konversi disimpan di registri, dapat diubah |
| A-03 | Nilai faktor grid per sistem diambil dari lampiran Kepmen ESDM 163.K/2021 (data 2019) atau pembaruan Ditjen Gatrik — **nilai belum disalin ke dokumen ini** | Scope 2 meleset | Admin memasukkan nilai dari dokumen resmi + lampiran sumber saat setup |
| A-04 | GWP default IPCC AR5 | Beda kecil dengan AR6 | Dapat dipilih per laporan |
| A-05 | Permen LHK 21/2022 masih berlaku selama masa transisi Perpres 110/2025 (±1 tahun); peraturan pelaksana baru mungkin mengubah format SRN PPI | Format ekspor berubah | Templat ekspor dibuat terkonfigurasi |
| A-06 | Pembangkit captive perusahaan (bila ada) dapat terkena PTBAE-PU Permen ESDM 16/2022 | Kewajiban tambahan terlewat | Penanda per pembangkit |
| A-07 | Portal POME/SIMEBTKE, SRN PPI belum menyediakan API publik bagi pihak ketiga | Integrasi API tidak mungkin | v1 berkas siap unggah |
| A-08 | Data energi diminta sebagai bagian dokumen RKAB/laporan berkala minerba | Templat tambahan | Dikonfirmasi dengan KTT |
| A-09 | Emisi CO₂ bahan peledak ANFO dihitung dengan faktor stoikiometri (≈0,17–0,19 tCO₂/t ANFO) | Nilai berbeda dengan metodologi perusahaan | Faktor di registri, sumber dicatat |
| A-10 | Scope 3 kategori 11 (pembakaran batubara terjual) dihitung untuk pengungkapan PSPK 2 namun dipisah dari target operasional | Angka Scope 3 sangat besar dan bisa menyesatkan BOD | Ditampilkan terpisah |
| A-11 | Retensi 10 tahun | Biaya penyimpanan | Konfirmasi kebijakan arsip |
| A-12 | Perubahan POJK 51/2017 (konsultasi publik 2026) akan mewajibkan verifikasi/assurance laporan keberlanjutan & selaras PSPK | Format berubah | Pantau; templat terkonfigurasi |

**Risiko utama:** (1) kualitas data lapangan rendah → mitigasi validasi otomatis + verifikasi berjenjang; (2) regulasi pelaksana baru (pasca-Perpres 110/2025, POJK baru) mengubah format → mitigasi templat terkonfigurasi; (3) resistensi pengguna → mitigasi input seluler & form sesingkat mungkin; (4) perhitungan disalahartikan BOD → mitigasi indikator kualitas data & penjelasan rumus.

---

## 12. Daftar regulasi & referensi

Status verifikasi per 21 Nov 2026: **✔ Terverifikasi** = dibaca dari sumber resmi/berita kredibel dalam penyusunan ini; **◐ Sebagian** = dari sumber sekunder/ringkasan, perlu cek teks resmi; **○ Belum** = dari pengetahuan umum, wajib dicek sebelum dikutip di laporan resmi.

| Kode | Regulasi / referensi | Status | Sumber |
|---|---|---|---|
| R-01 | PP No. 33 Tahun 2023 tentang Konservasi Energi (ditetapkan 16 Juni 2023; mencabut PP 70/2009). Ambang: penyedia energi ≥6.000 TOE (Ps. 29), transportasi ≥4.000 TOE (Ps. 31), industri ≥4.000 TOE (Ps. 32), bangunan gedung ≥500 TOE (Ps. 34); laporan tahunan (Ps. 29(4), 35) | ◐ | [BPK](https://peraturan.bpk.go.id/Details/252083/pp-no-33-tahun-2023), [JDIH ESDM](https://jdih.esdm.go.id/storage/document/Peraturan%20Pemerintah%20Nomor%2033%20Tahun%202023.pdf), [ringkasan pasal](https://regulasienergi.com/manajemen-energi/) |
| R-02 | Permen ESDM No. 8 Tahun 2025 (ditetapkan 13 Maret 2025) tentang pelaksanaan manajemen energi bagi pengguna & penyedia energi — Ps. 2, 3, 4, 10, 13, 16, 17, 28–29 | ◐ | [BPK](https://peraturan.bpk.go.id/Details/317641/permen-esdm-no-8-tahun-2025), [ringkasan](https://regulasienergi.com/manajemen-energi/), [CRPG](https://crpg.info/how-does-permen-esdm-8-2025-modernize-indonesias-energy-management-framework/) |
| R-02b | Permen ESDM No. 3 Tahun 2025 (ditetapkan 11 Feb 2025) — manajemen energi instansi pemerintah & pemda; pelaporan 30 Juni (Ps. 27) | ◐ | [BPK](https://peraturan.bpk.go.id/Details/317655/permen-esdm-no-3-tahun-2025) |
| R-02c | POME (Pelaporan Online Manajemen Energi) di SIMEBTKE | ◐ (halaman panduan tidak dapat dibaca otomatis) | [SIMEBTKE](https://simebtke.esdm.go.id/sinergi/page/panduan_pelaporan), [ESDM: hemat 10,42 juta SBM 2023](https://www.esdm.go.id/id/media-center/arsip-berita/implementasi-manajemen-energi-tahun-2023-mampu-hemat-1042-juta-sbm-) |
| R-03 | Perpres No. 110 Tahun 2025 tentang Penyelenggaraan Instrumen Nilai Ekonomi Karbon dan Pengendalian Emisi GRK Nasional (ditetapkan 10 Okt 2025; menggantikan Perpres 98/2021; masa transisi ±1 tahun). Ps. 1, 9, 11(1)e, 12(2)a, 13, 14 | ✔ (Ps. 1–27 dibaca), ◐ (Ps. 55–73 dari ringkasan) | [JDIH Kemenko Infra (PDF)](https://jdih.kemenkoinfra.go.id/cfind/source/files/perpres/2025/perpres-no.-110-tahun-2025.pdf), [BPK](https://peraturan.bpk.go.id/Details/334733/perpres-no-110-tahun-2025), [detik](https://finance.detik.com/ekonomi-hijau/d-8173907/prabowo-teken-aturan-soal-nilai-ekonomi-karbon-ini-isinya), [Fortune](https://www.fortuneidn.com/news/pemerintah-revisi-aturan-karbon-masa-transisi-selama-satu-tahun-00-my5jv-r2l0r0/amp) |
| R-03b | Perpres No. 98 Tahun 2021 (dicabut/diganti R-03) | ✔ | [Climate Laws](https://climate-laws.org/document/presidential-regulation-no-98-of-2021-on-the-implementation-of-carbon-pricing-to-achieve-the-nationally-determined-contribution-target-and-control-over-greenhouse-gas-emissions-in-the-national-development_c782) |
| R-04 | Permen LHK No. 21 Tahun 2022 tentang Tata Laksana Penerapan Nilai Ekonomi Karbon | ◐ (status pasca-Perpres 110 belum dipastikan) | [FAOLEX PDF](https://faolex.fao.org/docs/pdf/ins218225.pdf), [Hukumonline](https://www.hukumonline.com/pusatdata/detail/lt6357823b0b14a/peraturan-menteri-lingkungan-hidup-dan-kehutanan-nomor-21-tahun-2022/) |
| R-05 | Sistem Registri Nasional Pengendalian Perubahan Iklim (SRN PPI) & Sistem Registri Unit Karbon (SRUK) | ✔ | [Infografis JDIH](https://jdih.kemenkoinfra.go.id/en/infografis/penyelenggaraan-instrumen-nilai-ekonomi-karbon), [SRN](https://asset.srnmenlh.id/srn-menlhk/files/3ff4297a-0300-4efd-9572-9eca8eb4ba30.pdf) |
| R-06 | PROPER: Permen LHK No. 1 Tahun 2021; Permen LH/BPLH No. 7 Tahun 2025 (aturan PROPER terbaru: indikator efisiensi energi & air, penurunan emisi & limbah, NEK untuk Emas, LCA) | ◐ (hubungan 7/2025 terhadap 1/2021 belum dipastikan) | [Permen LHK 1/2021 PDF](https://ppkl.menlhk.go.id/website/filebox/995/210704055716PERMENLHK%20NO%2001%20TH%202021%20ttg%20PROPER.pdf), [KLH 5 Nov 2025](https://kemenlh.go.id/news/detail/klhbplh-sosialisasikan-aturan-proper-terbaru-dorong-industri-melampaui-ketaatan-lingkungan) |
| R-07 | Kepmen ESDM No. 1827 K/30/MEM/2018 tentang Pedoman Pelaksanaan Kaidah Teknik Pertambangan yang Baik; Permen ESDM No. 26 Tahun 2018 | ✔ (isi pokok), ○ (rincian energi) | [JDIH ESDM](https://jdih.esdm.go.id/storage/document/Keputusan%20Menteri%20ESDM%20Nomor%201827%20K%2030%20MEM%202018.pdf), [IEA](https://www.iea.org/policies/28226) |
| R-09 | Permen ESDM No. 22 Tahun 2019 tentang Pedoman Penyelenggaraan Inventarisasi GRK & Mitigasi Sektor Energi | ◐ (dirujuk oleh R-10) | — |
| R-10 | Kepmen ESDM No. 163.K/HK.02/MEM.S/2021 tentang Penetapan Faktor Emisi GRK Sistem Ketenagalistrikan (berlaku 30 Agu 2021, data tahun 2019; dapat diperbarui Dirjen Ketenagalistrikan) | ✔ (keberadaan & dasar), ○ (nilai lampiran) | [RuangEnergi](https://www.ruangenergi.com/pemerintah-terbitkan-aturan-emisi-grk-sistem-ketenagalistrikan/) |
| R-11 | Permen ESDM No. 16 Tahun 2022 tentang Tata Cara Penyelenggaraan NEK Subsektor Pembangkit Tenaga Listrik; aplikasi APPLE-Gatrik | ○ | [ESDM Gatrik](https://www.esdm.go.id/id/berita-unit/direktorat-jenderal-ketenagalistrikan/kementerian-esdm-dorong-badan-usaha-subsektor-ketenagalistrikan-taati-pelaporan-manajemen-energi) |
| R-12 | UU No. 7 Tahun 2021 (HPP) — pajak karbon Rp30/kgCO₂e, penerapan ditunda; peta jalan 2 fase (pembangkit, lalu transportasi) | ◐ | [DDTC](https://news.ddtc.co.id/berita/nasional/1804235/kemenko-perekonomian-tetapkan-2-fase-implementasi-pajak-karbon), [Kontan](https://nasional.kontan.co.id/news/pajak-karbon-ditunda-kemenkeu-tak-mau-subsidi-energi-membengkak) |
| R-13 | ISO 50001:2018 (SNI ISO 50001); ISO 50006:2023 EnPI/EnB; ISO 50002 audit energi | ○ | iso.org |
| R-14 | ISO 50015:2014 M&V; IPMVP (EVO) | ○ | iso.org, evo-world.org |
| R-15 | POJK 51/POJK.03/2017 Keuangan Berkelanjutan; SEOJK 16/SEOJK.04/2021; konsultasi publik perubahan POJK 51/2017 (2026) | ◐ | [OJK](https://keuanganberkelanjutan.ojk.go.id/keuanganberkelanjutan/ArticleList/View/2049/otoritas-jasa-keuangan-gelar-konsultasi-publik-atas-perubahan-pojk-51-2017-tentang-penerapan-keuangan-berkelanjutan-bagi-lembaga-jasa-keuangan-emiten-dan-perusahaan-publik), [Kompas](https://lestari.kompas.com/read/2026/03/16/131400586/ojk-soroti-masalah-laporan-keberlanjutan-perusahaan-ingatkan-aturan-baru) |
| R-16 | PSPK 1 & PSPK 2 (DSK IAI, terbit 1 Juli 2025; berlaku periode mulai ≥1 Jan 2027; keringanan Scope 3 tiga tahun; keringanan "iklim dulu" 3 tahun) | ✔ | [KPMG](https://kpmg.com/id/en/insights/2025/10/id-at-a-glance-pspk1-pspk2.html), [IAI](https://web.iaiglobal.or.id/Berita-IAI/detail/indonesia_launches_sustainability_disclosure_standards_based_on_ifrs_s1_and_s2) |
| R-17 | Kepmen ESDM No. 257.K/EK.01/MEM.E/2026 — kewajiban B50, ditetapkan 17 Juni 2026, berlaku 1 Juli 2026, stok B40 boleh disalurkan s.d. 30 Sep 2026 | ✔ | [Kontan](https://industri.kontan.co.id/news/bahlil-resmi-terbitkan-kepmen-soal-b50-yang-berlaku-1-juli-begini-poin-pentingnya) |
| R-18 | Mandatori B40 (2025) — Kepmen ESDM pemanfaatan BBN 2025 | ○ (nomor belum diverifikasi) | [Kompas](https://lestari.kompas.com/read/2025/01/04/070000086/setelah-b40-tahun-ini-b50-disiapkan-untuk-2026) |
| R-19 | ISO 14064-1:2018, ISO 14064-2:2019, ISO 14064-3:2019; ISO 14068-1:2023 (netralitas karbon) | ○ | iso.org |
| R-20 | 2006 IPCC Guidelines for National GHG Inventories Vol. 2 (Energi) + 2019 Refinement; IPCC AR5/AR6 GWP; IPCC EFDB | ○ | ipcc-nggip.iges.or.jp |
| R-21 | GHG Protocol Corporate Accounting and Reporting Standard (rev. ed.); Scope 2 Guidance (2015) | ○ | ghgprotocol.org |
| R-22 | GHG Protocol Corporate Value Chain (Scope 3) Standard | ○ | ghgprotocol.org |
| R-23 | NDC Indonesia (Enhanced NDC 2022; Second NDC 2025) — target sektor energi | ○ | unfccc.int |
| R-24 | POJK No. 14 Tahun 2023 tentang Perdagangan Karbon melalui Bursa Karbon (IDXCarbon) | ○ | ojk.go.id |
| R-25 | PP 22/2021 Penyelenggaraan Perlindungan & Pengelolaan LH (dasar persetujuan lingkungan; terkait PROPER) | ○ | peraturan.bpk.go.id |
| R-29 | UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi | ○ | peraturan.bpk.go.id |

> **Catatan hukum:** dokumen ini adalah spesifikasi produk, bukan opini hukum. Sebelum templat laporan dipakai untuk penyampaian resmi, teks lengkap R-01, R-02 (termasuk Lampiran I), R-03 (Ps. 55–73 & ketentuan peralihan), R-06, dan R-10 (lampiran nilai faktor) wajib dibaca dari salinan resmi JDIH/BPK.

---

## 13. Persetujuan dokumen

| Peran | Nama | Tanggal | Paraf |
|---|---|---|---|
| Pemilik produk | Febrian | | |
| Manajer Energi | | | |
| HSE/Lingkungan | | | |
| Perwakilan BOD | | | |
