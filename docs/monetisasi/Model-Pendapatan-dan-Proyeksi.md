# Model Pendapatan & Proyeksi Keuntungan — EQOHSEE

**Versi:** 1.0 (draf untuk ditinjau)
**Tanggal:** 28 November 2026
**Dokumen pasangan:** [PRD-Monetisasi-Membership.md](PRD-Monetisasi-Membership.md) · [FSD-Monetisasi-Membership.md](FSD-Monetisasi-Membership.md)
**Model yang dapat dijalankan:** [`proyeksi/model.cjs`](proyeksi/model.cjs) — seluruh angka di bawah dicetak oleh `node docs/monetisasi/proyeksi/tabel.cjs`. Ubah asumsi di `model.cjs`, jalankan ulang, dan tabel ikut berubah.

> Ini **proyeksi**, bukan janji. Angka pasar (pendaftar, konversi, churn) adalah asumsi [A-01]–[A-09] di PRD §13.2 yang harus diuji di 60–90 hari pertama. Gunakan skenario konservatif untuk keputusan pengeluaran, moderat untuk target, optimis untuk batas atas.

---

## 1. Cara membaca angka

- **Pendapatan** = pendapatan diakui per bulan. Langganan tahunan dihitung 10/12 harga bulanan per bulan (bayar 10, pakai 12). Kas tahunan sebenarnya masuk di depan — lebih baik untuk arus kas daripada yang tampak di tabel.
- **MRR** = langganan + add-on + ERP SaaS & Paket Operasional + layanan tahunan VVIP ÷ 12. Tidak termasuk penjualan sekali bayar (lisensi VVIP, produk digital, kursus, top-up AI, onboarding).
- **PPN tidak dihitung sebagai pendapatan** — ia titipan untuk negara.
- **Laba bersih** = pendapatan − semua biaya operasional − pajak penghasilan (asumsi PPh final 0,5% omzet selama omzet setahun ≤ Rp4,8 M; di atasnya 22% laba).
- Pelanggan dihitung sebagai bilangan pecahan di dalam model dan dibulatkan saat ditampilkan.

## 2. Mesin pendapatan

```text
Pendaftar Bronze ──(konversi 2,5–5%)──► Silver 65% · Gold 28% · Diamond 7%
Penjualan langsung IUP ───────────────► Gold 0,5–2/bln · Diamond 0,2–1/bln
Gold × 2 IUJP + Diamond × 6 IUJP ──(0,7%/bln)──► Silver baru (loop jaringan)
Silver ──0,5%/bln──► Gold ──1%/bln──► Diamond (upgrade)
Kontrak VVIP: 1 / 2 / 4 per tahun
Gold & Diamond ──► ERP SaaS (per karyawan / per site) + Paket Operasional tarif tetap
Bronze aktif × 12 buka modul/bln ──► iklan pembuka modul (50% terjual ke sponsor)
```

## 3. Asumsi per skenario

| Asumsi | Konservatif | Moderat | Optimis |
|---|---:|---:|---:|
| Pendaftar Bronze bulan pertama | 60 | 120 | 180 |
| Pertumbuhan pendaftar / bulan (thn 1 · 2 · 3) | 5% · 3% · 1,5% | 7% · 4% · 2% | 8% · 4% · 2% |
| Konversi Bronze → berbayar | 2,5% | 4% | 5% |
| Penjualan langsung Gold / Diamond per bulan | 0,5 / 0,2 | 1 / 0,5 | 2 / 1 |
| Kontrak VVIP per tahun | 1 | 2 | 4 |
| Churn bulanan Silver · Gold · Diamond | 4,5% · 3,2% · 2,0% | 3,5% · 2,5% · 1,5% | 2,8% · 2,0% · 1,2% |
| Porsi bayar tahunan | 35% | 40% | 45% |
| Sponsor aktif maksimum (mulai bln 6) | 3 | 6 | 10 |
| Tim inti / bulan (thn 1 · 2 · 3) | Rp12 · 18 · 25 jt | Rp15 · 30 · 60 jt | Rp25 · 50 · 90 jt |
| Pemasaran dasar / bulan (thn 1 · 2 · 3) | Rp3 · 4 · 5 jt | Rp5 · 8 · 15 jt | Rp10 · 20 · 35 jt |
| Umum & administrasi / bulan | Rp2 · 3 · 4 jt | Rp3 · 5 · 8 jt | Rp5 · 10 · 15 jt |

**Asumsi bersama (ketiga skenario):**

| Kelompok | Asumsi |
|---|---|
| Add-on | 20% Gold membeli 1 modul tambahan; 30% Diamond membeli rata-rata 3 slot IUJP; 10% Gold (+3 pengguna) dan 20% Diamond (+8 pengguna) |
| Paket Operasional tarif tetap | 4% Gold dan 18% Diamond membeli rata-rata 1,2 paket (Safety Operations / Environment & ESG) |
| ERP SaaS People | 4% Gold (rata-rata 100 karyawan → kena minimum Rp999rb) dan 12% Diamond (rata-rata 250 karyawan × Rp9.000 = Rp2,25 jt/bln) |
| ERP SaaS Operations | 2% Gold (1 site) dan 6% Diamond (rata-rata 1,5 site) × Rp1.499.000 |
| Iklan pembuka modul | Setiap Bronze aktif membuka 12 modul/bln = 12 tayangan; 50% terjual ke sponsor dengan eCPM Rp60.000 (sisanya promosi upgrade milik sendiri, tanpa uang) |
| Onboarding | 25% Gold/Diamond baru membayar onboarding |
| Kredit AI | 10% pelanggan berbayar top-up rata-rata Rp180rb/bln; 0,5% Bronze aktif top-up Rp99rb. Pemakaian kuota: Silver 30, Gold 100, Diamond 400 kredit/bln |
| Produk digital & kursus | 1,5% Bronze aktif dan 4% Silver membeli produk rata-rata Rp55rb/bln; 0,8% Bronze aktif membeli kursus Rp199rb |
| Bronze aktif | 35% dari total pendaftar kumulatif |
| Iklan | 10.000 tayangan publik/bln tumbuh 6%/bln (thn 1), + 15 tayangan per Bronze aktif, RPM Rp15.000; sponsor rata-rata Rp1,5 jt/bln |
| VVIP | Lisensi Rp25 jt (sekali) + layanan tahunan Rp22 jt (server 4 vCPU/4 GB + pemeliharaan 23 aplikasi) |
| Biaya AI | Rp200 per kredit terpakai; COGS top-up 25% |
| Gateway | 1,2% dari pembayaran non-VVIP |
| Server | Rp3 jt/bln dasar + Rp12rb per pelanggan berbayar + Rp300 per Bronze aktif + Rp400rb per server VVIP |
| Komisi | Mitra 6% dari langganan + add-on + ERP (≈ 30% pelanggan lewat mitra × 20%); sponsor 10% |
| Konten | Produk digital 10%; pengajar kursus 30% |
| Pajak | PPh final 0,5% omzet (≤ Rp4,8 M/thn); di atasnya 22% laba |

---

## 4. Harga yang dipakai model

| Butir | Harga |
|---|---:|
| Silver / Gold / Diamond per bulan | Rp149.000 / Rp399.000 / Rp799.000 |
| Tahunan | 10 × bulanan |
| Modul audit tambahan | Rp149.000/bln |
| Slot IUJP tambahan | Rp49.000/bln |
| Paket Operasional tarif tetap (Safety Operations, Environment & ESG) | Rp999.000/bln |
| ERP SaaS People (HRIS, payroll, absensi, Authority, LMS perusahaan) | Rp9.000/karyawan aktif/bln, minimum Rp999.000 |
| ERP SaaS Operations (gudang, maintenance & P2H, dispatch, biaya, mine ops) | Rp1.499.000/site/bln |
| Iklan pembuka modul (sponsor) | eCPM Rp60.000 (Rp60 per tayangan) |
| Pengguna tambahan | Rp29.000/bln |
| Onboarding Gold / Diamond | Rp1.500.000 / Rp3.500.000 |
| VVIP | Rp25.000.000 + Rp22.000.000/thn |

---

## 5. Hasil

### Ringkasan tiga skenario

| Indikator | Konservatif | Moderat | Optimis |
|---|---:|---:|---:|
| Pelanggan berbayar bulan ke-12 | 29 | 95 | 195 |
| Pelanggan berbayar bulan ke-36 | 95 | 403 | 890 |
| Kontrak VVIP s.d. bulan ke-36 | 3 | 6 | 12 |
| MRR bulan ke-12 | Rp15,5 jt | Rp45,7 jt | Rp92,4 jt |
| MRR bulan ke-36 | Rp50,6 jt | Rp186,8 jt | Rp402,5 jt |
| ARR bulan ke-36 (MRR × 12) | Rp607,4 jt | Rp2.241,2 jt | Rp4.830,5 jt |
| Pendapatan tahun 1 | Rp152,6 jt | Rp389,7 jt | Rp760,2 jt |
| Pendapatan tahun 2 | Rp416,2 jt | Rp1.212,0 jt | Rp2.402,6 jt |
| Pendapatan tahun 3 | Rp666,1 jt | Rp2.216,6 jt | Rp4.592,9 jt |
| Laba bersih tahun 1 | −Rp106,6 jt | Rp24,8 jt | Rp140,8 jt |
| Laba bersih tahun 2 | Rp17,9 jt | Rp472,8 jt | Rp1.033,9 jt |
| Laba bersih tahun 3 | Rp116,3 jt | Rp828,9 jt | Rp2.142,0 jt |
| Margin bersih tahun 3 | 17% | 37% | 47% |
| Margin kotor tahun 3 | 84% | 88% | 89% |
| Impas bulanan (pendapatan berulang menutup biaya) | bulan ke-20 | bulan ke-8 | bulan ke-7 |
| Balik modal kumulatif | bulan ke-36 | bulan ke-12 | bulan ke-10 |
| Kebutuhan modal kerja (titik terendah kumulatif) | −Rp133,8 jt | −Rp79,8 jt | −Rp89,6 jt |
| Laba kumulatif 36 bulan | Rp27,6 jt | Rp1.326,5 jt | Rp3.316,7 jt |

### Komposisi pendapatan tahun ke-3 — skenario moderat

| Aliran | Nilai tahun 3 | Porsi |
|---|---:|---:|
| A. Langganan membership | Rp1.140,3 jt | 51% |
| B. Add-on (modul audit, slot IUJP, pengguna) | Rp93,5 jt | 4% |
| H. ERP SaaS & Paket Operasional | Rp462,7 jt | 21% |
| C. Top-up kredit AI | Rp88,8 jt | 4% |
| D. VVIP / dedicated (lisensi + layanan tahunan) | Rp152,7 jt | 7% |
| E1. Produk digital | Rp36,0 jt | 2% |
| E2. Kursus berbayar Learning Center | Rp60,1 jt | 3% |
| F1. Iklan pembuka modul (Bronze) | Rp13,6 jt | 1% |
| F2. Iklan publik & sponsor | Rp122,4 jt | 6% |
| G. Layanan onboarding | Rp46,5 jt | 2% |
| **Total** | **Rp2.216,6 jt** | 100% |

### Struktur biaya tahun ke-3 — skenario moderat

| Pos biaya | Nilai tahun 3 | % pendapatan |
|---|---:|---:|
| Biaya AI (kredit terpakai + top-up) | Rp105,1 jt | 5% |
| Biaya payment gateway | Rp24,8 jt | 1% |
| Server, basis data, penyimpanan, surel | Rp116,5 jt | 5% |
| Bagi hasil konten & pengajar | Rp21,6 jt | 1% |
| Pemasaran + komisi mitra + komisi sponsor | Rp292,6 jt | 13% |
| Tim inti (developer, CS/sales, admin) | Rp720,0 jt | 32% |
| Umum & administrasi (akuntan, legal, perangkat) | Rp96,0 jt | 4% |
| Pajak penghasilan (asumsi) | Rp11,1 jt | 0% |
| **Total biaya** | **Rp1.387,7 jt** | 63% |
| **Laba bersih** | **Rp828,9 jt** | 37% |

### Jembatan kenaikan: dari simulasi dokumen awal ke model yang dioptimalkan

Basis tetap: 100 Silver, 100 Gold, 20 Diamond (angka pelanggan dari simulasi §12 dokumen rangkuman), harga final.

| Kode | Tuas | Tambahan omzet / bulan |
|---|---|---:|
| — | Simulasi awal dihitung ulang dengan harga final (langganan Rp70,8 jt + produk digital Rp5,0 jt + sponsor Rp3,0 jt) | Rp78,8 jt |
| T1 | Modul audit tambahan (Gold) | +Rp3,0 jt |
| T2 | Slot IUJP tambahan (Diamond) | +Rp0,9 jt |
| T3 | Paket Operasional & ERP SaaS (per karyawan / site) | +Rp23,4 jt |
| T4 | Pengguna tambahan | +Rp1,8 jt |
| T5 | Top-up kredit AI | +Rp4,0 jt |
| T6 | Jaringan IUJP → Silver (8%/tahun) | +Rp3,9 jt |
| T7 | Upgrade Gold → Diamond (10%/tahun) | +Rp4,0 jt |
| T8 | Kursus berbayar Learning Center | +Rp5,0 jt |
| T9 | VVIP dedicated (2 kontrak, dirata 12 bln) | +Rp7,8 jt |
| T10 | Iklan pembuka modul (2.000 Bronze aktif) | +Rp0,7 jt |
| | **Total setelah optimasi** | **Rp133,2 jt** (+69%) |

### Ekonomi per pelanggan — skenario moderat

| Paket | ARPU efektif / bln | Biaya langsung / bln | Margin kontribusi / bln | % | Umur rata-rata | LTV | Batas CAC (LTV ÷ 3) |
|---|---:|---:|---:|---:|---:|---:|---:|
| Silver | Rp139.067 | Rp28.013 | Rp111.054 | 80% | 29 bln | Rp3,2 jt | Rp1,1 jt |
| Gold | Rp372.400 | Rp58.813 | Rp313.587 | 84% | 40 bln | Rp12,5 jt | Rp4,2 jt |
| Diamond | Rp745.733 | Rp145.693 | Rp600.041 | 80% | 67 bln | Rp40,0 jt | Rp13,3 jt |


---

## 6. Membaca hasilnya

1. **Langganan tetap tulang punggung** (51% pendapatan tahun ke-3 moderat), dan **ERP SaaS + Paket Operasional menjadi aliran kedua terbesar (21%)**. Harga per karyawan dan per site membuat pelanggan besar membayar sesuai ukurannya: tanpa ERP SaaS per karyawan/site, laba tahun ke-3 turun Rp276,7 jt.
2. **VVIP membuat tahun pertama tidak merugi.** Tanpa VVIP, tahun ke-1 moderat rugi Rp36,4 jt; dengan dua kontrak VVIP labanya Rp24,8 jt. Jual dedicated secara aktif selama Cloud tumbuh.
3. **Biaya terbesar adalah tim, bukan server atau AI.** Margin kotor 88% berarti hampir seluruh pendapatan pelanggan baru menjadi laba kontribusi. Rekrut mengikuti MRR (aturan praktis: biaya tim ≤ 45% MRR).
4. **Biaya AI aman selama tarif kredit dijaga.** Pada Rp200/kredit, biaya AI 5% pendapatan. Bila biaya nyata Rp400/kredit, laba tahun ke-3 moderat turun Rp82,9 jt (margin bersih 37,4% → 33,7%). Kalibrasi di hari ke-60 wajib.
5. **Konversi adalah angka paling sensitif.** Turun dari 4% ke 3% menghapus Rp294,8 jt laba tahun ke-3.
6. **Iklan pembuka modul: uang langsungnya kecil, pengaruhnya ke konversi besar.** Pada ±48.800 tayangan/bulan di bulan ke-36, pendapatan iklannya hanya Rp13,6 jt di tahun ke-3. Nilai sebenarnya ada di tombol "Hilangkan iklan — mulai Silver": bila iklan pembuka menaikkan konversi 0,5 poin, laba tahun ke-3 bertambah Rp147,4 jt. Bila terlalu mengganggu dan konversi turun 0,5 poin, kerugiannya kira-kira sama besar. Ukur konversi dan retensi Bronze di 30 hari pertama setelah iklan menyala.
7. **Skenario konservatif baru balik modal tepat di bulan ke-36.** Bila 90 hari pertama menunjukkan konversi < 2,5% dan pendaftar < 60/bulan, tahan perekrutan dan arahkan penjualan ke VVIP dan Gold langsung (IUP), yang membawa IUJP-nya sendiri.

## 7. Sensitivitas (moderat, laba bersih tahun ke-3; basis Rp828,9 jt)

Masing-masing mengubah satu asumsi saja; dihitung dengan `proyeksi/model.cjs`.

| Perubahan | Pendapatan thn 3 | Laba thn 3 | Selisih laba | Pelanggan bln 36 |
|---|---:|---:|---:|---:|
| Basis | Rp2.216,6 jt | Rp828,9 jt | — | 403 |
| Konversi 4% → 3% | Rp1.867,6 jt | Rp534,2 jt | −Rp294,8 jt | 315 |
| Tanpa ERP SaaS per karyawan/site | Rp1.916,8 jt | Rp552,2 jt | −Rp276,7 jt | 403 |
| Tanpa Paket Operasional tarif tetap | Rp2.053,7 jt | Rp678,6 jt | −Rp150,3 jt | 403 |
| Tanpa kontrak VVIP | Rp2.063,9 jt | Rp699,4 jt | −Rp129,5 jt | 403 |
| Biaya AI Rp200 → Rp400 per kredit | Rp2.216,6 jt | Rp746,0 jt | −Rp82,9 jt | 403 |
| Tanpa loop jaringan IUJP | Rp2.149,4 jt | Rp775,3 jt | −Rp53,6 jt | 359 |
| Churn Silver 3,5% → 5% | Rp2.166,4 jt | Rp788,9 jt | −Rp40,0 jt | 370 |
| Porsi tahunan 40% → 60% | Rp2.175,9 jt | Rp791,3 jt | −Rp37,6 jt* | 403 |
| Tanpa iklan pembuka modul (pendapatan iklannya saja) | Rp2.203,0 jt | Rp815,6 jt | −Rp13,3 jt | 403 |
| Iklan pembuka menaikkan konversi +0,5 poin | Rp2.391,1 jt | Rp976,3 jt | +Rp147,4 jt | 447 |
| Harga Silver Rp149rb → Rp199rb (konversi sama) | Rp2.319,2 jt | Rp923,6 jt | +Rp94,7 jt | 403 |

\* Model memakai churn yang sama untuk bulanan dan tahunan, jadi hanya diskonnya yang terlihat. Dalam praktik pelanggan tahunan jauh lebih jarang berhenti dan kasnya masuk 12 bulan di muka — keuntungan yang tidak tertangkap tabel ini.

Kenaikan harga Silver ke Rp199rb layak diuji A/B setelah 3 bulan data konversi tersedia; jangan diputuskan sebelum ada data, karena Silver adalah pintu masuk IUJP.

## 8. Lampiran — proyeksi bulanan skenario moderat

| Bln | Pendaftar baru | Silver | Gold | Diamond | VVIP | MRR | Pendapatan | Biaya | Laba | Kumulatif |
|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| 1 | 120 | 3 | 2 | 1 | 0 | Rp2,9 jt | Rp4,9 jt | Rp26,6 jt | −Rp21,7 jt | −Rp21,7 jt |
| 2 | 128 | 6 | 5 | 2 | 0 | Rp5,8 jt | Rp8,2 jt | Rp27,1 jt | −Rp18,9 jt | −Rp40,6 jt |
| 3 | 137 | 10 | 7 | 3 | 0 | Rp8,9 jt | Rp11,6 jt | Rp27,7 jt | −Rp16,1 jt | −Rp56,7 jt |
| 4 | 147 | 13 | 10 | 4 | 0 | Rp12,0 jt | Rp15,2 jt | Rp28,3 jt | −Rp13,1 jt | −Rp69,8 jt |
| 5 | 157 | 17 | 12 | 5 | 0 | Rp15,3 jt | Rp18,8 jt | Rp28,8 jt | −Rp10,0 jt | −Rp79,8 jt |
| 6 | 168 | 21 | 15 | 6 | 1 | Rp20,5 jt | Rp51,0 jt | Rp30,2 jt | Rp20,8 jt | −Rp58,9 jt |
| 7 | 180 | 26 | 17 | 7 | 1 | Rp24,0 jt | Rp30,0 jt | Rp30,7 jt | −Rp0,7 jt | −Rp59,7 jt |
| 8 | 193 | 30 | 20 | 8 | 1 | Rp27,7 jt | Rp34,1 jt | Rp31,4 jt | Rp2,7 jt | −Rp56,9 jt |
| 9 | 206 | 35 | 23 | 9 | 1 | Rp31,5 jt | Rp39,9 jt | Rp32,2 jt | Rp7,7 jt | −Rp49,2 jt |
| 10 | 221 | 40 | 26 | 10 | 1 | Rp35,4 jt | Rp44,4 jt | Rp33,0 jt | Rp11,5 jt | −Rp37,8 jt |
| 11 | 236 | 45 | 28 | 11 | 1 | Rp39,5 jt | Rp49,1 jt | Rp33,7 jt | Rp15,4 jt | −Rp22,4 jt |
| 12 | 253 | 51 | 32 | 13 | 2 | Rp45,7 jt | Rp82,4 jt | Rp35,2 jt | Rp47,1 jt | Rp24,8 jt |
| 13 | 263 | 57 | 35 | 14 | 2 | Rp50,1 jt | Rp62,4 jt | Rp55,9 jt | Rp6,4 jt | Rp31,2 jt |
| 14 | 273 | 63 | 38 | 15 | 2 | Rp54,6 jt | Rp67,5 jt | Rp56,8 jt | Rp10,7 jt | Rp41,9 jt |
| 15 | 284 | 69 | 41 | 17 | 2 | Rp59,1 jt | Rp74,2 jt | Rp57,8 jt | Rp16,4 jt | Rp58,3 jt |
| 16 | 295 | 75 | 44 | 18 | 2 | Rp63,9 jt | Rp79,6 jt | Rp58,7 jt | Rp20,9 jt | Rp79,2 jt |
| 17 | 307 | 81 | 47 | 20 | 2 | Rp68,7 jt | Rp85,1 jt | Rp59,6 jt | Rp25,5 jt | Rp104,7 jt |
| 18 | 320 | 88 | 51 | 21 | 3 | Rp75,4 jt | Rp119,0 jt | Rp61,3 jt | Rp57,8 jt | Rp162,4 jt |
| 19 | 332 | 95 | 54 | 23 | 3 | Rp80,5 jt | Rp99,8 jt | Rp62,1 jt | Rp37,7 jt | Rp200,2 jt |
| 20 | 346 | 101 | 58 | 25 | 3 | Rp85,7 jt | Rp105,8 jt | Rp63,1 jt | Rp42,7 jt | Rp242,9 jt |
| 21 | 360 | 109 | 61 | 26 | 3 | Rp91,0 jt | Rp113,4 jt | Rp64,3 jt | Rp49,1 jt | Rp292,0 jt |
| 22 | 374 | 116 | 65 | 28 | 3 | Rp96,4 jt | Rp119,6 jt | Rp65,3 jt | Rp54,3 jt | Rp346,3 jt |
| 23 | 389 | 124 | 68 | 30 | 3 | Rp102,1 jt | Rp126,1 jt | Rp66,4 jt | Rp59,7 jt | Rp406,0 jt |
| 24 | 404 | 131 | 72 | 32 | 4 | Rp109,6 jt | Rp159,5 jt | Rp68,0 jt | Rp91,5 jt | Rp497,6 jt |
| 25 | 412 | 139 | 76 | 34 | 4 | Rp115,4 jt | Rp141,2 jt | Rp109,0 jt | Rp32,2 jt | Rp529,8 jt |
| 26 | 421 | 147 | 80 | 36 | 4 | Rp121,3 jt | Rp147,9 jt | Rp110,1 jt | Rp37,8 jt | Rp567,5 jt |
| 27 | 429 | 155 | 83 | 38 | 4 | Rp127,2 jt | Rp154,6 jt | Rp111,2 jt | Rp43,4 jt | Rp611,0 jt |
| 28 | 438 | 163 | 87 | 40 | 4 | Rp133,2 jt | Rp161,5 jt | Rp112,4 jt | Rp49,1 jt | Rp660,1 jt |
| 29 | 446 | 171 | 91 | 42 | 4 | Rp139,2 jt | Rp168,4 jt | Rp113,5 jt | Rp54,9 jt | Rp714,9 jt |
| 30 | 455 | 179 | 95 | 44 | 5 | Rp147,1 jt | Rp202,2 jt | Rp115,2 jt | Rp87,0 jt | Rp801,9 jt |
| 31 | 465 | 187 | 98 | 46 | 5 | Rp153,3 jt | Rp184,2 jt | Rp116,3 jt | Rp68,0 jt | Rp869,9 jt |
| 32 | 474 | 195 | 102 | 48 | 5 | Rp159,5 jt | Rp191,4 jt | Rp117,5 jt | Rp73,9 jt | Rp943,8 jt |
| 33 | 483 | 203 | 106 | 50 | 5 | Rp165,7 jt | Rp198,6 jt | Rp118,7 jt | Rp79,9 jt | Rp1.023,7 jt |
| 34 | 493 | 212 | 110 | 52 | 5 | Rp172,1 jt | Rp205,9 jt | Rp119,9 jt | Rp86,0 jt | Rp1.109,7 jt |
| 35 | 503 | 220 | 114 | 55 | 5 | Rp178,5 jt | Rp213,3 jt | Rp121,1 jt | Rp92,1 jt | Rp1.201,9 jt |
| 36 | 513 | 228 | 118 | 57 | 6 | Rp186,8 jt | Rp247,5 jt | Rp122,9 jt | Rp124,7 jt | Rp1.326,5 jt |
