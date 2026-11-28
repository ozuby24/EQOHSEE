# Model Pendapatan & Proyeksi Keuntungan — EQOHSEE

**Versi:** 1.0 (draf untuk ditinjau)
**Tanggal:** 28 November 2026
**Dokumen pasangan:** [PRD-Monetisasi-Membership.md](PRD-Monetisasi-Membership.md) · [FSD-Monetisasi-Membership.md](FSD-Monetisasi-Membership.md)
**Model yang dapat dijalankan:** [`proyeksi/model.cjs`](proyeksi/model.cjs) — seluruh angka di bawah dicetak oleh `node docs/monetisasi/proyeksi/tabel.cjs`. Ubah asumsi di `model.cjs`, jalankan ulang, dan tabel ikut berubah.

> Ini **proyeksi**, bukan janji. Angka pasar (pendaftar, konversi, churn) adalah asumsi [A-01]–[A-09] di PRD §13.2 yang harus diuji di 60–90 hari pertama. Gunakan skenario konservatif untuk keputusan pengeluaran, moderat untuk target, optimis untuk batas atas.

---

## 1. Cara membaca angka

- **Pendapatan** = pendapatan diakui per bulan. Langganan tahunan dihitung 10/12 harga bulanan per bulan (bayar 10, pakai 12). Kas tahunan sebenarnya masuk di depan — lebih baik untuk arus kas daripada yang tampak di tabel.
- **MRR** = langganan + add-on + layanan tahunan VVIP ÷ 12. Tidak termasuk penjualan sekali bayar (lisensi VVIP, produk digital, kursus, top-up AI, onboarding).
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
| Add-on | 20% Gold membeli 1 modul tambahan; 30% Diamond membeli rata-rata 3 slot IUJP; 5% Gold dan 25% Diamond membeli rata-rata 1,3 Paket Operasional; 10% Gold (+3 pengguna) dan 20% Diamond (+8 pengguna) |
| Onboarding | 25% Gold/Diamond baru membayar onboarding |
| Kredit AI | 10% pelanggan berbayar top-up rata-rata Rp180rb/bln; 0,5% Bronze aktif top-up Rp99rb. Pemakaian kuota: Silver 30, Gold 100, Diamond 400 kredit/bln |
| Produk digital & kursus | 1,5% Bronze aktif dan 4% Silver membeli produk rata-rata Rp55rb/bln; 0,8% Bronze aktif membeli kursus Rp199rb |
| Bronze aktif | 35% dari total pendaftar kumulatif |
| Iklan | 10.000 tayangan publik/bln tumbuh 6%/bln (thn 1), + 15 tayangan per Bronze aktif, RPM Rp15.000; sponsor rata-rata Rp1,5 jt/bln |
| VVIP | Lisensi Rp25 jt (sekali) + layanan tahunan Rp22 jt (server 4 vCPU/4 GB + pemeliharaan 23 aplikasi) |
| Biaya AI | Rp200 per kredit terpakai; COGS top-up 25% |
| Gateway | 1,2% dari pembayaran non-VVIP |
| Server | Rp3 jt/bln dasar + Rp12rb per pelanggan berbayar + Rp300 per Bronze aktif + Rp400rb per server VVIP |
| Komisi | Mitra 6% dari langganan + add-on (≈ 30% pelanggan lewat mitra × 20%); sponsor 10% |
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
| Paket Operasional | Rp999.000/bln |
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
| MRR bulan ke-12 | Rp13,6 jt | Rp40,0 jt | Rp80,9 jt |
| MRR bulan ke-36 | Rp44,3 jt | Rp162,9 jt | Rp351,0 jt |
| ARR bulan ke-36 (MRR × 12) | Rp531,3 jt | Rp1.954,8 jt | Rp4.211,9 jt |
| Pendapatan tahun 1 | Rp139,9 jt | Rp353,8 jt | Rp689,1 jt |
| Pendapatan tahun 2 | Rp377,1 jt | Rp1.087,6 jt | Rp2.146,6 jt |
| Pendapatan tahun 3 | Rp597,3 jt | Rp1.973,6 jt | Rp4.079,9 jt |
| Laba bersih tahun 1 | −Rp118,3 jt | −Rp8,4 jt | Rp75,1 jt |
| Laba bersih tahun 2 | −Rp18,3 jt | Rp357,6 jt | Rp797,0 jt |
| Laba bersih tahun 3 | Rp52,5 jt | Rp603,9 jt | Rp1.667,1 jt |
| Margin bersih tahun 3 | 9% | 31% | 41% |
| Margin kotor tahun 3 | 82% | 87% | 88% |
| Impas bulanan (pendapatan berulang menutup biaya) | bulan ke-29 | bulan ke-9 | bulan ke-8 |
| Balik modal kumulatif | > 36 bulan | bulan ke-15 | bulan ke-11 |
| Kebutuhan modal kerja (titik terendah kumulatif) | −Rp166,6 jt | −Rp85,6 jt | −Rp100,9 jt |
| Laba kumulatif 36 bulan | −Rp84,1 jt | Rp953,1 jt | Rp2.539,2 jt |

### Komposisi pendapatan tahun ke-3 — skenario moderat

| Aliran | Nilai tahun 3 | Porsi |
|---|---:|---:|
| A. Langganan membership | Rp1.140,3 jt | 58% |
| B. Add-on (modul, slot IUJP, paket, pengguna) | Rp326,8 jt | 17% |
| C. Top-up kredit AI | Rp88,8 jt | 5% |
| D. VVIP / dedicated (lisensi + layanan tahunan) | Rp152,7 jt | 8% |
| E1. Produk digital | Rp36,0 jt | 2% |
| E2. Kursus berbayar Learning Center | Rp60,1 jt | 3% |
| F. Iklan & sponsor | Rp122,4 jt | 6% |
| G. Layanan onboarding | Rp46,5 jt | 2% |
| **Total** | **Rp1.973,6 jt** | 100% |

### Struktur biaya tahun ke-3 — skenario moderat

| Pos biaya | Nilai tahun 3 | % pendapatan |
|---|---:|---:|
| Biaya AI (kredit terpakai + top-up) | Rp105,1 jt | 5% |
| Biaya payment gateway | Rp21,9 jt | 1% |
| Server, basis data, penyimpanan, surel | Rp116,5 jt | 6% |
| Bagi hasil konten & pengajar | Rp21,6 jt | 1% |
| Pemasaran + komisi mitra + komisi sponsor | Rp278,8 jt | 14% |
| Tim inti (developer, CS/sales, admin) | Rp720,0 jt | 36% |
| Umum & administrasi (akuntan, legal, perangkat) | Rp96,0 jt | 5% |
| Pajak penghasilan (asumsi) | Rp9,9 jt | 1% |
| **Total biaya** | **Rp1.369,8 jt** | 69% |
| **Laba bersih** | **Rp603,9 jt** | 31% |

### Jembatan kenaikan: dari simulasi dokumen awal ke model yang dioptimalkan

Basis tetap: 100 Silver, 100 Gold, 20 Diamond (angka pelanggan dari simulasi §12 dokumen rangkuman), harga final.

| Kode | Tuas | Tambahan omzet / bulan |
|---|---|---:|
| — | Simulasi awal dihitung ulang dengan harga final (langganan Rp70,8 jt + produk digital Rp5,0 jt + sponsor Rp3,0 jt) | Rp78,8 jt |
| T1 | Modul audit tambahan (Gold) | +Rp3,0 jt |
| T2 | Slot IUJP tambahan (Diamond) | +Rp0,9 jt |
| T3 | Paket modul operasional | +Rp11,5 jt |
| T4 | Pengguna tambahan | +Rp1,8 jt |
| T5 | Top-up kredit AI | +Rp4,0 jt |
| T6 | Jaringan IUJP → Silver (8%/tahun) | +Rp3,9 jt |
| T7 | Upgrade Gold → Diamond (10%/tahun) | +Rp4,0 jt |
| T8 | Kursus berbayar Learning Center | +Rp5,0 jt |
| T9 | VVIP dedicated (2 kontrak, dirata 12 bln) | +Rp7,8 jt |
| | **Total setelah optimasi** | **Rp120,6 jt** (+53%) |

### Ekonomi per pelanggan — skenario moderat

| Paket | ARPU efektif / bln | Biaya langsung / bln | Margin kontribusi / bln | % | Umur rata-rata | LTV | Batas CAC (LTV ÷ 3) |
|---|---:|---:|---:|---:|---:|---:|---:|
| Silver | Rp139.067 | Rp28.013 | Rp111.054 | 80% | 29 bln | Rp3,2 jt | Rp1,1 jt |
| Gold | Rp372.400 | Rp58.813 | Rp313.587 | 84% | 40 bln | Rp12,5 jt | Rp4,2 jt |
| Diamond | Rp745.733 | Rp145.693 | Rp600.041 | 80% | 67 bln | Rp40,0 jt | Rp13,3 jt |


---

## 6. Membaca hasilnya

1. **Langganan tetap tulang punggung** (58% pendapatan tahun ke-3 moderat), tetapi **add-on menyumbang 17%** tanpa menaikkan harga dasar. Paket Operasional saja bernilai Rp215 jt laba tahun ke-3.
2. **VVIP membuat tahun pertama hampir impas.** Tanpa VVIP, tahun ke-1 moderat rugi Rp69,6 jt; dengan dua kontrak VVIP ruginya tinggal Rp8,4 jt. Jual dedicated secara aktif selama Cloud tumbuh.
3. **Biaya terbesar adalah tim, bukan server atau AI.** Margin kotor 87% berarti hampir seluruh pendapatan pelanggan baru menjadi laba kontribusi. Rekrut mengikuti MRR (aturan praktis: biaya tim ≤ 45% MRR).
4. **Biaya AI aman selama tarif kredit dijaga.** Pada Rp200/kredit, biaya AI 5% pendapatan. Bila biaya nyata Rp400/kredit, laba tahun ke-3 moderat turun Rp82,9 jt (margin bersih 30,6% → 26,4%). Kalibrasi di hari ke-60 wajib.
5. **Konversi adalah angka paling sensitif.** Turun dari 4% ke 3% menghapus Rp257,5 jt laba tahun ke-3. Uji coba terbalik dan halaman modul terkunci yang menjual (bukan menyembunyikan) adalah investasi dengan hasil terbesar.
6. **Skenario konservatif belum balik modal di bulan ke-36.** Bila 90 hari pertama menunjukkan konversi < 2,5% dan pendaftar < 60/bulan, tahan perekrutan dan arahkan penjualan ke VVIP dan Gold langsung (IUP), yang membawa IUJP-nya sendiri.

## 7. Sensitivitas (moderat, laba bersih tahun ke-3; basis Rp603,9 jt)

Masing-masing mengubah satu asumsi saja; dihitung dengan `proyeksi/model.cjs`.

| Perubahan | Pendapatan thn 3 | Laba thn 3 | Selisih laba | Pelanggan bln 36 |
|---|---:|---:|---:|---:|
| Basis | Rp1.973,6 jt | Rp603,9 jt | — | 403 |
| Konversi 4% → 3% | Rp1.665,0 jt | Rp346,4 jt | −Rp257,5 jt | 315 |
| Tanpa Paket Operasional di Cloud | Rp1.740,3 jt | Rp388,5 jt | −Rp215,3 jt | 403 |
| Tanpa kontrak VVIP | Rp1.821,0 jt | Rp474,3 jt | −Rp129,5 jt | 403 |
| Biaya AI Rp200 → Rp400 per kredit | Rp1.973,6 jt | Rp520,9 jt | −Rp82,9 jt | 403 |
| Tanpa loop jaringan IUJP | Rp1.907,5 jt | Rp551,3 jt | −Rp52,6 jt | 359 |
| Churn Silver 3,5% → 5% | Rp1.924,3 jt | Rp564,6 jt | −Rp39,2 jt | 370 |
| Porsi tahunan 40% → 60% | Rp1.932,9 jt | Rp566,3 jt | −Rp37,6 jt* | 403 |
| Harga Silver Rp149rb → Rp199rb (konversi sama) | Rp2.076,2 jt | Rp698,5 jt | +Rp94,7 jt | 403 |

\* Model memakai churn yang sama untuk bulanan dan tahunan, jadi hanya diskonnya yang terlihat. Dalam praktik pelanggan tahunan jauh lebih jarang berhenti dan kasnya masuk 12 bulan di muka — keuntungan yang tidak tertangkap tabel ini.

Kenaikan harga Silver ke Rp199rb layak diuji A/B setelah 3 bulan data konversi tersedia; jangan diputuskan sebelum ada data, karena Silver adalah pintu masuk IUJP.

## 8. Lampiran — proyeksi bulanan skenario moderat

| Bln | Pendaftar baru | Silver | Gold | Diamond | VVIP | MRR | Pendapatan | Biaya | Laba | Kumulatif |
|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| 1 | 120 | 3 | 2 | 1 | 0 | Rp2,5 jt | Rp4,5 jt | Rp26,6 jt | −Rp22,1 jt | −Rp22,1 jt |
| 2 | 128 | 6 | 5 | 2 | 0 | Rp5,0 jt | Rp7,4 jt | Rp27,1 jt | −Rp19,7 jt | −Rp41,8 jt |
| 3 | 137 | 10 | 7 | 3 | 0 | Rp7,7 jt | Rp10,4 jt | Rp27,6 jt | −Rp17,2 jt | −Rp59,0 jt |
| 4 | 147 | 13 | 10 | 4 | 0 | Rp10,4 jt | Rp13,5 jt | Rp28,1 jt | −Rp14,6 jt | −Rp73,6 jt |
| 5 | 157 | 17 | 12 | 5 | 0 | Rp13,2 jt | Rp16,7 jt | Rp28,7 jt | −Rp12,0 jt | −Rp85,6 jt |
| 6 | 168 | 21 | 15 | 6 | 1 | Rp18,0 jt | Rp48,4 jt | Rp30,0 jt | Rp18,4 jt | −Rp67,2 jt |
| 7 | 180 | 26 | 17 | 7 | 1 | Rp21,0 jt | Rp26,8 jt | Rp30,5 jt | −Rp3,6 jt | −Rp70,8 jt |
| 8 | 193 | 30 | 20 | 8 | 1 | Rp24,2 jt | Rp30,5 jt | Rp31,1 jt | −Rp0,6 jt | −Rp71,4 jt |
| 9 | 206 | 35 | 23 | 9 | 1 | Rp27,5 jt | Rp35,7 jt | Rp31,9 jt | Rp3,8 jt | −Rp67,6 jt |
| 10 | 221 | 40 | 26 | 10 | 1 | Rp30,9 jt | Rp39,7 jt | Rp32,6 jt | Rp7,1 jt | −Rp60,6 jt |
| 11 | 236 | 45 | 28 | 11 | 1 | Rp34,5 jt | Rp43,8 jt | Rp33,3 jt | Rp10,5 jt | −Rp50,1 jt |
| 12 | 253 | 51 | 32 | 13 | 2 | Rp40,0 jt | Rp76,5 jt | Rp34,8 jt | Rp41,7 jt | −Rp8,4 jt |
| 13 | 263 | 57 | 35 | 14 | 2 | Rp43,8 jt | Rp55,8 jt | Rp55,5 jt | Rp0,4 jt | −Rp8,0 jt |
| 14 | 273 | 63 | 38 | 15 | 2 | Rp47,7 jt | Rp60,3 jt | Rp56,3 jt | Rp4,1 jt | −Rp4,0 jt |
| 15 | 284 | 69 | 41 | 17 | 2 | Rp51,7 jt | Rp66,4 jt | Rp57,2 jt | Rp9,1 jt | Rp5,2 jt |
| 16 | 295 | 75 | 44 | 18 | 2 | Rp55,8 jt | Rp71,1 jt | Rp58,1 jt | Rp13,0 jt | Rp18,2 jt |
| 17 | 307 | 81 | 47 | 20 | 2 | Rp59,9 jt | Rp75,9 jt | Rp58,9 jt | Rp17,0 jt | Rp35,1 jt |
| 18 | 320 | 88 | 51 | 21 | 3 | Rp66,0 jt | Rp109,2 jt | Rp60,5 jt | Rp48,6 jt | Rp83,8 jt |
| 19 | 332 | 95 | 54 | 23 | 3 | Rp70,4 jt | Rp89,2 jt | Rp61,3 jt | Rp27,9 jt | Rp111,7 jt |
| 20 | 346 | 101 | 58 | 25 | 3 | Rp74,9 jt | Rp94,4 jt | Rp62,2 jt | Rp32,2 jt | Rp143,9 jt |
| 21 | 360 | 109 | 61 | 26 | 3 | Rp79,5 jt | Rp101,2 jt | Rp63,4 jt | Rp37,9 jt | Rp181,8 jt |
| 22 | 374 | 116 | 65 | 28 | 3 | Rp84,2 jt | Rp106,7 jt | Rp64,3 jt | Rp42,4 jt | Rp224,1 jt |
| 23 | 389 | 124 | 68 | 30 | 3 | Rp89,1 jt | Rp112,4 jt | Rp65,4 jt | Rp47,0 jt | Rp271,2 jt |
| 24 | 404 | 131 | 72 | 32 | 4 | Rp95,9 jt | Rp145,0 jt | Rp66,9 jt | Rp78,1 jt | Rp349,2 jt |
| 25 | 412 | 139 | 76 | 34 | 4 | Rp100,9 jt | Rp125,8 jt | Rp107,8 jt | Rp17,9 jt | Rp367,2 jt |
| 26 | 421 | 147 | 80 | 36 | 4 | Rp106,0 jt | Rp131,6 jt | Rp108,9 jt | Rp22,7 jt | Rp389,9 jt |
| 27 | 429 | 155 | 83 | 38 | 4 | Rp111,1 jt | Rp137,6 jt | Rp110,0 jt | Rp27,6 jt | Rp417,5 jt |
| 28 | 438 | 163 | 87 | 40 | 4 | Rp116,2 jt | Rp143,5 jt | Rp111,0 jt | Rp32,5 jt | Rp450,0 jt |
| 29 | 446 | 171 | 91 | 42 | 4 | Rp121,4 jt | Rp149,5 jt | Rp112,1 jt | Rp37,4 jt | Rp487,4 jt |
| 30 | 455 | 179 | 95 | 44 | 5 | Rp128,5 jt | Rp182,5 jt | Rp113,8 jt | Rp68,7 jt | Rp556,1 jt |
| 31 | 465 | 187 | 98 | 46 | 5 | Rp133,8 jt | Rp163,6 jt | Rp114,7 jt | Rp48,9 jt | Rp605,0 jt |
| 32 | 474 | 195 | 102 | 48 | 5 | Rp139,1 jt | Rp169,8 jt | Rp115,9 jt | Rp54,0 jt | Rp658,9 jt |
| 33 | 483 | 203 | 106 | 50 | 5 | Rp144,5 jt | Rp176,1 jt | Rp117,0 jt | Rp59,1 jt | Rp718,0 jt |
| 34 | 493 | 212 | 110 | 52 | 5 | Rp150,0 jt | Rp182,5 jt | Rp118,2 jt | Rp64,3 jt | Rp782,3 jt |
| 35 | 503 | 220 | 114 | 55 | 5 | Rp155,5 jt | Rp188,9 jt | Rp119,3 jt | Rp69,6 jt | Rp851,9 jt |
| 36 | 513 | 228 | 118 | 57 | 6 | Rp162,9 jt | Rp222,2 jt | Rp121,0 jt | Rp101,2 jt | Rp953,1 jt |
