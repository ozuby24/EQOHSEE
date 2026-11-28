# PRD — Monetisasi & Membership EQOHSEE

**Nama kerja:** EQOHSEE Revenue Engine — Cloud Membership + Dedicated
**Jenis dokumen:** Product Requirements Document (PRD)
**Versi:** 1.0 (draf untuk ditinjau — belum ada kode yang diubah)
**Tanggal:** 28 November 2026
**Pemilik produk:** Pemilik EQOHSEE
**Dokumen pasangan:**
- [FSD-Monetisasi-Membership.md](FSD-Monetisasi-Membership.md) — spesifikasi fungsional & teknis
- [Model-Pendapatan-dan-Proyeksi.md](Model-Pendapatan-dan-Proyeksi.md) — model pendapatan, proyeksi 36 bulan, ekonomi per pelanggan
- [proyeksi/](proyeksi/) — model angka yang dapat dijalankan ulang (`node docs/monetisasi/proyeksi/tabel.js`)

**Sumber:** dokumen *EQOHSEE — Rangkuman Rancangan Monetisasi & Membership* (28 Nov 2026) dan hasil pembacaan repositori pada tanggal yang sama.

> **Cara membaca tanda di dokumen ini**
> - **[K-xx]** keputusan yang menunggu persetujuan pemilik. Setiap butir sudah disertai rekomendasi. Daftar lengkap di §13.3.
> - **[A-xx]** asumsi yang belum diverifikasi (pasar, biaya, pajak). Daftar di §13.2.
> - **[G-xx]** celah pada aplikasi saat ini (§2.2).
> - **WAJIB / SEHARUSNYA / BOLEH** = MUST / SHOULD / MAY.
> - Semua harga **belum termasuk PPN** kecuali disebut lain [K-07].

---

## 1. Ringkasan eksekutif

EQOHSEE hari ini menjual **lisensi beli-putus**: paket menyeluruh (etalase: Rp25.000.000) atau aplikasi satuan (Rp2,5–13,5 jt), ditambah server tahunan (mulai Rp3 jt) dan pemeliharaan (Rp2 jt per 3 modul per tahun). Pembayaran lewat QRIS/transfer dengan bukti yang diverifikasi admin. Model ini menghasilkan uang besar per transaksi, tetapi **jarang, tidak berulang, dan tidak dapat dilayani sendiri** — setiap pelanggan butuh penjualan langsung dan server sendiri.

Rangkuman monetisasi mengusulkan membership SaaS Bronze–VVIP. PRD ini menggabungkan keduanya menjadi **dua jalur yang saling menguatkan**:

| Jalur | Untuk siapa | Cara bayar | Peran dalam laba |
|---|---|---|---|
| **EQOHSEE Cloud** — Bronze, Silver, Gold, Diamond | IUJP, IUP kecil–menengah, praktisi HSE | Berlangganan bulanan/tahunan, self-serve, otomatis | Mesin **pendapatan berulang (MRR)** dan akuisisi |
| **EQOHSEE Dedicated** — VVIP | Grup IUP besar, multi-site | Lisensi sekali bayar + layanan tahunan (katalog yang sudah ada) + kontrak | **Kontrak bernilai besar** dan kas di muka |

Di atas keduanya berjalan enam aliran pelengkap: **ERP SaaS**, **add-on**, **kredit AI**, **produk digital & kursus**, **iklan & sponsor** (termasuk iklan pembuka modul untuk Bronze), dan **layanan**.

**Sembilan keputusan desain yang membuat laba lebih besar** (dibanding rancangan awal):

1. **Harga mengikuti nilai, bukan sekadar paket.** Nilai bertambah seiring jumlah IUJP, modul, dan pengguna — maka ketiganya menjadi add-on berbayar. Pelanggan besar otomatis membayar lebih tanpa harga dasar naik.
2. **Pagar paket yang tegas** supaya Cloud tidak memakan Dedicated: Diamond Cloud berisi 6 modul audit, bukan 23 aplikasi. Modul operasional dijual terpisah sebagai **ERP SaaS** atau lewat VVIP.
3. **EQOHSEE sebagai ERP SaaS tambang.** HRIS, payroll, absensi, Authority, gudang, maintenance, dispatch, dan biaya dijual per karyawan aktif (Rp9.000/karyawan/bulan, minimum Rp999rb) dan per site (Rp1.499.000/site/bulan). Tagihan tumbuh bersama ukuran pelanggan.
4. **Iklan pembuka setiap kali pengguna Bronze membuka modul** (keputusan pemilik), dengan tombol "Hilangkan iklan — mulai Silver". Sumber uang sponsor sekaligus pendorong upgrade.
5. **Jaringan IUP → IUJP sebagai mesin akuisisi.** Setiap Gold/Diamond mengundang kontraktornya; IUJP terhubung gratis untuk alur audit, lalu sebagian menjadi Silver.
6. **Bayar tahunan di muka** (bayar 10 bulan, pakai 12) + onboarding gratis untuk tahunan → kas masuk lebih awal, churn turun.
7. **Uji coba terbalik (reverse trial)**: workspace baru langsung merasakan Gold 14 hari, lalu turun ke Bronze bila tidak membayar.
8. **Biaya AI dikunci lewat kredit** dengan target biaya ≤ Rp200 per kredit, dijual Rp650–990 per kredit.
9. **Pembayaran otomatis lewat gateway + webhook**; jalur manual (QRIS statis/transfer) tetap untuk korporasi.

**Proyeksi (skenario moderat, detail di dokumen Model):** 95 pelanggan berbayar di bulan ke-12 dan 403 di bulan ke-36; MRR Rp45,7 jt → Rp186,8 jt; pendapatan tahun ke-3 **Rp2,22 miliar** dengan laba bersih **Rp829 jt (37%)**; impas bulanan di bulan ke-8 dan balik modal kumulatif di bulan ke-12, dengan kebutuhan modal kerja sekitar **Rp80 jt**. Pada basis pelanggan yang sama dengan simulasi rangkuman (100 Silver, 100 Gold, 20 Diamond), tuas-tuas di atas menaikkan omzet bulanan dari **Rp78,8 jt menjadi Rp133,2 jt (+69%)**.

**Fondasi yang harus dibangun lebih dulu:** mesin **Hak Akses** (entitlement). Saat ini setiap pengguna dapat membuka seluruh modul non-admin [G-01] — tanpa Hak Akses, tidak ada yang dapat dijual sebagai paket.

---

## 2. Latar belakang & kondisi saat ini

### 2.1 Yang sudah ada (hasil pembacaan repo, 28 Nov 2026)

| Area | Yang ada | Rujukan |
|---|---|---|
| Katalog | `beli_produk` berjenis `website`, `aplikasi`, `layanan`, `pemeliharaan`; diturunkan dari `App\Support\Menu` oleh `pembelian:katalog`; butir baru terbit berharga nol dan mati | `app/Console/Commands/PasangKatalog.php` |
| Aturan harga | Harga bertingkat website tambahan (Rp3 jt), server dapat diatur (+Rp500rb/vCPU, +Rp500rb/GB RAM), pemeliharaan Rp2 jt per kelipatan 3 modul; harga disalin ke baris tagihan | `app/Models/Pembelian/Produk.php`, migrasi `2026_09_25`, `2026_11_26_*` |
| Tagihan | `beli_pesanan` dengan token tautan bayar 40+ karakter, nomor INV, status `draf → menunggu_bayar → menunggu_verifikasi → lunas/ditolak/batal/kedaluwarsa`, tenggat 48 jam | `app/Support/Pembelian.php` |
| Pembayaran | QRIS dinamis dari kode statis merchant (CRC diperiksa), transfer bank, unggah bukti (berkas tertutup), verifikasi admin | `config/pembelian.php`, `beli_pembayaran` |
| Lisensi | `beli_lisensi` terbit sekali setelah lunas; kunci 16 karakter; `berakhir` null = permanen | `app/Models/Pembelian/Lisensi.php` |
| Arus kas | Masuk, ditunggu, bulan ini, hangus — dihitung dari seluruh tagihan | `Pembelian::arusKas()` |
| Akun | Daftar surel + Google/Microsoft, verifikasi kode surel, 2FA, nonaktif langsung keluar | `routes/auth.php`, `PastikanAktif` |
| Perusahaan | `companies.parent_id` (naungan), `kategori` induk/pjp/sister/lainnya, `izin_type` | migrasi `2026_11_07_000001` |
| AI | Asisten multi-penyedia (Anthropic, OpenAI, Gemini), batas 30 permintaan/jam | `config/asisten.php`, `app/Support/AiPenyedia.php` |
| Analitik | Hitungan pengunjung harian anonim tanpa IP | `kunjungan_harian`, `CatatPengunjung` |
| Etalase | Halaman depan dengan paket menyeluruh, layanan tahunan, 23 aplikasi satuan, keranjang & "Buat tagihan" tanpa akun | `LandingController`, `docs/jual/00-halaman-depan` |

Fondasinya rapi: uang disimpan sebagai bilangan bulat, harga tidak pernah diterima dari layar, harga disalin ke tagihan, lisensi idempoten. **PRD ini memperluas modul Pembelian, bukan menggantinya.**

### 2.2 Celah yang harus ditutup

| Kode | Celah | Dampak ke pendapatan |
|---|---|---|
| G-01 | Akses modul tidak dibatasi paket/lisensi. `Menu::untuk()` hanya menyaring modul admin; `beli_lisensi` tidak dibaca saat modul dibuka | Tidak ada alasan membayar; paket tidak dapat dibedakan |
| G-02 | Pendaftar baru otomatis masuk perusahaan demo (`perusahaanDemoBawaan`) — tidak ada jalur membuat workspace sendiri | Tidak ada funnel Bronze → berbayar |
| G-03 | Tidak ada langganan berulang: hanya `masa_bulan`, tanpa perpanjangan, pengingat, masa tenggang, upgrade/downgrade, prorata | Pendapatan tidak berulang; churn tak terlihat |
| G-04 | Pembayaran manual dengan verifikasi bukti oleh admin | Aktivasi tidak instan, biaya operasional naik seiring pelanggan |
| G-05 | Tidak ada kuota pengguna, IUJP terhubung, penyimpanan, kredit AI; AI hanya dibatasi per jam tanpa catatan biaya per perusahaan | Biaya AI tidak terkendali; tak ada add-on untuk dijual |
| G-06 | `arusKas()` mencampur lisensi sekali bayar, server, dan pemeliharaan; tidak ada MRR/churn/ARPU | Keputusan harga tanpa data |
| G-07 | Belum ada lencana, halaman paket membership, toko produk digital, iklan/sponsor | Empat aliran pendapatan belum ada |
| G-08 | Relasi IUP–IUJP hanya `parent_id` (naungan), tanpa undangan, persetujuan, dan izin berbagi data | Loop jaringan IUJP tidak dapat berjalan; risiko data antar-entitas |
| G-09 | Instalasi dedicated per pelanggan | Hak Akses baru tidak boleh mengunci instalasi dedicated yang sudah dibayar |
| G-10 | Harga Cloud usulan (Diamond Rp7,99 jt/tahun bila tahunan) jauh di bawah Dedicated (Rp25 jt + ±Rp22 jt/tahun) | Kanibalisasi tanpa pagar paket |

---

## 3. Tujuan & ukuran keberhasilan

### 3.1 Tujuan
1. Mengubah EQOHSEE dari penjualan proyek menjadi bisnis **pendapatan berulang** tanpa kehilangan kontrak dedicated bernilai besar.
2. Membuat pembelian **self-serve 24/7**: pilih paket → bayar → aktif dalam hitungan detik.
3. Menjaga **margin kotor ≥ 80%** dengan mengendalikan biaya AI, server, dan gateway.
4. Menumbuhkan basis pengguna gratis (Bronze) yang dapat dimonetisasi lewat konversi, produk digital, kursus, dan sponsor.

### 3.2 KPI (target skenario moderat)

| KPI | Definisi | Target |
|---|---|---|
| MRR | Langganan + add-on + ERP SaaS + layanan tahunan VVIP ÷ 12, per bulan | Rp46 jt (bln 12) · Rp110 jt (bln 24) · Rp187 jt (bln 36) |
| Konversi Bronze → berbayar | Workspace berbayar baru ÷ pendaftar baru (bulan yang sama) | ≥ 4% |
| Churn logo bulanan | Pelanggan berhenti ÷ pelanggan awal bulan | Silver ≤ 3,5% · Gold ≤ 2,5% · Diamond ≤ 1,5% |
| Porsi bayar tahunan | Pelanggan tahunan ÷ total berbayar | ≥ 40% |
| Net Revenue Retention | MRR kohort 12 bulan lalu hari ini ÷ MRR awalnya | ≥ 105% |
| Margin kotor | (Pendapatan − AI − gateway − server − konten) ÷ pendapatan | ≥ 80% |
| Biaya AI | Biaya AI ÷ pendapatan | ≤ 8% |
| Waktu aktivasi | Webhook lunas → hak aktif | ≤ 60 detik (p95) |
| Tagihan perpanjangan terlambat | Tagihan lewat jatuh tempo ÷ tagihan perpanjangan | ≤ 10% |

---

## 4. Ruang lingkup

### 4.1 Termasuk
- Paket Bronze, Silver, Gold, Diamond (Cloud) dan VVIP (Dedicated & Cloud kontrak).
- Mesin Hak Akses (modul, fitur, kuota) untuk seluruh aplikasi.
- Workspace perusahaan, relasi IUP–IUJP berbasis undangan & persetujuan.
- Langganan berulang, tagihan perpanjangan, prorata, masa tenggang, dunning.
- Payment gateway (Midtrans atau Xendit) + webhook; jalur manual yang sudah ada dipertahankan.
- Lencana membership.
- Kredit AI (dompet, pemakaian, top-up).
- Toko produk digital dan kursus berbayar.
- ERP SaaS: modul operasional dengan harga per karyawan aktif dan per site.
- Iklan, sponsor, iklan pembuka modul, dan rewarded access untuk Bronze.
- Kupon, program mitra/referal.
- Monetization Center untuk admin platform.

### 4.2 Tidak termasuk versi 1.0
- Auto-debit kartu kredit berulang (tokenisasi) — dipertimbangkan setelah gateway aktif.
- Mata uang selain IDR; faktur pajak elektronik otomatis (e-Faktur) — dikerjakan manual oleh admin keuangan dulu.
- Marketplace auditor pihak ketiga dengan komisi (dicatat sebagai peluang Tahap 5).
- Aplikasi mobile terpisah untuk pembelian (pembelian lewat web; aplikasi mobile cukup membaca hak).

---

## 5. Pengguna & peran

| Peran | Siapa | Kebutuhan utama |
|---|---|---|
| Pengunjung publik | Belum login | Membaca artikel/regulasi, melihat harga, membeli produk digital |
| Bronze | Pekerja HSE, mahasiswa, calon pelanggan | Belajar, mencoba demo audit, template gratis |
| Pemilik Workspace | Pembeli paket (Company Owner) | Membeli, mengubah paket, mengundang pengguna & IUJP, melihat tagihan |
| Admin Workspace | Ditunjuk pemilik | Mengelola pengguna, modul pilihan, IUJP terhubung |
| Auditor / Auditi / Anggota | Peran yang sudah ada (`audit_role`, `ohse_role`, dll.) | Bekerja di modul sesuai hak |
| Mitra IUJP terhubung | Workspace IUJP yang menerima undangan IUP | Merespons audit/evaluasi IUP tanpa harus berlangganan |
| Admin Platform | `is_admin` | Semua menu admin |
| **Admin Keuangan** (baru) | Ditunjuk Admin Platform | Tagihan, refund, kupon, laporan pendapatan — tanpa akses data audit pelanggan |
| Pengiklan / Sponsor | Vendor APD, alat tambang, konsultan | Kampanye, laporan tayang & klik |
| Mitra Konsultan (reseller) | Konsultan HSE | Kode referal, komisi, daftar pelanggan rujukan |

---

## 6. Model pendapatan

### 6.1 Delapan aliran pendapatan

| # | Aliran | Siapa membayar | Satuan harga (value metric) | Berulang? | Margin kotor | Tahap |
|---|---|---|---|---|---|---|
| A | **Membership Cloud** | IUJP, IUP | per workspace per bulan/tahun | Ya | ±80–84% | 1 |
| B | **Add-on** | Silver/Gold/Diamond | per modul audit, per IUJP, per pengguna | Ya | ±90% | 1 |
| H | **ERP SaaS & Paket Operasional** | Gold, Diamond, VVIP Cloud | per karyawan aktif, per site, per paket | Ya | ±85% | 1 (paket) · 2 (meteran karyawan) |
| C | **Kredit AI** | Semua | per paket kredit | Sebagian (kuota bulanan termasuk paket; top-up sekali) | ±75% | 2 |
| D | **VVIP / Dedicated** | Grup IUP | lisensi sekali + layanan tahunan + kontrak | Layanan tahunan berulang | ±70% (server & pemeliharaan) | 1 (sudah ada) |
| E | **Produk digital & kursus** | Bronze, publik, Silver | per produk / per peserta | Tidak | 70–90% | 3 |
| F | **Iklan & sponsor** | Vendor, konsultan | iklan pembuka modul (eCPM), slot bulanan, tayangan selesai, RPM | Ya (kontrak sponsor) | ±90% | 1 (pembuka modul, materi sendiri) · 4 (sponsor) |
| G | **Layanan** | Gold, Diamond, VVIP | onboarding, pelatihan, pendampingan | Tidak | 50–70% | 1 |

### 6.2 Logika laba

```text
Laba = (Pelanggan × ARPU × umur pelanggan) − biaya akuisisi − biaya melayani
         ▲ jaringan IUJP     ▲ add-on,       ▲ tahunan,        ▲ mitra &    ▲ kredit AI,
         ▲ reverse trial       upgrade         onboarding        referal      gateway otomatis
```

- **Pelanggan** naik lewat Bronze gratis + konten regulasi (SEO), reverse trial, dan undangan IUJP.
- **ARPU** naik lewat add-on dan upgrade yang dipicu batas paket, bukan lewat menaikkan harga dasar.
- **Umur pelanggan** naik lewat bayar tahunan, onboarding, dan modul harian (Hazard Report) yang membuat produk dipakai setiap hari.
- **Biaya** ditekan lewat kredit AI, pembayaran otomatis, dan satu peladen bersama untuk Cloud.

---

## 7. Paket & harga

### 7.1 Membership

| | **Bronze** | **Silver — Personal IUJP** | **Gold — Professional IUP** | **Diamond — Business** | **VVIP — Enterprise** |
|---|---|---|---|---|---|
| Bulanan | Gratis | **Rp149.000** | **Rp399.000** | **Rp799.000** | Kontrak |
| Tahunan (bayar 10 bln) [K-09] | — | Rp1.490.000 | Rp3.990.000 | Rp7.990.000 | Mulai ±Rp47 jt tahun ke-1 (§7.5) |
| Lencana | Perunggu | Perak | Emas | Berlian biru | Mahkota ungu-emas |
| Target | Individu | 1 perusahaan IUJP | 1 IUP + ≤3 IUJP | 1 IUP + ≤10 IUJP | Grup multi-IUP/IUJP |
| Pengguna termasuk [K-02] | 1 | 3 | 10 | 30 | Kontrak |
| Modul audit | Demo read-only | 1 pilihan (bawaan SMKP) | 1 pilihan | 6 modul audit standar | Semua + kustom |
| Hazard Report & Inspeksi | — | ✔ | ✔ | ✔ | ✔ |
| Respons audit/evaluasi dari IUP | ✔ (bila terhubung) | ✔ | — | — | — |
| Kredit AI | 5 saat daftar | 50/bln | 200/bln | 1.000/bln | Kontrak |
| Ekspor | Pratinjau bertanda air | PDF | PDF + Excel | PDF + Excel + konsolidasi | Semua |
| Dasbor konsolidasi IUP–IUJP | — | — | Terbatas (ringkasan) | Penuh | Penuh + multi-site |
| Template & dokumen | 3 gratis | Premium, diskon toko 10% | Seluruh template standar | Seluruh template standar | Seluruh + kustom |
| Penyimpanan bukti | 100 MB | 2 GB | 10 GB | 50 GB | Kontrak |
| Iklan | **Iklan pembuka setiap membuka modul** + slot terkontrol (§7.7) | Tidak | Tidak (termasuk selama uji coba) | Tidak | Tidak |
| ERP SaaS | — | — | Add-on | Add-on | Termasuk / kontrak |
| Dukungan | Pusat bantuan | Standar (1×24 jam kerja) | Prioritas | Prioritas tinggi | Dedicated + SLA |

**Enam modul audit standar** (kunci hak di FSD §4.3):

| # | Modul | Kunci menu / alamat |
|---|---|---|
| 1 | Audit SMKP | `smkp` |
| 2 | Pemantauan Perusahaan Jasa (PJP) | `pjp` |
| 3 | Safety Maturity Level (TPKKP) | `tpkkp` |
| 4 | Keselamatan Operasi (KO) | `ko` |
| 5 | ISO, Dokumen & Kepatuhan Regulasi | `dokumen` (termasuk `kepatuhan*`, `iso*`) |
| 6 | Audit Kinerja Pengelolaan Lingkungan | `audit-lingkungan*` |

### 7.2 ERP SaaS & Paket Operasional (add-on, Gold & Diamond Cloud) [K-10, K-17]

EQOHSEE bukan hanya alat audit: HRIS (absensi, roster, cuti, lembur, payroll, kontrak), Authority, gudang, maintenance & P2H, dispatch, dan pengendalian biaya membentuk **ERP tambang**. Modul-modul ini, yang selama ini dijual satuan Rp2,5–13,5 jt, dijual di Cloud dengan dua cara:

**a. ERP SaaS — harga mengikuti ukuran pelanggan**

| Produk | Isi (kunci menu) | Satuan harga |
|---|---|---|
| **ERP People** | HRIS (`hris`: absensi, roster, cuti, lembur, payroll, kontrak), Authority — Kelayakan Kerja (`miners`), Learning Center perusahaan (`lms`: kursus & sertifikat internal) | **Rp9.000 per karyawan aktif/bln**, minimum Rp999.000 (setara 111 karyawan) |
| **ERP Operations** | Gudang (`gudang`), Maintenance & P2H (`maintenance`), Dispatch & Hauling (`angkutan`), Pengendalian Biaya (`biaya`), Mine Ops & GIS (`operasi`), MEH (`meh`), Geoteknik (`geoteknik`), Drill & Blast (`peledakan`) | **Rp1.499.000 per site/bln** |

*Karyawan aktif* = pekerja berstatus aktif di HRIS/Authority pada bulan tagihan (FSD §7.4). *Site* = satu lokasi tambang/pit/jetty yang didaftarkan workspace. Perusahaan 250 karyawan membayar Rp2.250.000/bln untuk ERP People; 1.000 karyawan membayar Rp9.000.000/bln. Untuk lebih dari 2.000 karyawan atau 5 site, harga dinegosiasikan lewat VVIP.

**b. Paket Operasional tarif tetap**

| Paket | Isi (kunci menu) | Harga |
|---|---|---|
| Safety Operations | Investigasi Insiden (`investigasi`), Izin Kerja Aman (`izin`), Scaffolding (`perancah`), Lifting & Rigging (`angkat`) | Rp999.000/bln |
| Environment & ESG | Lingkungan & Reklamasi (`lingkungan`), Water (`air`), Konservasi (`konservasi`), RDAS (`rdas`), MERANTI (`meranti`), Energy (`energi`), Carbon (`karbon`), LCA (`lca`) | Rp999.000/bln |

Peluang SaaS lain yang dicatat tetapi **belum dimasukkan proyeksi**: *white-label* untuk konsultan HSE (logo & domain sendiri untuk melayani banyak klien), akses API per panggilan untuk integrasi sistem pelanggan, dan penyimpanan arsip jangka panjang.

### 7.3 Daftar harga add-on

| Add-on | Berlaku untuk | Harga | Batas |
|---|---|---|---|
| Modul audit tambahan | Silver, Gold | Rp149.000/modul/bln | Silver +1, Gold +2. Tiga modul tambahan di Gold (Rp846rb) melewati harga Diamond → layar menyarankan upgrade |
| Slot IUJP tambahan | Diamond | Rp49.000/IUJP/bln | Tanpa batas; Gold tetap maks. 3 (pemicu upgrade) |
| Pengguna tambahan | Silver, Gold, Diamond | Rp29.000/pengguna/bln | — |
| Penyimpanan +10 GB | Silver, Gold, Diamond | Rp49.000/bln | — |
| Paket Operasional tarif tetap | Gold, Diamond | Rp999.000/paket/bln | §7.2 |
| ERP People | Gold, Diamond | Rp9.000/karyawan aktif/bln, min. Rp999.000 | §7.2 |
| ERP Operations | Gold, Diamond | Rp1.499.000/site/bln | §7.2 |
| Onboarding & migrasi data | Gold / Diamond | Rp1.500.000 / Rp3.500.000 sekali | **Gratis bila bayar tahunan** |
| Pelatihan pengguna daring (2 jam) | Semua berbayar | Rp1.000.000/sesi | — |

Add-on tahunan = 10 × harga bulanan, mengikuti periode langganan induknya.

### 7.4 Kredit AI

**Kuota bulanan** mengikuti paket (§7.1), hangus di akhir periode. **Kredit beli** berlaku 12 bulan dan dipakai setelah kuota bulanan habis.

| Paket top-up | Harga | Per kredit |
|---|---|---|
| 100 kredit | Rp99.000 | Rp990 |
| 500 kredit | Rp399.000 | Rp798 |
| 2.000 kredit | Rp1.299.000 | Rp650 |

| Aksi AI | Kredit (awal) |
|---|---|
| Tanya-jawab asisten (per pertanyaan) | 1 |
| Ringkasan regulasi | 2 |
| Analisis dokumen (≤ 20 halaman) | 5 |
| Gap assessment terhadap kriteria SMKP | 10 |
| Draf laporan kepatuhan / narasi laporan audit | 15 |

Target biaya **≤ Rp200 per kredit** [A-05]. Tarif dikalibrasi ulang 60 hari setelah peluncuran dari `ai_pemakaian` (FSD §8). Batas 30 permintaan/jam yang sudah ada tetap berlaku sebagai rem penyalahgunaan.

### 7.5 VVIP — Dedicated & Cloud kontrak

VVIP memakai **katalog dedicated yang sudah ada** sebagai harga lantai [K-11]:

| Komponen | Harga katalog | Contoh 23 aplikasi |
|---|---|---|
| Paket menyeluruh (lisensi permanen) | Rp25.000.000 sekali | Rp25.000.000 |
| Website tambahan | Rp3.000.000 per website | — |
| Server Professional | Rp3.000.000/thn dasar + Rp500rb/vCPU + Rp500rb/GB RAM | 4 vCPU/4 GB: Rp6.000.000/thn |
| Pemeliharaan | Rp2.000.000/thn per kelipatan 3 modul | 8 kelipatan: Rp16.000.000/thn |
| **Tahun ke-1** | | **Rp47.000.000** |
| **Tahun ke-2 dst.** | | **Rp22.000.000/thn** |

Tambahan kontrak VVIP: kredit AI kontrak (mis. 5.000/bln), SLA (respons 4 jam kerja), integrasi API, alur kustom, multi-site — dihargai per proyek. VVIP juga dapat memilih **Cloud kontrak** (tanpa server sendiri) dengan harga tahunan yang dinegosiasikan, minimal setara Diamond + Paket Operasional yang dipakai.

### 7.6 Produk digital & kursus

| Produk | Harga | Silver | Gold/Diamond |
|---|---|---|---|
| Template HIRADC | Rp25.000 | −10% | Termasuk |
| Template JSA | Rp20.000 | −10% | Termasuk |
| Checklist inspeksi | Rp35.000 | −10% | Termasuk |
| Paket SOP pertambangan | Rp99.000 | −10% | Termasuk |
| Paket dokumen SMKP | Rp149.000 | −10% | Termasuk |
| Paket audit lengkap | Rp199.000 | −10% | −20% |
| Modul training HSE (materi) | Rp49.000 | −10% | Termasuk |
| **Kursus daring berbayar** (baru), mis. K3 Pertambangan Dasar, Persiapan Pengawas Operasional | Rp149.000–Rp499.000/peserta | −10% | −20%, kursi korporat |

Lisensi: **Individu** (1 orang, tidak boleh dibagikan) dan **Perusahaan** (seluruh karyawan satu perusahaan, harga ×5). Redistribusi/penjualan ulang dilarang. Sertifikat kursus adalah **sertifikat penyelesaian**, bukan sertifikasi kompetensi resmi [K-14].

### 7.7 Iklan & sponsor

**Iklan pembuka modul (keputusan pemilik, K-16).** Setiap kali pengguna Bronze **membuka sebuah modul** (berpindah ke modul lain dari bilah samping, dasbor, atau tautan), satu iklan layar penuh tampil sebelum isi modul dapat dipakai:

| Aturan | Nilai |
|---|---|
| Siapa | Pengguna tanpa langganan berbayar (Bronze), termasuk yang hanya bisa membuka demo/hanya-baca atau layar modul terkunci |
| Kapan | Setiap masuk ke modul yang berbeda dari modul terakhir. Berpindah halaman **di dalam** modul yang sama tidak memicu iklan lagi |
| Format | Gambar atau video ≤ 15 detik; tombol **Lewati** muncul setelah 5 detik |
| Ajakan tetap | Tombol "Hilangkan iklan — mulai Silver Rp149rb/bln" dan "Coba Gold gratis 14 hari" pada setiap iklan |
| Isi saat belum ada sponsor | Promosi milik EQOHSEE sendiri (upgrade, kursus, toko) — iklan tetap tampil |
| Penargetan | Kontekstual per modul (mis. vendor APD di Hazard Report, konsultan lingkungan di modul Lingkungan); tanpa profil pribadi |
| Tidak tampil | Pengguna berbayar dan selama uji coba; halaman harga, checkout, pembayaran, langganan; undangan IUJP; IUJP terhubung yang sedang merespons audit milik IUP berbayar; cetak/ekspor; Admin; server mode dedicated |

| Produk | Harga | Penempatan |
|---|---|---|
| **Iklan pembuka modul** | eCPM Rp60.000 (Rp60/tayangan), atau eksklusif satu modul Rp1.500.000–Rp3.000.000/bln sesuai tayangan | Layar pembuka modul untuk Bronze |
| Listing Direktori Unggulan | Rp500.000/bln | HSE Directory, posisi atas kategori |
| Sponsor Kategori | Rp1.500.000/bln | Banner di artikel regulasi, katalog satu kategori |
| Sponsor Utama | Rp5.000.000/bln | Landing page, buletin bulanan, satu webinar |
| Rewarded sponsor video (Bronze) | Rp1.000 per tayangan selesai | Sebelum fitur gratis tertentu |
| AdSense | Bagi hasil Google | Halaman publik saja |

**Aturan penempatan (WAJIB):** pengguna berbayar tidak pernah melihat iklan. Untuk Bronze, iklan hanya muncul sebagai pembuka modul dan di slot yang ditentukan — tidak pernah di tengah formulir, cetak/ekspor, atau pembayaran. Bronze maksimal 3 rewarded per hari; hadiah = 1 kredit AI atau 1 ekspor PDF tanpa tanda air, berlaku 24 jam.

**Catatan jujur tentang uangnya:** pada skenario moderat iklan pembuka modul menghasilkan ±Rp1,5 jt/bulan di bulan ke-36 (±48.800 tayangan). Nilai utamanya adalah dorongan upgrade: kenaikan konversi 0,5 poin bernilai +Rp147 jt laba tahun ke-3, dan penurunan konversi 0,5 poin karena pengguna terganggu merugikan kira-kira sebesar itu. Karena itu konversi dan retensi Bronze diukur 30 hari setelah fitur menyala, dan jeda minimum antar iklan dapat diatur tanpa deploy (FSD §11.4).

### 7.8 Layanan & program mitra

- **Mitra Konsultan HSE** (reseller): komisi **20% dari pembayaran 12 bulan pertama** setiap pelanggan rujukan [K-12]; harga ke pelanggan tetap sama.
- **Referal pelanggan:** perujuk dan yang dirujuk masing-masing mendapat 1 bulan gratis setelah pembayaran pertama yang dirujuk.
- **Pendampingan audit SMKP** oleh auditor mitra: mulai Rp7.500.000/audit, margin EQOHSEE 25% — Tahap 5.

### 7.9 Pagar harga: mengapa Cloud tidak memakan Dedicated

| Kebutuhan pelanggan | Cloud termurah | Dedicated | Arah |
|---|---|---|---|
| 1 IUJP, audit SMKP saja | Silver Rp1,49 jt/thn | Rp2,5 jt + Rp5 jt/thn | Cloud (target pasar baru) |
| 1 IUP + 3 IUJP, satu modul audit | Gold Rp3,99 jt/thn | Rp2,5 jt + Rp5 jt/thn | Cloud |
| IUP + 10 IUJP, semua audit | Diamond Rp7,99 jt/thn | ±Rp23 jt + ±Rp7 jt/thn (6 aplikasi katalog) | Cloud |
| IUP besar 250 karyawan, 1 site, semua 23 aplikasi | Diamond + 2 paket + ERP People + ERP Operations ≈ Rp65,5 jt/thn (tahunan) | Rp47 jt thn-1, Rp22 jt/thn sesudahnya | **Dedicated lebih murah jangka panjang** → VVIP |
| Grup, multi-site, data harus di server sendiri | — | VVIP | VVIP |

Cloud menangkap pasar yang selama ini terlalu kecil untuk Dedicated. Pelanggan yang butuh seluruh aplikasi tetap diarahkan ke Dedicated, yang memberi kas di muka lebih besar.

### 7.10 Lencana

Emblem melingkar pada foto profil, logo perusahaan, kepala workspace, daftar perusahaan, dan halaman informasi audit. Teks bantu wajib: *"Lencana menunjukkan tingkat keanggotaan EQOHSEE, bukan sertifikasi kompetensi, izin pertambangan, atau pengesahan regulator."* Lencana **tidak** dicetak pada laporan audit resmi, berita acara, atau formulir bertanda tangan, supaya tidak terbaca sebagai pengesahan.

---

## 8. Strategi pertumbuhan & konversi

| Tuas | Cara kerja | Diukur dengan |
|---|---|---|
| **Uji coba terbalik** [K-08] | Membuat workspace → Gold penuh 14 hari tanpa kartu. Hari ke-15 turun ke Bronze; data tetap ada, hanya baca | Konversi trial → berbayar (target ≥ 15%) |
| **Loop jaringan IUP → IUJP** | Gold/Diamond mengundang IUJP. IUJP membuat akun gratis, menerima audit/evaluasi, mengunggah bukti. Ketika IUJP ingin mengaudit dirinya sendiri atau memakai modul lain → Silver | IUJP terhubung, konversi ke Silver (target 8%/tahun) |
| **Iklan pembuka modul** | Setiap Bronze membuka modul: iklan + "Hilangkan iklan — mulai Silver" + "Coba Gold 14 hari" | Klik upgrade dari iklan, konversi & retensi Bronze sebelum/sesudah |
| **ERP tumbuh bersama pelanggan** | Tagihan ERP People naik otomatis saat karyawan bertambah; pemilik melihat perkiraan tagihan bulan depan | Ekspansi MRR dari ERP |
| **Pemicu upgrade di batas** | Saat batas tercapai (IUJP ke-4, modul ke-2, pengguna ke-11, kredit habis), layar menunjukkan pilihan add-on dan upgrade dengan selisih harganya | Upgrade per bulan, ekspansi MRR |
| **Tahunan di muka** | Bayar 10 bulan pakai 12, onboarding gratis | Porsi tahunan (target ≥ 40%) |
| **Dunning & win-back** | Pengingat H-14, H-7, H-1, H+1, H+3, H+7; tawaran kembali 20% di bulan pertama setelah turun ke Bronze | Pemulihan tagihan terlambat |
| **Konten regulasi publik** | Artikel & ringkasan regulasi (SEO) → daftar Bronze → template gratis | Pendaftar per bulan |
| **Mitra konsultan** | Konsultan membawa kliennya; komisi 20% | Porsi pelanggan dari mitra |
| **Pelanggan dedicated lama** | Tawarkan kredit AI, kursus, dan Cloud untuk IUJP-nya | Pendapatan tambahan dari basis lama |

---

## 9. Kebutuhan fungsional

### E1 — Paket & Hak Akses (WAJIB, Tahap 1) — menutup G-01, G-05, G-09
- FR-101 Paket, fitur, dan kuota disimpan sebagai data (tabel), bukan konstanta kode, dan dapat diubah Admin Platform dengan jejak audit.
- FR-102 Satu layanan `Hak` menjadi satu-satunya sumber keputusan akses modul, fitur, dan kuota (`Hak::boleh()`, `Hak::kuota()`, `Hak::sisa()`).
- FR-103 Setiap permintaan ke alamat modul diperiksa di server lewat middleware global yang memetakan alamat ke kunci hak (pola yang sama dengan `Menu::PETA_ALAMAT`).
- FR-104 Bilah samping menampilkan modul yang tidak termasuk paket dengan ikon gembok dan tautan "Lihat paket", bukan disembunyikan.
- FR-105 Mode instalasi `cloud` / `dedicated`. Pada `dedicated`, hak dibaca dari `beli_lisensi` yang berlaku; bila belum ada lisensi tercatat, seluruh modul tetap terbuka (perilaku hari ini).
- FR-106 Data tidak pernah dihapus karena paket turun; modul yang tidak lagi termasuk menjadi hanya-baca selama masa terbatas, lalu terkunci dengan tawaran ekspor.

### E2 — Workspace & relasi IUP–IUJP (WAJIB, Tahap 1) — menutup G-02, G-08
- FR-201 Pengguna Bronze dapat membuat workspace perusahaan (IUP/IUJP) dan menjadi Pemilik Workspace.
- FR-202 Gold/Diamond mengundang IUJP lewat surel; IUJP menerima, membuat akun/workspace sendiri bila belum ada, dan **menyetujui** cakupan data yang dibagikan.
- FR-203 Kuota IUJP terhubung dihitung dari tautan berstatus disetujui; undangan yang menunggu tidak dihitung.
- FR-204 IUP hanya dapat melihat data IUJP yang termasuk cakupan persetujuan (mis. audit/evaluasi yang dibuat IUP itu sendiri). IUJP dapat mencabut tautan; data audit milik IUP tetap di IUP.
- FR-205 Satu IUJP boleh terhubung ke beberapa IUP.

### E3 — Langganan & siklus tagihan (WAJIB, Tahap 1) — menutup G-03
- FR-301 Langganan milik workspace (perusahaan), bukan akun [K-06].
- FR-302 Status: `uji_coba`, `aktif`, `tenggang`, `terbatas`, `berhenti`; Bronze adalah keadaan tanpa langganan berbayar.
- FR-303 Tagihan perpanjangan terbit otomatis H-14; masa tenggang 7 hari; masa terbatas 30 hari (hanya baca + ekspor) [K-05].
- FR-304 Upgrade berlaku setelah lunas, ditagih prorata sisa periode. Downgrade berlaku di periode berikutnya setelah pemeriksaan kuota.
- FR-305 Seluruh perubahan dicatat di riwayat langganan.
- FR-306 Pemilik dapat mematikan perpanjangan; paket tetap aktif sampai akhir periode.

### E4 — Pembayaran (WAJIB, Tahap 1) — menutup G-04
- FR-401 Driver gateway (Midtrans **atau** Xendit) di balik satu antarmuka; jalur manual (QRIS statis/dinamis + transfer + bukti) tetap tersedia [K-13].
- FR-402 Aktivasi hanya dari webhook yang tanda tangannya sah dan nominalnya sama dengan tagihan — tidak pernah dari redirect peramban.
- FR-403 Webhook disimpan seluruhnya, diproses idempoten (unik per gerbang + id event), di dalam transaksi basis data.
- FR-404 Rekonsiliasi harian menanyakan status tagihan `menunggu_bayar` ke gateway untuk menangkap webhook yang hilang.
- FR-405 Refund tercatat sebagai status `dikembalikan` dan mencabut hak sesuai kebijakan tertulis.

### E5 — Halaman harga & checkout self-serve (WAJIB, Tahap 1)
- FR-501 Halaman `/harga` menampilkan Cloud (bulanan/tahunan) dan Dedicated (katalog yang sudah ada) dalam satu halaman.
- FR-502 Checkout: pilih paket → pilih siklus → pilih modul (Silver/Gold) → add-on → kupon → ringkasan dengan PPN → bayar.
- FR-503 Angka di layar dihitung ulang di server; harga tidak pernah diterima dari klien (aturan yang sudah ada di `Pembelian::buat`).

### E6 — Lencana (SEHARUSNYA, Tahap 1)
- FR-601 Komponen lencana pada avatar dan logo, ukuran 16/24/40 px, dengan teks bantu (§7.10).
- FR-602 Berubah otomatis mengikuti status langganan; tidak tampil pada dokumen resmi.

### E7 — Kredit AI (SEHARUSNYA, Tahap 2) — menutup G-05
- FR-701 Dompet per workspace (dan per akun untuk Bronze) dengan buku besar mutasi.
- FR-702 Kuota bulanan dikreditkan di awal periode; kredit beli kedaluwarsa 12 bulan; pemakaian mendahulukan kuota bulanan.
- FR-703 Setiap permintaan AI menahan kredit sebelum dikirim dan menyelesaikannya sesudah jawaban diterima; gagal = kredit kembali.
- FR-704 Token masuk/keluar, model, dan biaya rupiah dicatat per permintaan.
- FR-705 Peringatan saat sisa kredit 20% dan 0%.

### E8 — Toko produk digital (SEHARUSNYA, Tahap 3) — menutup G-07
- FR-801 Produk digital memakai katalog & tagihan yang sama (`beli_produk` jenis `digital`).
- FR-802 Berkas disimpan di disk privat; unduhan lewat tautan bertanda tangan berumur 10 menit; maks. 5 unduhan dalam 30 hari per lisensi.
- FR-803 PDF diberi tanda air nama pembeli dan nomor lisensi (SEHARUSNYA).
- FR-804 Diskon anggota dan "termasuk paket" dihitung di server.

### E9 — Kursus berbayar (BOLEH, Tahap 3)
- FR-901 Kursus Learning Center dapat diberi harga; pendaftaran dibuka setelah lunas.
- FR-902 Kursi korporat: workspace membeli N kursi dan menugaskan karyawannya.

### E10 — Iklan, sponsor & rewarded (WAJIB untuk iklan pembuka modul di Tahap 1; lainnya BOLEH, Tahap 4) — menutup G-07
- FR-1000 **Iklan pembuka modul:** setiap kali pengguna Bronze masuk ke modul yang berbeda dari modul terakhirnya, iklan layar penuh tampil sebelum isi modul dapat dipakai; tombol Lewati setelah 5 detik; ajakan upgrade selalu ada; promosi sendiri bila tidak ada sponsor; pengecualian sesuai §7.7. Ditentukan di server, bukan di peramban.
- FR-1001 Slot iklan bernama; kampanye dengan tanggal, slot, materi, batas tayang, dan target modul (untuk slot pembuka modul).
- FR-1002 Statistik disimpan sebagai agregat harian (tanpa IP/profil), mengikuti pendekatan `kunjungan_harian`.
- FR-1003 Rewarded: penyelesaian diverifikasi di server (token bertanda tangan + durasi minimum), maks. 3 per hari per akun.
- FR-1004 Aturan larangan penempatan §7.7 ditegakkan di server.

### E14 — ERP SaaS berbasis pemakaian (WAJIB untuk paket tarif tetap di Tahap 1; meteran karyawan & site SEHARUSNYA, Tahap 2)
- FR-1401 Workspace dapat membeli ERP People dan ERP Operations sebagai add-on Gold/Diamond.
- FR-1402 Jumlah karyawan aktif dihitung otomatis dari HRIS/Authority pada tanggal yang sama setiap bulan; pemilik melihat angka & daftar karyawan yang dihitung sebelum tagihan terbit.
- FR-1403 Tagihan ERP People dibayar di muka menurut jumlah yang dinyatakan; selisih dengan jumlah sebenarnya ditagih/dikreditkan pada tagihan berikutnya (*true-up*). Minimum Rp999.000 selalu berlaku.
- FR-1404 Site didaftarkan pemilik (nama, lokasi, jenis); modul ERP Operations hanya dapat memilih site yang terdaftar dan dibayar.
- FR-1405 Monetization Center menampilkan pendapatan ERP per workspace dan pertumbuhan karyawan (sinyal ekspansi).

### E11 — Monetization Center (WAJIB sebagian di Tahap 1) — menutup G-06
- FR-1101 Revenue Overview: MRR, ARR, pendapatan per aliran, tren 12 bulan. MRR tidak mencampur penjualan sekali bayar.
- FR-1102 Membership Manager, Company Capacity, Module Entitlement, Payment & Invoice Manager, AI Credit Manager, Digital Store Manager, Ads Manager, Coupon Manager, Reports (ekspor CSV/Excel).
- FR-1103 KPI: MRR, ARR, ARPU, churn logo & pendapatan, NRR, konversi, LTV, tagihan terbuka, biaya AI per pelanggan, margin kotor.
- FR-1104 Hanya Admin Platform & Admin Keuangan.

### E12 — Notifikasi & dunning (WAJIB, Tahap 1)
- FR-1201 Surel transaksional: tagihan terbit, lunas + faktur, perpanjangan H-14/H-7/H-1, gagal/lewat tempo H+1/H+3/H+7, turun paket, kredit menipis, undangan IUJP.
- FR-1202 Notifikasi dalam aplikasi untuk pemilik & admin workspace.

### E13 — Kupon & program mitra (SEHARUSNYA, Tahap 1–2)
- FR-1301 Kupon persentase/nominal, batas pakai, masa berlaku, berlaku untuk paket/siklus tertentu.
- FR-1302 Kode mitra pada tautan daftar; komisi dihitung dari pembayaran lunas 12 bulan pertama; laporan komisi bulanan.

---

## 10. Kebutuhan non-fungsional

| Aspek | Kebutuhan |
|---|---|
| Keamanan | Paket tidak dapat diubah dari peramban; semua cek hak di server; webhook diverifikasi tanda tangan; rahasia gateway di `.env` |
| Integritas uang | Rupiah bilangan bulat (`bigInteger`); harga disalin ke tagihan; idempotensi aktivasi; transaksi DB + `lockForUpdate` |
| Jejak audit | Perubahan paket, harga, fitur, lencana, kupon, refund dicatat (siapa, kapan, sebelum/sesudah) di `activity_log` yang sudah ada |
| Privasi | Mengikuti UU No. 27 Tahun 2022 (PDP): data minimal, iklan tanpa profil pribadi, persetujuan berbagi data IUJP–IUP eksplisit dan dapat dicabut |
| Kinerja | Keputusan hak ≤ 5 ms (cache per permintaan + cache 10 menit, dibersihkan saat langganan berubah) |
| Ketersediaan | Webhook gateway diterima walau antrean penuh (simpan dulu, proses lewat antrean); rekonsiliasi harian |
| Cadangan | Basis data pembayaran dicadangkan harian; uji pemulihan triwulanan |
| Kompatibilitas | Instalasi dedicated tidak berubah perilakunya kecuali mode `cloud` dinyalakan |
| Aksesibilitas | Halaman harga dan checkout terbaca di ponsel 360 px |

---

## 11. Ringkasan proyeksi keuntungan

Detail asumsi dan tabel bulanan di [Model-Pendapatan-dan-Proyeksi.md](Model-Pendapatan-dan-Proyeksi.md).

| Indikator | Konservatif | Moderat | Optimis |
|---|---:|---:|---:|
| Pelanggan berbayar bulan ke-12 / ke-36 | 29 / 95 | 95 / 403 | 195 / 890 |
| MRR bulan ke-36 | Rp50,6 jt | Rp186,8 jt | Rp402,5 jt |
| Pendapatan tahun 1 / 2 / 3 | Rp153 jt / Rp416 jt / Rp666 jt | Rp390 jt / Rp1,21 M / Rp2,22 M | Rp760 jt / Rp2,40 M / Rp4,59 M |
| Laba bersih tahun 3 (margin) | Rp116,3 jt (17%) | Rp828,9 jt (37%) | Rp2.142,0 jt (47%) |
| Impas bulanan | bulan ke-20 | bulan ke-8 | bulan ke-7 |
| Balik modal kumulatif | bulan ke-36 | bulan ke-12 | bulan ke-10 |
| Kebutuhan modal kerja | ±Rp134 jt | ±Rp80 jt | ±Rp90 jt |
| Pendapatan ERP SaaS & Paket Operasional tahun 3 | Rp128 jt | Rp463 jt | Rp989 jt |
| Pendapatan iklan pembuka modul tahun 3 | Rp5,3 jt | Rp13,6 jt | Rp22,3 jt |

Catatan: skenario optimis butuh modal kerja lebih besar dari moderat karena tim dan pemasaran dibesarkan lebih awal untuk mengejar pertumbuhan.

---

## 12. Rilis bertahap

| Tahap | Lama | Isi | Kriteria selesai |
|---|---|---|---|
| **0 — Keputusan** | 1 minggu | Putuskan [K-01]–[K-17]; buka akun gateway; konsultasi pajak (PPN, PPh); syarat & ketentuan, kebijakan refund & privasi | Seluruh K bertanda putus |
| **1 — Fondasi** | 7–9 minggu | E1 Hak Akses, E2 Workspace & IUJP, E3 Langganan, E4 Gateway, E5 Harga & checkout, E6 Lencana, **E10 iklan pembuka modul (materi promosi sendiri)**, E14 paket tarif tetap, E11 dasar (MRR & tagihan), E12, kupon | Pelanggan membeli Silver sendiri, bayar QRIS/VA, aktif < 60 detik; Bronze melihat iklan pembuka setiap membuka modul; perpanjangan & tenggang berjalan; instalasi dedicated tidak berubah |
| **2 — AI premium & ERP SaaS** | 5–6 minggu | E7 Kredit AI, top-up, laporan biaya AI; E14 meteran karyawan aktif & site, true-up | Setiap permintaan AI tercatat biayanya; kredit tidak dapat minus; tagihan ERP People sesuai jumlah karyawan aktif |
| **3 — Toko & kursus** | 3–4 minggu | E8, E9 | Unduhan hanya lewat tautan bertanda tangan; lisensi tercatat |
| **4 — Iklan & sponsor** | 2–3 minggu | E10 sisanya: penjualan slot ke sponsor (termasuk pembuka modul per modul), rewarded, AdSense halaman publik, Ads Manager | Tidak ada iklan di halaman terlarang dan untuk pengguna berbayar (diuji otomatis) |
| **5 — Enterprise** | Berkelanjutan | Multi-site, API, alur kustom, corporate billing, pendampingan audit, program mitra penuh | Kontrak VVIP Cloud pertama |

---

## 13. Risiko, asumsi & keputusan

### 13.1 Risiko

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Pelanggan lama merasa "dikunci" | Kehilangan kepercayaan | Mode `dedicated` tidak berubah; pengumuman 30 hari; pelanggan Cloud awal di-*grandfather* harga 12 bulan |
| Kanibalisasi Dedicated | ARPU turun | Pagar paket §7.9; paket operasional berbayar |
| Biaya AI melonjak | Margin turun | Kredit, tahan-lalu-selesaikan, batas per jam, kalibrasi 60 hari, model hemat untuk tugas ringan |
| Webhook hilang / ganda | Aktivasi gagal / ganda | Idempotensi, rekonsiliasi harian |
| Kebocoran data antar-tenant | Hukum & reputasi | Cakupan berbasis persetujuan, uji otomatis batas tenant, `AksesAuditPerusahaan` diperluas |
| Lencana disalahartikan sebagai sertifikasi | Reputasi/hukum | Teks bantu wajib, tidak dicetak di dokumen resmi |
| Iklan pembuka setiap buka modul terasa mengganggu | Bronze pergi sebelum upgrade; konversi turun | Lewati setelah 5 detik, ajakan upgrade jelas, jeda minimum dapat diatur tanpa deploy, ukur 30 hari |
| Jumlah karyawan ERP diperdebatkan pelanggan | Sengketa tagihan | Daftar karyawan yang dihitung ditampilkan sebelum tagihan terbit; true-up di tagihan berikutnya |
| Pasar lebih kecil dari asumsi | Proyeksi meleset | Skenario konservatif; pengeluaran tim mengikuti MRR, bukan jadwal |

### 13.2 Asumsi

| Kode | Asumsi | Cara memverifikasi |
|---|---|---|
| A-01 | Pendaftar Bronze bulan pertama 60/120/180 (3 skenario), tumbuh 5–8%/bulan di tahun 1 | Data `kunjungan_harian` + uji iklan berbayar 1 bulan |
| A-02 | Konversi Bronze → berbayar 2,5% / 4% / 5% | Uji coba terbalik 60 hari pertama |
| A-03 | Churn bulanan Silver 3,5%, Gold 2,5%, Diamond 1,5% (moderat) | Kohort 6 bulan pertama |
| A-04 | Rata-rata IUJP per IUP: Gold 2, Diamond 6; 0,7%/bulan IUJP terhubung menjadi Silver | Data undangan setelah Tahap 1 |
| A-05 | Biaya AI rata-rata Rp200 per kredit terpakai | `ai_pemakaian` 60 hari |
| A-06 | Biaya gateway campuran 1,2% dari pembayaran | Penawaran resmi Midtrans/Xendit |
| A-07 | PPh final 0,5% dari omzet (PP 55/2022) selama omzet ≤ Rp4,8 M/tahun dan wajib pajak memenuhi syarat; di atasnya tarif umum | Konsultan pajak |
| A-08 | Jumlah IUP & IUJP aktif cukup untuk 400–900 pelanggan dalam 3 tahun | Data MODI / MINERBA One Map ESDM |
| A-09 | Harga katalog dedicated di etalase (Rp25 jt) adalah harga jual aktual | Pemilik |
| A-10 | Bronze aktif membuka 12 modul/bulan; 50% tayangan pembuka terjual ke sponsor dengan eCPM Rp60.000 | Log tayangan 30 hari + penawaran ke 5 vendor APD/konsultan |
| A-11 | ERP People dibeli 4% Gold (±100 karyawan) dan 12% Diamond (±250 karyawan); ERP Operations 2% Gold dan 6% Diamond (±1,5 site) | Wawancara 10 calon pelanggan IUP; data HRIS pelanggan dedicated |

### 13.3 Keputusan menunggu persetujuan

| Kode | Pertanyaan | Rekomendasi |
|---|---|---|
| K-01 | IUJP di workspace Gold/Diamond punya akun sendiri? | **Ya.** Akun & workspace sendiri, terhubung lewat undangan + persetujuan. IUJP terhubung gratis untuk merespons audit IUP |
| K-02 | Pengguna termasuk per paket | Bronze 1, Silver 3, Gold 10, Diamond 30; tambahan Rp29rb/pengguna/bln. (Rangkuman menulis Silver tanpa multi-user; 3 diperlukan karena audit butuh auditor + auditi) |
| K-03 | Modul Silver | 1 modul audit pilihan (bawaan SMKP) + Hazard Report + respons audit IUP |
| K-04 | Modul Gold per paket atau per IUP? | 1 modul per workspace; tambahan Rp149rb/modul (maks. 2) |
| K-05 | Masa tenggang & akses rewarded | Tenggang 7 hari penuh, lalu 30 hari hanya-baca + ekspor; rewarded 24 jam/1 aksi, maks. 3/hari |
| K-06 | Bayar per workspace atau per akun? | Per workspace (perusahaan) |
| K-07 | Harga termasuk PPN dan biaya transaksi? | Belum termasuk PPN (B2B); biaya gateway ditanggung EQOHSEE |
| K-08 | Uji coba terbalik Gold 14 hari | Ya |
| K-09 | Harga tahunan | 10 × bulanan + onboarding gratis |
| K-10 | Paket Operasional di Cloud | Ya: Safety Operations dan Environment & ESG Rp999rb/paket/bln untuk Gold & Diamond |
| K-11 | Harga lantai VVIP | Katalog dedicated yang berlaku (tahun ke-1 ±Rp47 jt untuk 23 aplikasi) |
| K-12 | Komisi mitra konsultan | 20% dari pembayaran 12 bulan pertama |
| K-13 | Gateway | Pilih satu dari penawaran biaya resmi termurah (Midtrans Snap atau Xendit Invoice); driver mendukung keduanya; jalur manual tetap |
| K-14 | Kursus berbayar | Ya, sertifikat penyelesaian (bukan sertifikasi kompetensi) |
| K-15 | Penyedia rewarded ads | Sponsor video internal dulu; jaringan iklan pihak ketiga setelah lalu lintas cukup |
| K-16 | Iklan untuk Bronze saat membuka modul | **Diputuskan pemilik: setiap membuka modul.** Rekomendasi pelaksanaan: Lewati setelah 5 detik, tidak tampil di pembayaran/uji coba/alur IUJP terhubung, jeda minimum bawaan 0 menit (dapat dinaikkan bila konversi turun) |
| K-17 | Harga ERP SaaS | ERP People Rp9.000/karyawan aktif/bln (min. Rp999rb); ERP Operations Rp1.499.000/site/bln; di atas 2.000 karyawan atau 5 site lewat VVIP |

---

## 14. Persetujuan dokumen

| Peran | Nama | Keputusan | Tanggal |
|---|---|---|---|
| Pemilik produk | | ☐ Setuju ☐ Setuju dengan catatan ☐ Revisi | |
| Keuangan / pajak | | ☐ Setuju ☐ Setuju dengan catatan ☐ Revisi | |
| Teknis | | ☐ Setuju ☐ Setuju dengan catatan ☐ Revisi | |
