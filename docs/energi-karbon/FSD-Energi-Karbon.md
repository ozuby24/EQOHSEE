# FSD — Modul Manajemen Energi & Karbon EQOHSEE

**Jenis dokumen:** Functional Specification Document (FSD)
**Versi:** 1.0 (draf untuk ditinjau)
**Tanggal:** 21 November 2026
**Dokumen induk:** [PRD-Energi-Karbon.md](PRD-Energi-Karbon.md) — kode kebutuhan (F*, BOD-*), asumsi (A-*), dan referensi (R-*) merujuk ke PRD.
**Stack:** Laravel 13 · PHP 8.4 · Inertia 3 + Vue 3 (TypeScript) + Tailwind · PhpSpreadsheet · SQLite (dev) / PostgreSQL (prod) · Flutter (`mobile/`)

> Prinsip yang **dipertahankan** dari modul yang ada:
> 1. **Data aktivitas disimpan mentah** (liter, kWh, m³, ton, HM); GJ, TOE, tCO₂e, Rp dihitung saat dibaca.
> 2. **Batas perusahaan via global scope** `MilikPerusahaan` + trait `BerpemilikPerusahaan` — tidak ada `where company_id` manual di controller.
> 3. **`App\Support\Energi` tetap menjadi satu-satunya pintu rumus**; yang berubah adalah sumber faktornya.
>
> Prinsip **baru**: laporan yang sudah dikunci **membekukan snapshot** data & faktor, sehingga perubahan faktor tahun depan tidak mengubah laporan yang sudah disampaikan ke regulator — tanpa melanggar prinsip 1 untuk tampilan operasional.

---

## Daftar isi
1. Arsitektur & struktur kode
2. Konsep inti & glosarium
3. Navigasi & rute
4. Peran & matriks hak akses
5. Model data (tabel baru & perubahan)
6. Mesin perhitungan (rumus)
7. Alur kerja data & kunci periode
8. Spesifikasi layar
9. Dasbor BOD — Executive Control Tower
10. Pusat Pelaporan — templat laporan
11. API & integrasi
12. Notifikasi & kalender kepatuhan
13. Aplikasi seluler
14. Migrasi dari modul lama
15. Perbaikan cacat yang diketahui
16. Pengujian & kriteria penerimaan
17. Lampiran: nilai faktor acuan awal

---

## 1. Arsitektur & struktur kode

```
app/
  Http/Controllers/
    EnergyController.php            (ada — dirampingkan, halaman operasional)
    Energi/
      FaktorController.php          registri faktor & konversi
      SumberController.php          sumber emisi/energi & site
      InventarisController.php      inventarisasi GRK (scope 1/2/3)
      ManajemenEnergiController.php PP 33: TOE, manajer/auditor, program, audit
      EnpiController.php            EnPI/EnB, tinjauan energi, SEU
      PeriodeController.php         verifikasi, kunci, koreksi
      LaporanController.php         pusat pelaporan & ekspor
      BodController.php             dasbor BOD
      Api/IngestController.php      ingest data mesin (token)
      Api/SelulerController.php     sinkronisasi Flutter
  Support/
    Energi.php                      (ada — fasad, kompatibel mundur)
    Energi/
      RegistriFaktor.php            ambil faktor berlaku per tanggal/wilayah/pemasok
      Kalkulator.php                GJ, TOE, CO2/CH4/N2O, CO2e, biogenik, Rp
      Inventaris.php                agregasi per scope/kategori/gas
      Ambang.php                    status wajib manajemen energi
      Enpi.php                      regresi & normalisasi baseline
      Anomali.php                   deteksi lonjakan & unit boros
      Snapshot.php                  pembekuan laporan
      Templat/*.php                 satu kelas per templat laporan
  Models/Energi/*.php               model baru (lihat §5)
  Jobs/Energi/
    HitungAgregatHarian.php         materialisasi agregat
    DeteksiAnomali.php
  Console/Commands/
    EnergiPengingat.php             kalender kepatuhan (dijadwalkan harian)
    EnergiImporFaktor.php           impor paket faktor awal
resources/js/Pages/Energi/
  Halaman.vue (dipecah) → Input.vue, Konsumsi.vue, BahanBakar.vue, Listrik.vue,
  Peralatan.vue, Kpi.vue, Baseline.vue, Penghematan.vue, Karbon.vue,
  Kalkulator.vue, Laporan.vue, Master.vue
  Faktor/*.vue, Inventaris/*.vue, Manajemen/*.vue, Periode/*.vue, Bod/*.vue
resources/js/Components/Energi/
  KartuKpi.vue, GrafikTren.vue, PetaPanas.vue, TabelDrill.vue, PenandaMutu.vue,
  PanelRumus.vue (klik angka → rumus & faktor), PemilihPeriode.vue
```

**Penyelarasan dengan repo:** komponen bersama yang dipakai ulang — `KopDokumen` (nomor & kop dokumen), `PersetujuanParaf` + `Tahap` (paraf bertahap), `TindakLanjut` (morph, untuk instruksi BOD & rekomendasi audit), `ActivityLog`, `Menu`/`Modules` (bilah samping & katalog), `DasborGrafik`, `Dialog.vue`, `propHalaman()`.

**Agregat termaterialisasi:** tabel `energi_agregat_harian` diisi oleh job `HitungAgregatHarian` setiap kali data harian disimpan/diverifikasi (antrian) dan ulang penuh setiap malam; dasbor BOD membaca agregat, tidak membaca tabel log mentah.

---

## 2. Konsep inti & glosarium

| Istilah | Arti di sistem |
|---|---|
| **Perusahaan** (`companies`) | Entitas hukum pemegang IUP/IUJP — batas pelaporan regulasi |
| **Site** (`energi_sites`) | Lokasi operasi (pit, port, hauling road, camp); satu perusahaan bisa punya banyak site |
| **Sumber** (`energi_sumber`) | Titik pemakaian/emisi: unit alat, genset, area listrik, tangki, kendaraan ringan, pembangkit captive, PLTS, switchgear |
| **Data aktivitas** | Kuantitas mentah per tanggal per sumber: L, kWh, m³, kg, ton |
| **Faktor** | Nilai konversi berversi: NCV, densitas, EF per gas, faktor grid, harga, GWP |
| **Scope 1** | Emisi langsung dari sumber yang dimiliki/dikendalikan (solar alat, genset, bensin, LPG, ANFO, refrigeran, SF₆) |
| **Scope 2** | Emisi tidak langsung dari listrik/uap yang dibeli (PLN, IUPTLS pihak lain) — dihitung location-based & market-based |
| **Scope 3** | Emisi rantai nilai lain (kategori 1–15 GHG Protocol) |
| **TOE** | Ton of Oil Equivalent; 1 TOE = 41,868 GJ [A-02] |
| **SEU** | Significant Energy Use — pemakai energi signifikan (ISO 50001) |
| **EnPI / EnB** | Energy Performance Indicator / Energy Baseline (ISO 50006) |
| **Periode** | Bulan kalender per perusahaan; unit kunci data |
| **Snapshot** | Salinan beku data & faktor yang dipakai sebuah laporan terbit |

---

## 3. Navigasi & rute

Rute lama dipertahankan (tautan & tangkapan layar `docs/jual/energi` tetap berlaku). Rute baru di bawah prefix yang sama `energi.`:

| Kelompok menu | Rute (GET kecuali disebut) | Nama rute | Controller@metode |
|---|---|---|---|
| **Operasional (ada)** | `/energi`, `/energi/input`, `/konsumsi`, `/bahan-bakar`, `/listrik`, `/alat`, `/alat/{unit}`, `/kpi`, `/baseline`, `/penghematan`, `/karbon`, `/kalkulator`, `/laporan`, `/data-induk` | (tetap) | `EnergyController` |
| Input baru | POST `/energi/input/lain` | `energi.input.other` | `EnergyController@simpanLain` |
| | POST `/energi/input/impor` | `energi.input.impor` | `EnergyController@impor` |
| **Karbon (GRK)** | `/energi/grk` | `energi.grk.index` | `InventarisController@index` |
| | `/energi/grk/scope/{scope}` | `energi.grk.scope` | `@scope` |
| | `/energi/grk/batas` (+POST) | `energi.grk.batas` | `@batas` / `@simpanBatas` |
| | `/energi/grk/tahun-dasar` (+POST) | `energi.grk.dasar` | `@tahunDasar` |
| | `/energi/grk/scope3` (+POST/PUT/DELETE) | `energi.grk.s3` | `@scope3` … |
| | `/energi/grk/mitigasi` (+POST/PUT) | `energi.grk.mitigasi` | `@mitigasi` … |
| **Faktor** | `/energi/faktor` (+POST/PUT), `/energi/faktor/{faktor}/setujui` POST | `energi.faktor.*` | `FaktorController` |
| **Manajemen Energi (PP 33)** | `/energi/manajemen` | `energi.me.index` | `ManajemenEnergiController@index` |
| | `/energi/manajemen/personel` (+POST/PUT) | `energi.me.personel` | `@personel` |
| | `/energi/manajemen/program` (+POST/PUT) | `energi.me.program` | `@program` |
| | `/energi/manajemen/audit` (+POST/PUT), `/audit/{audit}` | `energi.me.audit` | `@audit`, `@auditShow` |
| | `/energi/manajemen/rekomendasi/{rek}` PUT | `energi.me.rek` | `@ubahRekomendasi` |
| | `/energi/enpi` (+POST) | `energi.enpi` | `EnpiController` |
| | `/energi/seu` | `energi.seu` | `EnpiController@seu` |
| **Periode & verifikasi** | `/energi/periode` | `energi.periode.index` | `PeriodeController@index` |
| | POST `/energi/periode/verifikasi` (massal) | `energi.periode.verifikasi` | `@verifikasi` |
| | POST `/energi/periode/{periode}/kunci` | `energi.periode.kunci` | `@kunci` |
| | POST `/energi/periode/{periode}/koreksi` | `energi.periode.koreksi` | `@ajukanKoreksi` |
| **Pelaporan** | `/energi/pelaporan` | `energi.lapor.index` | `LaporanController@index` |
| | POST `/energi/pelaporan/{templat}/susun` | `energi.lapor.susun` | `@susun` |
| | `/energi/pelaporan/terbit/{laporan}` | `energi.lapor.show` | `@show` |
| | POST `/energi/pelaporan/terbit/{laporan}/ajukan`, `/setujui`, `/terbitkan` | `energi.lapor.*` | `@ajukan`… |
| | `/energi/pelaporan/terbit/{laporan}/unduh/{format}` | `energi.lapor.unduh` | `@unduh` |
| | `/energi/kalender` | `energi.kalender` | `LaporanController@kalender` |
| **BOD** | `/energi/bod` | `energi.bod.index` | `BodController@index` |
| | `/energi/bod/drill` (JSON, Inertia partial) | `energi.bod.drill` | `@drill` |
| | POST `/energi/bod/instruksi` | `energi.bod.instruksi` | `@instruksi` |
| | POST `/energi/bod/simulasi` (JSON) | `energi.bod.simulasi` | `@simulasi` |
| | POST `/energi/bod/board-pack` | `energi.bod.pack` | `@boardPack` |
| | `/energi/bod/layar` | `energi.bod.layar` | `@layar` (mode TV) |
| | POST `/energi/bod/target/{tahun}/setujui` | `energi.bod.target` | `@setujuiTarget` |
| **API** | lihat §11 | | |

Menu bilah samping (`App\Support\Menu`) ditambah grup: *Karbon & GRK*, *Manajemen Energi*, *Pelaporan*, *Kontrol Direksi*. Item "Kontrol Direksi" hanya tampil untuk peran BOD/admin.

---

## 4. Peran & matriks hak akses

### 4.1 Peran baru
Ditambahkan kolom `energi_role` pada `users` (enum string, nullable) — pola sama dengan `ohse_role`, `lms_role`, `audit_role`:
`operator`, `verifikator`, `manajer_energi`, `petugas_grk`, `keuangan`, `bod`, `auditor_tamu`.
KTT memakai `lms_role = 'ktt'` yang sudah ada. Admin memakai `is_admin`.

Gate baru (di `AppServiceProvider`):
```php
Gate::define('energi.input',     fn (User $u) => $u->isAdmin() || in_array($u->energi_role, ['operator','verifikator','manajer_energi','petugas_grk']));
Gate::define('energi.verifikasi',fn (User $u) => $u->isAdmin() || in_array($u->energi_role, ['verifikator','manajer_energi','petugas_grk']));
Gate::define('energi.kelola',    fn (User $u) => $u->isAdmin() || in_array($u->energi_role, ['manajer_energi','petugas_grk']));
Gate::define('energi.faktor',    fn (User $u) => $u->isAdmin() || $u->energi_role === 'petugas_grk');
Gate::define('energi.setujui',   fn (User $u) => $u->isAdmin() || $u->isKtt() || $u->energi_role === 'manajer_energi');
Gate::define('energi.bod',       fn (User $u) => $u->isAdmin() || in_array($u->energi_role, ['bod','keuangan']) || $u->isKtt());
```

### 4.2 Matriks (✔ penuh · L lihat · — tidak)

| Fitur | Operator | Verifikator | Manajer Energi | Petugas GRK | KTT | Keuangan | BOD | Auditor tamu | Admin |
|---|---|---|---|---|---|---|---|---|---|
| Input data aktivitas | ✔ (site sendiri) | ✔ | ✔ | ✔ | L | — | — | — | ✔ |
| Verifikasi data harian | — | ✔ | ✔ | ✔ | L | — | — | L | ✔ |
| Koreksi pasca-kunci (ajukan) | — | ✔ | ✔ | ✔ | — | — | — | — | ✔ |
| Setujui koreksi / kunci periode | — | — | ✔ | ✔ | ✔ | — | — | — | ✔ |
| Registri faktor (ubah/setujui) | — | — | L | ✔ | L | L (harga ✔) | — | L | ✔ |
| Baseline/target (usul) | — | — | ✔ | ✔ | L | L | L | L | ✔ |
| Baseline/target (setujui tahunan) | — | — | — | — | — | — | ✔ | — | ✔ |
| Program, audit, rekomendasi | — | L | ✔ | L | L | L | L | L | ✔ |
| Inventarisasi GRK | — | L | L | ✔ | L | L | L | L | ✔ |
| Susun laporan | — | — | ✔ | ✔ | — | ✔ (keberlanjutan) | — | — | ✔ |
| Setujui & terbitkan laporan | — | — | ✔ (energi) | ✔ (GRK) | ✔ (paraf akhir) | — | — | — | ✔ |
| Dasbor BOD | — | — | L | L | ✔ | ✔ | ✔ | — | ✔ |
| Instruksi BOD | — | — | — | — | ✔ | — | ✔ | — | ✔ |

Akses auditor tamu: tautan bertoken (`energi_akses_tamu`) dengan masa berlaku, lingkup perusahaan & periode, baca-saja, setiap akses tercatat di `AksesAudit` (model sudah ada).

---

## 5. Model data

Semua tabel baru: `id`, `company_id` (FK `companies`, `nullOnDelete`, ber-index), `timestamps`; model memakai `#[ScopedBy(MilikPerusahaan::class)]` + `BerpemilikPerusahaan`. Kecuali disebut, tabel faktor boleh `company_id NULL` = faktor bersama (nasional/IPCC) yang terlihat semua perusahaan — sesuai arti NULL pada `MilikPerusahaan`.

### 5.1 Site & sumber

**`energi_sites`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| kode | string(30) | unik per perusahaan |
| nama | string(150) | |
| jenis | string | `pit`, `port`, `hauling`, `camp`, `plant`, `kantor`, `lain` |
| provinsi | string | untuk pelaporan daerah (Perpres 110 Ps. 14) |
| sistem_grid | string nullable | kode sistem ketenagalistrikan untuk faktor grid (mis. `JAMALI`, `SUMBAGSEL`, `KALSELTENG`, `ISOLATED`) |
| zona_waktu | string | `Asia/Jakarta` / `Asia/Makassar` / `Asia/Jayapura` |
| aktif | bool | |

**`energi_sumber`** (generalisasi `energy_equipment` + area listrik)
| Kolom | Tipe | Keterangan |
|---|---|---|
| site_id | FK | |
| kode, nama | string | |
| jenis_sumber | string | `alat_berat`, `kendaraan_ringan`, `genset`, `area_listrik`, `pembangkit_captive`, `plts`, `tangki`, `peledakan`, `pendingin`, `switchgear`, `boiler`, `lain` |
| kategori_ipcc | string | `stasioner`, `bergerak_offroad`, `bergerak_jalan`, `proses`, `fugitive`, `listrik_beli` — menentukan faktor CH₄/N₂O & scope |
| scope | tinyint | 1 atau 2 (bawaan dari jenis; dapat ditimpa) |
| energy_equipment_id | FK nullable | jembatan ke unit lama |
| area_kode | string nullable | jembatan ke `Energi::AREA` |
| kapasitas, satuan_kapasitas | decimal, string | kW, HP, ton |
| seu | bool | ditandai hasil tinjauan energi |
| ptbae | bool | pembangkit terkena PTBAE-PU [A-06] |
| aktif | bool | |

### 5.2 Registri faktor (menutup G1)

**`energi_faktor`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| kode_energi | string | `solar`, `biodiesel_fame`, `bensin`, `lpg`, `gas_alam`, `listrik_grid`, `listrik_captive`, `anfo`, `emulsi`, `r134a`, `r410a`, `r22`, `sf6`, `pelumas`, … |
| parameter | string | `ncv`, `densitas`, `ef_co2`, `ef_ch4`, `ef_n2o`, `ef_grid_co2e`, `ef_grid_co2`, `harga`, `toe`, `sbm`, `fraksi_fosil` |
| kategori_ipcc | string nullable | membedakan EF CH₄/N₂O stasioner vs bergerak |
| wilayah | string nullable | kode sistem grid / provinsi |
| pemasok | string nullable | faktor spesifik pemasok (Tier 2) |
| nilai | decimal(20,10) | |
| satuan | string | mis. `MJ/kg`, `kg/L`, `kgCO2/TJ`, `tCO2e/MWh`, `Rp/L` |
| berlaku_mulai, berlaku_sampai | date, date nullable | interval tidak boleh tumpang tindih untuk kunci (kode_energi, parameter, kategori_ipcc, wilayah, pemasok, company_id) |
| tier | tinyint | 1/2/3 |
| sumber_dokumen | string | mis. "Kepmen ESDM 163.K/HK.02/MEM.S/2021 Lampiran" |
| sumber_rujukan | string nullable | tabel/halaman |
| sumber_url | string nullable | |
| lampiran_path | string nullable | salinan dokumen sumber |
| ketidakpastian_persen | decimal nullable | untuk analisis ketidakpastian |
| status | string | `draf`, `aktif`, `ditarik` |
| diajukan_oleh, disetujui_oleh | FK users | |
| disetujui_pada | datetime | |
| catatan | text | |

**`energi_gwp`**: `set` (`AR4`, `AR5`, `AR6`), `gas`, `nilai`, `sumber`. Isi awal AR5: CO₂ 1, CH₄ 28, N₂O 265; AR6: CO₂ 1, CH₄ (fosil) 29,8, N₂O 273; plus HFC/SF₆ yang dipakai (lampiran §17) [R-20][A-04].

**`energi_campuran_bbn`**: `company_id`, `pemasok` nullable, `berlaku_mulai`, `berlaku_sampai`, `persen_fame` (40, 50, …), `dasar` (mis. "Kepmen ESDM 257.K/EK.01/MEM.E/2026"), `bukti_path`. Bawaan nasional: 40% s.d. 30 Jun 2026; 50% mulai 1 Jul 2026, dengan masa transisi s.d. 30 Sep 2026 di mana operator boleh memilih per pengiriman [R-17][R-18].

### 5.3 Data aktivitas (perluasan)

Tabel lama **dipertahankan**; kolom baru ditambahkan (nullable agar data lama tetap sah):

| Tabel | Kolom tambahan |
|---|---|
| `energy_fuel_logs` | `site_id`, `sumber_id`, `status` (default `draf`), `sumber_data` (`flowmeter`/`nota`/`estimasi`/`fms`), `bukti_path`, `diinput_oleh`, `diverifikasi_oleh`, `diverifikasi_pada`, `persen_fame` nullable (menimpa campuran bawaan), `periode_id` |
| `energy_power_logs` | `site_id`, `sumber_id`, `status`, `sumber_data`, `bukti_path`, `diinput_oleh`, `diverifikasi_oleh`, `diverifikasi_pada`, `pemasok_listrik` (`pln`/`iuptls`/`captive`/`plts`), `periode_id` |
| `energy_other_logs` | `site_id`, `sumber_id`, `kode_energi` (ganti makna `jenis`), `status`, `sumber_data`, `bukti_path`, `diinput_oleh`, `diverifikasi_oleh`, `diverifikasi_pada`, `periode_id` |
| `energy_production` | `site_id`, `status`, `stripping_ratio` decimal nullable, `jarak_angkut_km` decimal nullable, `komoditas` |
| `energy_fuel_recon` | `site_id`, `tangki_id` (sumber jenis `tangki`), `status` |
| `energy_baselines` | `site_id` nullable, `jenis` (`gj_ton`, `l_bcm`, `tco2e_ton`, `tco2e_absolut`), `model_enb_id` nullable, `status` (`usulan`/`disetujui_bod`), `disetujui_oleh`, `disetujui_pada` |
| `energy_opportunities` | `site_id`, `capex_rp`, `opex_hemat_rp`, `metode_mv` (`A`/`B`/`C`/`D`), `hemat_terverifikasi_liter`, `hemat_terverifikasi_kwh`, `hemat_terverifikasi_tco2e`, `tanggal_mulai_hemat`, `mitigasi_id` nullable, `rekomendasi_audit_id` nullable |

**Indeks unik** yang ada (`equipment_id,tanggal`; `tanggal,area,sumber`) diubah menyertakan `company_id`/`site_id` mengikuti pola migrasi `2026_08_16_000008`/`000011`.

**Tabel baru `energi_aktivitas_scope3`**
| Kolom | Keterangan |
|---|---|
| tahun, bulan nullable | |
| kategori | 1–15 (GHG Protocol) |
| subkategori | teks bebas terstruktur (mis. "angkutan tongkang pihak ketiga") |
| metode | `spend`, `average_data`, `supplier_specific`, `distance` |
| kuantitas, satuan | mis. ton-km, Rp, kWh, ton batubara terjual |
| faktor_id | FK `energi_faktor` (EF Scope 3) |
| mitra | nama pemasok/kontraktor |
| kualitas_data | 1–5 |
| status | alur seperti data harian |

**Tabel `energi_ingest_token`**: `company_id`, `nama`, `hash_token`, `lingkup` (json: jenis data, site), `kedaluwarsa`, `terakhir_dipakai`.

### 5.4 Periode, alur, & jejak

**`energi_periode`**: `company_id`, `tahun`, `bulan`, `status` (`terbuka`, `verifikasi`, `terkunci`), `dikunci_oleh`, `dikunci_pada`, `kelengkapan_persen`, `catatan`. Unik (`company_id`, `tahun`, `bulan`).

**`energi_koreksi`**: `periode_id`, `subjek_type`, `subjek_id` (morph ke baris log), `nilai_lama` json, `nilai_baru` json, `alasan`, `status` (`diajukan`/`disetujui`/`ditolak`), `diajukan_oleh`, `diputus_oleh`, `diputus_pada`. Persetujuan memicu revisi laporan yang memakai periode itu (status laporan → `perlu_revisi`).

**`energi_jejak`** (audit trail granular; `ActivityLog` tetap untuk peristiwa ringkas): `subjek_type`, `subjek_id`, `aksi` (`buat`/`ubah`/`hapus`/`verifikasi`/`kunci`), `perubahan` json (field → [lama, baru]), `user_id`, `ip`, `user_agent`, `created_at`. Diisi oleh observer model `Concerns\Dijejak` (trait baru).

### 5.5 Inventarisasi GRK

**`energi_batas_organisasi`**: `company_id`, `tahun`, `pendekatan` (`kendali_operasional` default, `kendali_finansial`, `ekuitas`), `persen_ekuitas` nullable, `uraian_batas_operasional` text, `pengecualian` json (sumber dikecualikan + alasan + estimasi % emisi), `disetujui_oleh`.

**`energi_tahun_dasar`**: `company_id`, `tahun_dasar`, `total_s1`, `total_s2_lb`, `total_s2_mb`, `total_s3` (snapshot), `ambang_rekalkulasi_persen` (default 5), `riwayat_rekalkulasi` json.

**`energi_mitigasi`** (registri aksi mitigasi → SRN PPI): `judul`, `jenis` (`efisiensi`, `ebt`, `elektrifikasi`, `bbn`, `lain`), `site_id`, `mulai`, `selesai`, `metodologi`, `baseline_tco2e_tahun`, `target_penurunan_tco2e_tahun`, `capaian` json per tahun, `status_srn` (`belum`, `didaftarkan`, `terverifikasi`), `nomor_srn` nullable, `dokumen_path`.

### 5.6 Manajemen energi (PP 33/2023, Permen ESDM 8/2025, ISO 50001)

**`energi_personel`**: `user_id` nullable, `nama`, `peran` (`manajer_energi`, `auditor_energi`), `internal` bool, `lembaga` (untuk eksternal), `nomor_sertifikat`, `lembaga_sertifikasi`, `berlaku_sampai`, `sk_penunjukan_path`, `aktif`. Validasi: harus ada tepat ≥1 manajer energi aktif bersertifikat berlaku untuk perusahaan berstatus wajib.

**`energi_program`** (program konservasi tahunan): `tahun`, `judul`, `sasaran` text, `target_hemat_gj`, `target_hemat_tco2e`, `anggaran_rp`, `pic`, `status`, relasi ke `energy_opportunities` (pivot `energi_program_peluang`).

**`energi_audit`**: `jenis` (`internal`/`eksternal`), `ruang_lingkup` text, `sumber_ids` json (SEU yang diaudit — minimal satu SEU), `auditor_ids` json (FK personel), `mulai`, `selesai`, `standar` (mis. "SNI ISO 50002"), `laporan_path`, `status` (`direncanakan`/`berjalan`/`selesai`).

**`energi_rekomendasi`**: `audit_id`, `uraian`, `potensi_hemat_gj`, `potensi_hemat_rp`, `biaya_rp`, `prioritas`, `tenggat` (default = `audit.selesai + 3 tahun`, Permen ESDM 8/2025 Ps. 16), `status` (`terbuka`/`ditindaklanjuti`/`selesai`/`tidak_layak`), `alasan_tidak_layak`, `peluang_id` nullable. Setiap rekomendasi juga membuat `TindakLanjut` (morph `sumber`) agar masuk mekanisme pengingat `KirimPengingatTindakLanjut` yang sudah ada.

**`energi_enb_model`** (EnB/EnPI ISO 50006): `site_id` nullable, `sumber_id` nullable, `nama`, `variabel_terikat` (`gj`, `liter`, `kwh`), `variabel_bebas` json (mis. `["ton","bcm","jarak_angkut_km"]`), `periode_dasar_mulai`, `periode_dasar_sampai`, `koefisien` json, `intercept`, `r2`, `cv_rmse`, `p_values` json, `status`, `disetujui_oleh`.

**`energi_tinjauan`** (tinjauan energi & tinjauan manajemen): `tahun`, `jenis` (`tinjauan_energi`/`tinjauan_manajemen`), `masukan` json, `keluaran` json, `notulen_path`, `tanggal`.

### 5.7 Pelaporan

**`energi_laporan`**: `templat` (kode, §10), `tahun`, `periode_mulai`, `periode_sampai`, `versi` (int, naik setiap revisi), `status` (`draf`, `diajukan`, `disetujui`, `terbit`, `perlu_revisi`, `diganti`), `nomor_dokumen` (dari `KopDokumen`/`Nomor`), `snapshot` json (lihat §7.4), `hash_snapshot` (SHA-256), `berkas` json (path PDF/XLSX/CSV), `disampaikan_pada` nullable, `bukti_penyampaian_path` nullable (tanda terima POME dsb.), `pengganti_id` nullable. Paraf memakai `PersetujuanParaf` (morph) dengan tahap: penyusun → pemeriksa → penyetuju (Manajer Energi/Petugas GRK) → pengesah (KTT/Direksi).

**`energi_kewajiban`** (kalender kepatuhan): `kode` (mis. `ESDM_LAPORAN_ME`, `GRK_INVENTARIS`, `AUDIT_ENERGI`, `PROPER`, `PSPK2`, `SERT_MANAJER`), `judul`, `dasar_hukum`, `tenggat`, `berulang` (`tahunan`/`3tahunan`/`sekali`), `templat` nullable, `status` (`belum`/`proses`/`selesai`/`terlambat`/`tidak_berlaku`), `laporan_id` nullable.

### 5.8 Agregat & BOD

**`energi_agregat_harian`** (materialisasi): `company_id`, `site_id`, `tanggal`, `sumber_id` nullable, `kode_energi`, `scope`, `kuantitas`, `satuan`, `gj`, `toe`, `co2_fosil_t`, `co2_biogenik_t`, `ch4_t`, `n2o_t`, `co2e_t`, `rupiah`, `ton_produksi`, `bcm_produksi`, `persen_terverifikasi`, `persen_estimasi`, `faktor_versi` json. Index (`company_id`,`tanggal`), (`site_id`,`tanggal`).

**`energi_bod_instruksi`**: `company_id`, `site_id` nullable, `konteks` json (KPI, filter, nilai saat instruksi), `isi`, `prioritas`, `pic_user_id`, `tenggat`, `tindak_lanjut_id` (FK `TindakLanjut`), `status`, `dibuat_oleh`.

**`energi_harga_karbon`**: `berlaku_mulai`, `skenario` (`dasar`, `rendah`, `tinggi`), `rp_per_tco2e`, `sumber` (mis. "UU 7/2021 HPP Rp30/kgCO₂e = Rp30.000/tCO₂e"), `catatan`.

**`energi_snapshot_bod`** (board pack): `judul`, `filter` json, `data` json, `berkas_path`, `dibuat_oleh`, `dikunci` bool.

### 5.9 Diagram relasi (ringkas)

```
companies ─┬─< energi_sites ─< energi_sumber ─< (energy_fuel_logs | energy_power_logs | energy_other_logs)
           │                      └─ energy_equipment (jembatan)
           ├─< energy_production, energy_fuel_recon, energi_aktivitas_scope3
           ├─< energi_periode ─< energi_koreksi
           ├─< energi_faktor (company_id NULL = bersama), energi_campuran_bbn, energi_harga_karbon
           ├─< energi_personel, energi_program ─<> energy_opportunities ─ energi_rekomendasi >─ energi_audit
           ├─< energi_enb_model, energy_baselines, energi_tinjauan
           ├─< energi_batas_organisasi, energi_tahun_dasar, energi_mitigasi
           ├─< energi_laporan ─< persetujuan_paraf (morph)
           ├─< energi_kewajiban
           └─< energi_agregat_harian, energi_bod_instruksi ─ tindak_lanjut (morph)
```

---

## 6. Mesin perhitungan

Semua rumus di `App\Support\Energi\Kalkulator`, dipanggil lewat fasad `App\Support\Energi`. Semua fungsi menerima **tanggal aktivitas** dan **konteks** (company, site/wilayah, pemasok) untuk memilih faktor via `RegistriFaktor::ambil($kodeEnergi, $parameter, $tanggal, $konteks)`.

### 6.1 Pemilihan faktor (urutan prioritas)
1. Faktor perusahaan + pemasok spesifik (Tier 2/3), status `aktif`, `berlaku_mulai ≤ tanggal ≤ berlaku_sampai`.
2. Faktor perusahaan tanpa pemasok.
3. Faktor bersama (company_id NULL) dengan wilayah cocok.
4. Faktor bersama tanpa wilayah (default IPCC).
5. Tidak ada → hasil `null` + penanda **"faktor tidak tersedia"**; baris tidak boleh dikunci/dilaporkan sampai faktor tersedia. Tidak ada fallback diam-diam ke angka kode.

Cache: per-request memo + cache aplikasi berkunci (`kode|parameter|tanggal|konteks`) yang dibersihkan saat faktor diubah.

### 6.2 Energi

```
massa_kg        = volume_L × densitas_kg/L
energi_GJ       = massa_kg × NCV_MJ/kg / 1000                         (BBM cair/gas berbasis massa)
                = volume_m3 × NCV_MJ/m3 / 1000                        (gas berbasis volume)
                = kWh × 0,0036                                        (listrik, definisi)
TOE             = energi_GJ / 41,868                                  [A-02]
SBM             = energi_GJ / faktor_GJ_per_SBM (registri)            [A-02]
```
Kompatibilitas: dengan densitas solar 0,84 kg/L dan NCV 43,0 MJ/kg → 36,12 MJ/L (nilai lama kode 35,8 MJ/L; selisih ±0,9% dicatat di catatan migrasi §14).

**Listrik untuk TOE (PP 33):** listrik yang dibeli dihitung dalam energi final (3,6 MJ/kWh) [A-02]; solar genset dihitung sebagai BBM, dan kWh genset **tidak** dijumlahkan lagi (menghindari hitung ganda — perilaku lama di `ringkasan()` sudah benar karena kWh genset tetap terpisah dari liter; FSD menegaskan: total energi = BBM + listrik beli + EBT on-site; kWh genset hanya untuk EnPI genset).

### 6.3 Emisi Scope 1 — pembakaran

Untuk setiap baris BBM dengan fraksi FAME `f` (0–1):
```
E_fosil_GJ     = energi_GJ_solar_murni × (1 − f_energi)
E_bio_GJ       = energi_GJ × f_energi
  f_energi     = (f_vol × ρ_fame × NCV_fame) / (f_vol × ρ_fame × NCV_fame + (1−f_vol) × ρ_solar × NCV_solar)

CO2_fosil_t    = E_fosil_GJ × EF_CO2_solar (kg/TJ) / 1e6
CO2_biogenik_t = E_bio_GJ  × EF_CO2_fame  (kg/TJ) / 1e6      → dilaporkan TERPISAH, tidak masuk total Scope 1
CH4_t          = energi_GJ × EF_CH4(kategori_ipcc) / 1e6
N2O_t          = energi_GJ × EF_N2O(kategori_ipcc) / 1e6
CO2e_t         = CO2_fosil_t + CH4_t × GWP_CH4 + N2O_t × GWP_N2O
```
`kategori_ipcc` sumber menentukan EF CH₄/N₂O (stasioner: genset, boiler; bergerak_offroad: alat berat; bergerak_jalan: kendaraan ringan/truk jalan umum).

### 6.4 Emisi Scope 1 — proses & fugitive
```
Bahan peledak:  CO2_t = massa_ANFO_t × EF_anfo (tCO2/t)            [A-09]
Refrigeran:     CO2e_t = isi_ulang_kg × GWP_gas / 1000            (metode mass-balance sederhana)
SF6:            CO2e_t = kebocoran_kg × GWP_SF6 / 1000
```

### 6.5 Emisi Scope 2
```
Location-based:  CO2e_t = MWh_beli × EF_grid(sistem_grid site, tahun) (tCO2e/MWh)
Market-based:    CO2e_t = Σ (MWh_kontrak_i × EF_kontrak_i)
                         + MWh_REC × 0
                         + MWh_sisa × EF_residual (bila tidak tersedia → EF_grid, ditandai)
```
PLTS on-site: tidak ada emisi; MWh tercatat untuk % EBT. Listrik captive milik sendiri = Scope 1 (BBM-nya), bukan Scope 2.

### 6.6 Scope 3
```
CO2e_t = kuantitas × EF_scope3 (sesuai metode)
```
Kategori 11 (pembakaran produk batubara terjual) [A-10]: `ton_terjual × NCV_batubara (per kualitas) × EF_CO2_batubara` — disajikan terpisah dan tidak masuk target operasional.

### 6.7 Indikator kinerja
```
Intensitas energi      = GJ_total / ton_produksi          (sudah ada: Energi::intensitas)
Rasio BBM              = L / ton, L / BCM                 (Energi::rasio)
Intensitas emisi       = tCO2e (S1+S2_LB) / ton
% EBT                  = MWh_EBT / (MWh_EBT + MWh_beli + MWh_genset)
Selisih rekonsiliasi   = (disalurkan − tercatat) / disalurkan × 100   (Energi::selisihRekonsiliasi)
Faktor beban, efisiensi genset — tetap
Penurunan vs baseline  = (EnB_prediksi − aktual) / EnB_prediksi × 100
  EnB_prediksi         = intercept + Σ koef_i × variabel_i(periode)   (model ISO 50006)
```

### 6.8 Status ambang wajib manajemen energi (`Ambang`)
```
TOE_tahun_lalu   = Σ TOE seluruh site perusahaan tahun t-1 (terverifikasi)
TOE_proyeksi     = TOE_ytd / hari_berjalan × hari_tahun
ambang           = konfigurasi perusahaan (bawaan: industri 4.000; pilihan: penyedia energi 6.000) [A-01]
status           = WAJIB         bila TOE_tahun_lalu ≥ ambang
                   PERINGATAN    bila TOE_proyeksi ≥ 0,9 × ambang
                   TIDAK_WAJIB   lainnya (sukarela tetap boleh, PP 33)
```
Contoh orientasi: 4.000 TOE ≈ 167.472 GJ ≈ 4,6 juta liter solar/tahun.

### 6.9 Ketidakpastian (IPCC Approach 1)
```
U_baris  = sqrt(U_aktivitas² + U_faktor²)
U_total  = sqrt(Σ (U_i × E_i)²) / Σ E_i
```
`U_aktivitas` bawaan: flowmeter terkalibrasi 2%, nota 5%, estimasi 20% (dapat dikonfigurasi). `U_faktor` dari `energi_faktor.ketidakpastian_persen`.

### 6.10 Deteksi anomali (`Anomali`)
- Per sumber: L/HM harian dibandingkan median 30 hari; anomali bila |z robust| > 3 (MAD) atau L/HM > kapasitas tangki/jam.
- HM mundur atau lompatan > 24 jam/hari.
- Rekonsiliasi: |selisih| > ambang (bawaan 2%).
- Kelengkapan: hari operasi tanpa data.
Hasil disimpan sebagai penanda pada baris (`anomali` json) dan muncul di Pusat Peringatan BOD.

### 6.11 Konvensi pembulatan
Perhitungan dalam presisi penuh (`decimal` → `float` PHP), pembulatan **hanya** saat tampil: tCO₂e 2 desimal, GJ 1 desimal, TOE 1 desimal, Rp tanpa desimal. Total dihitung dari nilai belum dibulatkan.

---

## 7. Alur kerja data & kunci periode

### 7.1 Status baris data aktivitas

```
draf ──(ajukan)──> diajukan ──(verifikasi)──> terverifikasi ──(kunci periode)──> terkunci
  ^                   │  (tolak + alasan)                          │
  └───────────────────┘                                            └──(koreksi disetujui)──> terkunci (revisi)
```
- Operator: buat/ubah `draf` & `diajukan` miliknya.
- Verifikator: verifikasi massal per tanggal/site; wajib alasan saat menolak.
- Data `terverifikasi` hanya bisa diubah verifikator dan tercatat di `energi_jejak`.
- Data `terkunci`: tidak bisa diubah; perubahan lewat `energi_koreksi`.
- Data lama (sebelum migrasi) diberi status `terverifikasi` (lihat §14).

### 7.2 Kunci periode bulanan
Syarat tombol "Kunci" aktif:
1. Kelengkapan ≥ ambang (bawaan 95% hari operasi × sumber aktif).
2. Tidak ada baris `draf`/`diajukan`.
3. Tidak ada baris dengan "faktor tidak tersedia".
4. Rekonsiliasi BBM bulan itu terisi.
Yang mengunci: Manajer Energi/Petugas GRK/KTT. Mengunci menjalankan ulang `HitungAgregatHarian` untuk periode tsb.

### 7.3 Alur laporan
`draf → diajukan → disetujui → terbit` dengan paraf bertahap (`PersetujuanParaf`). Laporan hanya bisa disusun dari periode **terkunci**. Saat `terbit`: snapshot dibekukan, hash dihitung, PDF/XLSX dibuat, kewajiban terkait di kalender ditandai `selesai` setelah bukti penyampaian diunggah. Jika koreksi disetujui pada periode yang dipakai → laporan `perlu_revisi`; revisi membuat versi baru yang menautkan `pengganti_id`.

### 7.4 Isi snapshot
```json
{
  "dibuat_pada": "...", "periode": ["2026-01-01","2026-12-31"], "company": {...},
  "baris_agregat": [ {site, sumber, kode_energi, scope, kuantitas, satuan, gj, toe, co2_fosil_t, co2_biogenik_t, ch4_t, n2o_t, co2e_t, rupiah} ],
  "faktor": [ {id, kode_energi, parameter, nilai, satuan, berlaku_mulai, sumber_dokumen, tier} ],
  "gwp_set": "AR5", "batas_organisasi": {...}, "ketidakpastian": {...},
  "kelengkapan": {...}, "penyusun": [...], "versi_aplikasi": "git sha"
}
```

---

## 8. Spesifikasi layar

Pola umum semua layar: header judul + `PemilihPeriode` (dari/sampai, preset MTD/QTD/YTD/12 bulan) + pemilih site; kartu KPI di atas; tabel/grafik; setiap angka turunan dapat diklik membuka `PanelRumus` (rumus, faktor & versinya, jumlah baris sumber). Seluruh mode `Halaman.vue` yang masih "tampilan darurat" diganti komponen per halaman (F9.1).

### 8.1 Input Lapangan (`/energi/input`) — diperluas
Tab: **Produksi · Solar per unit · Listrik · Gas & energi lain · Peledakan · Refrigeran/SF₆ · Rekonsiliasi · Impor**.

Form "Gas & energi lain" (baru):
| Field | Tipe | Validasi |
|---|---|---|
| tanggal | date | wajib, ≤ hari ini, periode tidak terkunci |
| site_id | select | wajib, site milik perusahaan |
| sumber_id | select | opsional, sesuai site |
| kode_energi | select | wajib, dari daftar kode energi aktif |
| jumlah | number | wajib, ≥0, ≤1e9 |
| satuan | select | otomatis dari kode energi (m³, kg, L) |
| sumber_data | select | wajib |
| bukti | file | opsional, pdf/jpg/png ≤5 MB |
| keterangan | text | ≤500 |

Form Solar per unit: field lama + `persen_fame` (bawaan dari campuran berlaku, dapat ditimpa dengan bukti), `sumber_data`, `bukti`. Peringatan inline bila L/HM di luar rentang wajar kategori.

Impor: unduh templat XLSX per jenis; unggah → pratinjau baris (valid/galat/peringatan) → simpan sebagai `draf` atau `diajukan`. Maksimal 10.000 baris per berkas; diproses di antrian bila >1.000 baris.

### 8.2 Verifikasi (`/energi/periode`)
- Kalender kelengkapan per site (sel hijau = lengkap & terverifikasi, kuning = belum diverifikasi, merah = kosong, ungu = anomali).
- Daftar baris `diajukan` dengan centang massal, filter anomali.
- Panel kunci periode dengan daftar syarat (§7.2) bertanda ✔/✖.
- Daftar pengajuan koreksi.

### 8.3 Registri Faktor (`/energi/faktor`)
- Tabel berfilter (kode energi, parameter, wilayah, tahun, status).
- Linimasa per faktor (versi berurutan) — mencegah tumpang tindih interval.
- Form tambah/ubah: semua kolom §5.2; `sumber_dokumen` wajib; lampiran dianjurkan.
- Tombol "Setujui" (Petugas GRK lain/admin; penyetuju ≠ pengaju).
- Tombol "Uji dampak": hitung ulang tCO₂e 12 bulan terakhir dengan faktor draf vs aktif dan tampilkan selisih — sebelum disetujui.
- Impor paket faktor (JSON/XLSX) — dipakai untuk paket awal §17.

### 8.4 Karbon & GRK (`/energi/grk`, menggantikan isi `/energi/karbon`)
- KPI: total tCO₂e (S1, S2-LB, S2-MB, S3), CO₂ biogenik (terpisah), intensitas tCO₂e/ton, perubahan vs tahun dasar.
- Grafik batang bertumpuk per bulan per scope; donat per sumber/gas.
- Tabel per scope → kategori → sumber → gas (CO₂, CH₄, N₂O, HFC, SF₆) dengan kolom kualitas data & ketidakpastian.
- Halaman `/grk/batas`: form pendekatan & pengecualian.
- Halaman `/grk/tahun-dasar`: tahun dasar & riwayat rekalkulasi; peringatan otomatis bila perubahan struktural/metodologi > ambang.
- Halaman `/grk/scope3`: daftar & form aktivitas Scope 3 per kategori, tabel penyaringan relevansi 15 kategori (relevan/tidak + alasan).
- Halaman `/grk/mitigasi`: registri aksi mitigasi & status SRN PPI.

### 8.5 Manajemen Energi (`/energi/manajemen`)
- **Status kewajiban** (kartu besar): TOE tahun lalu, proyeksi tahun berjalan, ambang, status (WAJIB/PERINGATAN/TIDAK WAJIB), dasar hukum.
- **Checklist kewajiban PP 33 / Permen ESDM 8/2025**: manajer energi bersertifikat ✔/✖ · program konservasi tahun ini ✔/✖ · audit energi ≤3 tahun terakhir mencakup ≥1 SEU ✔/✖ · rekomendasi lewat tenggat (jumlah) · monev tahunan ke manajemen puncak ✔/✖ · laporan tahunan ke Menteri (tenggat 30 Juni) ✔/✖.
- Sub-halaman Personel, Program, Audit (dengan rekomendasi & tenggat), EnPI/EnB, SEU.
- **SEU** (`/energi/seu`): diagram Pareto GJ per sumber/kategori, centang SEU, variabel relevan per SEU.
- **EnPI/EnB** (`/energi/enpi`): pilih variabel terikat & bebas, periode dasar → hitung regresi (OLS) → tampilkan koefisien, R², CV(RMSE), p-value, grafik prediksi vs aktual; model diterima bila R² ≥ 0,75 dan CV(RMSE) ≤ 20% (bawaan, dapat diatur) — jika tidak, sistem menyarankan EnPI rasio sederhana.

### 8.6 Penghematan (`/energi/penghematan`) — diperluas
Kanban status (`usulan → review → disetujui → berjalan → selesai`/`ditolak`), kolom CAPEX, payback = CAPEX / hemat_Rp_tahunan, NPV opsional, metode M&V, penghematan rencana vs terverifikasi. Status `selesai` mensyaratkan isian M&V; penghematan "terwujud" di KPI hanya memakai nilai terverifikasi.

### 8.7 Halaman lain yang dirapikan
Konsumsi, Bahan Bakar (termasuk rekonsiliasi per tangki), Listrik (PLN/IUPTLS/genset/PLTS, faktor beban), Peralatan & detail unit (L/HM, L/ton, idle %, status efisiensi), KPI, Baseline (usulan & status persetujuan BOD), Kalkulator (memanggil `GET /energi/api/kalkulator` — hasil identik server, termasuk tCO₂e), Laporan (pindah ke Pusat Pelaporan; rute lama mengarah ke laporan kinerja energi bulanan), Data Induk (site, sumber, unit lama).

---

## 9. Dasbor BOD — Executive Control Tower (`/energi/bod`)

### 9.1 Tata letak

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ Filter: [Periode YTD ▾] [Grup/Perusahaan ▾] [Site ▾] [Bandingkan: Target ▾]   │
│ Mutu data: 96% terverifikasi · 3% estimasi · data s.d. 20/11/2026             │
├──────────┬──────────┬──────────┬──────────┬──────────┬──────────┬────────────┤
│ Energi   │Intensitas│ Emisi    │Intensitas│ Biaya    │  % EBT   │ Rekonsil.  │
│ GJ / TOE │ GJ/ton   │ tCO2e    │ tCO2e/t  │ energi Rp│          │ BBM %      │
│ ▲/▼ vs T │ RAG      │ S1|S2|S3 │ RAG      │ Rp/ton   │          │ RAG        │
├──────────┴──────────┴──────────┴──────────┴──────────┴──────────┴────────────┤
│ [Tren aktual vs baseline vs target + proyeksi akhir tahun]   │ [Peta panas    │
│                                                               │  kepatuhan]   │
├───────────────────────────────────────────────┬───────────────┴──────────────┤
│ [Peringkat site: intensitas & deviasi Rp]     │ [Pusat peringatan]            │
├───────────────────────────────────────────────┼──────────────────────────────┤
│ [Portofolio inisiatif: rencana vs terverif.]  │ [Eksposur biaya karbon &      │
│                                               │  simulator skenario]          │
├───────────────────────────────────────────────┴──────────────────────────────┤
│ [Instruksi BOD terbuka: PIC · tenggat · status]          [Board pack ⤓]        │
└──────────────────────────────────────────────────────────────────────────────┘
```

### 9.2 Widget

| ID | Widget | Data / query | Interaksi | Kebutuhan |
|---|---|---|---|---|
| W1 | Kartu KPI (7) | `energi_agregat_harian` dijumlah per filter; target dari `energy_baselines` status `disetujui_bod` | Klik → drill (W8) & `PanelRumus` | BOD-01, BOD-14 |
| W2 | Status RAG | Hijau: ≤ target; Kuning: ≤ target + 5%; Merah: > target + 5% (ambang dapat diatur per KPI); untuk KPI "lebih tinggi lebih baik" (% EBT) dibalik | Tooltip ambang | BOD-01 |
| W3 | Tren & proyeksi | Seri bulanan aktual; garis EnB_prediksi (model §6.7) & target; proyeksi = aktual YTD + (run-rate 3 bulan × sisa bulan), pita ±1σ | Toggle seri, zoom, ganti metrik | BOD-04 |
| W4 | Jalur dekarbonisasi | Emisi tahunan aktual vs lintasan target (input tahun target & % penurunan) + waterfall kontribusi inisiatif (`energi_mitigasi`, `energy_opportunities` terverifikasi) | Klik batang → daftar inisiatif | BOD-05 |
| W5 | Peta panas kepatuhan | `energi_kewajiban` × perusahaan; warna status; isi sel = tenggat | Klik sel → detail kewajiban/laporan | BOD-06 |
| W6 | Peringkat site/unit | Intensitas & deviasi vs acuan (`Energi::statusEfisiensi`), deviasi Rp = (aktual − acuan) × produksi × harga | Urut, klik → drill | BOD-12 |
| W7 | Pusat peringatan | Anomali (§6.10), data belum terverifikasi > 3 hari, kewajiban ≤30 hari, sertifikat manajer energi ≤60 hari kedaluwarsa, rekomendasi audit lewat tenggat | Tombol "Beri instruksi" | BOD-09 |
| W8 | Drill-down | Grup → perusahaan → site → kategori/area → sumber → baris harian (dengan bukti) | Breadcrumb, kembali | BOD-03 |
| W9 | Portofolio inisiatif | `energy_opportunities` per status: jumlah, CAPEX, hemat rencana vs terverifikasi (GJ, tCO₂e, Rp), payback | Filter status, klik → detail | BOD-10 |
| W10 | Eksposur karbon | tCO₂e (S1+S2) × `energi_harga_karbon` per skenario; tampilkan juga potensi bila pajak karbon diterapkan pada pembangkit captive (PTBAE) | Pilih skenario | BOD-07 |
| W11 | Simulator | Input slider: Δ produksi %, % FAME, bauran listrik (PLN/genset/PLTS), Δ harga solar, Δ L/BCM; dihitung server (`/bod/simulasi`) memakai faktor aktif; tidak menyimpan data | Simpan skenario sebagai catatan | BOD-08 |
| W12 | Instruksi BOD | `energi_bod_instruksi` + status `TindakLanjut` | Buat, ubah tenggat, tutup | BOD-11 |
| W13 | Board pack | Snapshot filter & semua widget → PDF (KopDokumen, nomor dokumen) + XLSX lampiran data | Satu klik, tercatat | BOD-13 |
| W14 | Persetujuan target | Usulan baseline/target tahun depan dari Manajer Energi; tombol setujui/tolak dengan paraf | — | BOD-17 |

### 9.3 Aturan & kinerja
- Semua query BOD membaca `energi_agregat_harian` (dan agregat bulanan turunan via view/cache), maksimal 2 detik p95.
- Respons dibagi dengan Inertia *partial reload* per widget (`only: ['w3']`) agar perubahan filter tidak memuat ulang seluruh halaman.
- Filter disimpan di query string (`?periode=ytd&company=3&site=7`) → tautan dapat dibagikan; hak akses tetap diperiksa server.
- Mode layar (`/energi/bod/layar`): rotasi widget tiap 30 detik, segarkan data tiap 5 menit, tanpa kontrol ubah.
- Seluler: kartu KPI + peringatan + instruksi; notifikasi dorong untuk peringatan merah.
- Lintas perusahaan: BOD grup melihat perusahaan yang ditetapkan pada `energi_akses_bod` (`user_id`, `company_id`) — melewati `MilikPerusahaan` hanya melalui `withoutGlobalScope` eksplisit di `BodController` dengan penyaringan daftar perusahaan tersebut (ditinjau ketat dalam code review).

### 9.4 Kriteria penerimaan BOD (contoh)
- Diberikan data 3 site 12 bulan, saat BOD memilih "YTD" dan site "Pit A", maka semua kartu menampilkan nilai yang sama dengan Laporan Kinerja Energi untuk rentang & site yang sama (selisih 0).
- Klik kartu "Emisi" membuka rincian S1/S2/S3 dan dapat turun sampai baris harian dengan versi faktor yang dipakai.
- Instruksi yang dibuat muncul di daftar `TindakLanjut` PIC dan memicu surel; status berubah ke "selesai" saat PIC menutupnya dengan bukti.
- Board pack berisi nomor dokumen, tanggal data, filter, dan tidak berubah meskipun data dikoreksi kemudian (snapshot).

---

## 10. Pusat Pelaporan — templat

Setiap templat adalah kelas `App\Support\Energi\Templat\*` dengan metode `sumber(Periode): array`, `validasi(): array` (daftar galat pemblokir & peringatan), `pdf()`, `xlsx()`, `csv()` (opsional). Daftar kolom final mengikuti format resmi saat tersedia; bila format resmi belum diperoleh, struktur di bawah adalah rancangan [A-05][A-07].

| Kode | Templat | Dasar | Periode & tenggat | Isi pokok |
|---|---|---|---|---|
| `ESDM_ME` | **Laporan Manajemen Energi tahunan ke Menteri ESDM** | PP 33/2023; Permen ESDM 8/2025 Ps. 17 & Lampiran I [R-01][R-02] | Tahun t, disampaikan ≤ 30 Juni t+1 (elektronik/POME) | Identitas perusahaan & site; manajer energi (nama, no. sertifikat); konsumsi energi per jenis (satuan asli, GJ, TOE/SBM); produksi & intensitas; SEU; program konservasi & realisasi; audit energi (tanggal, auditor, cakupan) & status rekomendasi; penghematan terverifikasi; rencana tahun berikut. **Lampiran I harus dicocokkan dengan teks resmi** |
| `ESDM_MONEV` | Laporan monev Manajer Energi ke manajemen puncak | Permen ESDM 8/2025 Ps. 10 | ≥1×/tahun | Kinerja EnPI vs EnB, sasaran, program, kendala, rekomendasi |
| `GRK_PU` | **Laporan Inventarisasi Emisi GRK Perusahaan** | Perpres 110/2025 Ps. 11–14 [R-03]; Permen ESDM 22/2019 (sektor energi) [R-09] | Tahun t, disampaikan ≤ Maret t+1 ke bupati/wali kota, gubernur, atau menteri terkait (sesuai kewenangan) | Batas organisasi; sumber emisi; data aktivitas; faktor & sumbernya (tier); emisi per gas (CO₂, CH₄, N₂O, HFC, PFC, SF₆) & CO₂e; CO₂ biogenik terpisah; ketidakpastian; QA/QC; tren vs tahun dasar |
| `SRN_AKSI` | Data aksi mitigasi untuk SRN PPI | Perpres 110/2025; Permen LHK 21/2022 [R-04][R-05] | Sesuai kebutuhan | Per aksi: jenis, lokasi, periode, metodologi, baseline, capaian penurunan, bukti |
| `ISO14064` | Laporan GRK organisasi | ISO 14064-1:2018 [R-19] | Tahunan | Struktur klausul 9 ISO 14064-1 (deskripsi organisasi, penanggung jawab, periode, batas, emisi langsung/tidak langsung per kategori 1–6 ISO, pengecualian, tahun dasar, metodologi, ketidakpastian, pernyataan verifikasi) |
| `PROPER` | Data energi & emisi untuk dokumen PROPER | Permen LHK 1/2021; Permen LH/BPLH 7/2025 [R-06] | Siklus penilaian PROPER | Efisiensi energi: absolut & intensitas, program, penghematan (GJ, Rp) 3–4 tahun; penurunan emisi: baseline, capaian (tCO₂e), program; masukan LCA (inventori energi per tahap) |
| `OJK_LK` | Bagian kinerja lingkungan Laporan Keberlanjutan | POJK 51/2017, SEOJK 16/2021 [R-15] | Tahunan, bersama laporan tahunan | Penggunaan energi (jumlah & intensitas), upaya efisiensi, emisi GRK, upaya penurunan |
| `PSPK2` | Metrik iklim PSPK 2 | PSPK 2 (IFRS S2) [R-16] | Periode mulai ≥ 1 Jan 2027 | Scope 1, 2 (location-based + info market-based), 3 (dengan keringanan 3 tahun), metode GHG Protocol, target iklim & kemajuan, harga karbon internal, aset/aktivitas rentan risiko transisi |
| `KINERJA_BLN` | Laporan Kinerja Energi bulanan (yang sudah ada, ditingkatkan) | Internal | Bulanan | Ringkasan, per kategori, peringkat unit, peluang, karbon |
| `BOD_PACK` | Board pack | Internal | Sesuai rapat | §9 W13 |

**Aturan validasi umum pemblokir terbit:** periode belum terkunci; faktor tidak tersedia; manajer energi tidak bersertifikat (untuk `ESDM_ME`); batas organisasi belum disetujui (untuk `GRK_PU`, `ISO14064`, `PSPK2`); paraf belum lengkap.

**Ekspor:** PDF memakai kop & penomoran `KopDokumen` (pemenggalan halaman di server seperti laporan energi saat ini); XLSX memakai PhpSpreadsheet dengan sheet "Ringkasan", "Data aktivitas", "Faktor", "Jejak"; CSV/JSON untuk portal.

---

## 11. API & integrasi

### 11.1 API internal (sesi web, JSON)
| Metode & path | Fungsi |
|---|---|
| `GET /energi/api/faktor?kode=&parameter=&tanggal=&site=` | Faktor berlaku |
| `POST /energi/api/kalkulator` | Hitung GJ/TOE/tCO₂e/Rp untuk input bebas (dipakai halaman Kalkulator & simulator) |
| `GET /energi/api/agregat?metrik=&dari=&sampai=&group_by=` | Data grafik |

### 11.2 Ingest mesin (`/api/energi/v1`, token Bearer per perusahaan)
| Metode & path | Isi |
|---|---|
| `POST /api/energi/v1/bbm` | `[{tanggal, kode_sumber, liter, hm, persen_fame?, sumber_data, ref_eksternal}]` |
| `POST /api/energi/v1/listrik` | `[{tanggal, kode_sumber, kwh, puncak_kw, jam_operasi, pemasok_listrik, ref_eksternal}]` |
| `POST /api/energi/v1/produksi` | `[{tanggal, kode_site, ton, bcm, jarak_angkut_km?}]` |
| `GET /api/energi/v1/status/{batch}` | Status pemrosesan |

Aturan: idempoten pada (`company_id`, `ref_eksternal`); maks. 5.000 baris/permintaan; rate limit 60 permintaan/menit/token; baris masuk sebagai `diajukan` dengan `sumber_data = fms/meter`; respons memuat galat per baris. Pola autentikasi mengikuti `Api/AbsensiIngestController` yang sudah ada.

### 11.3 Integrasi modul EQOHSEE lain
- **Operasi Tambang** (`MineOperationalRecord`): sumber produksi alternatif (opsi impor harian ke `energy_production`).
- **Maintenance/Work Order**: tautkan anomali unit boros ke WO pemeriksaan.
- **Biaya** (`BiayaRealisasi`): realisasi biaya BBM/listrik untuk validasi harga.
- **Lingkungan**: berbagi site/area; PROPER memakai data keduanya.
- **TindakLanjut**: rekomendasi audit & instruksi BOD.
- **Dokumen/KopDokumen**: penomoran & kop laporan.

### 11.4 Portal pemerintah
v1: berkas siap unggah + pencatatan bukti penyampaian manual (nomor tanda terima, tanggal, tangkapan layar). API langsung ditunda sampai ada dokumentasi resmi [A-07].

---

## 12. Notifikasi & kalender kepatuhan

Perintah `energi:pengingat` dijadwalkan harian 07:00 per zona waktu site.

| Pemicu | Penerima | Saluran | Waktu |
|---|---|---|---|
| Data harian belum diinput | Operator site | Dalam aplikasi, seluler | H+1 09:00 |
| Baris `diajukan` > 3 hari | Verifikator | Surel, aplikasi | Harian |
| Anomali konsumsi | Verifikator, Manajer Energi | Aplikasi, seluler | Segera |
| Periode bulan lalu belum dikunci | Manajer Energi | Surel | Tanggal 10 |
| Tenggat kewajiban (`energi_kewajiban`) | PIC, Manajer Energi, KTT; BOD bila ≤7 hari & belum selesai | Surel, aplikasi | H-60, H-30, H-7, H-1, terlambat |
| Sertifikat manajer/auditor energi kedaluwarsa | Manajer Energi, HR | Surel | H-90, H-30 |
| Rekomendasi audit mendekati tenggat 3 tahun | PIC | Lewat `KirimPengingatTindakLanjut` | H-90, H-30 |
| Instruksi BOD baru / lewat tenggat | PIC / BOD | Surel, seluler | Segera / harian |
| Faktor baru diajukan | Petugas GRK lain | Aplikasi | Segera |

Kewajiban bawaan yang dibuat otomatis per perusahaan setiap tahun: `ESDM_ME` (30 Juni t+1, bila status WAJIB), `ESDM_MONEV`, `GRK_PU` (31 Maret t+1), `AUDIT_ENERGI` (3 tahun sejak audit terakhir), `PROPER` (tanggal diatur admin per siklus), `PSPK2` & `OJK_LK` (bila perusahaan emiten; tanggal laporan tahunan).

---

## 13. Aplikasi seluler (Flutter, `mobile/`)

- Layar baru: **Isi BBM** (pilih unit via daftar/QR stiker unit, liter, HM, foto nota/flowmeter), **Listrik harian** (area, kWh, puncak), **Produksi** (admin produksi), **Ringkasan BOD** (KPI + peringatan + instruksi).
- Luring: antrean lokal (SQLite di perangkat), sinkron saat daring lewat `POST /api/energi/v1/seluler/sinkron` (token sesi pengguna), konflik ditangani "server menang + catatan konflik ke pengguna".
- Validasi lokal sama dengan server (rentang wajar diunduh bersama data induk).
- Notifikasi dorong untuk peringatan & instruksi.

---

## 14. Migrasi dari modul lama

| Langkah | Rincian |
|---|---|
| M1 | Buat `energi_sites`: satu site bawaan per perusahaan ("Site Utama") ; isi `site_id` semua baris lama |
| M2 | Buat `energi_sumber` dari `energy_equipment` (jenis `alat_berat`, kategori_ipcc `bergerak_offroad`) dan dari kombinasi (`area`,`sumber`) di `energy_power_logs` (jenis `area_listrik`/`genset`) |
| M3 | Paket faktor awal (§17) diimpor dengan `berlaku_mulai = 2000-01-01` sebagai faktor bersama, ditambah faktor "warisan" bernilai sama dengan konstanta lama (status `ditarik`) untuk keperluan perbandingan |
| M4 | Status data lama = `terverifikasi`; periode lama `terbuka` (agar dapat dikunci setelah diperiksa) |
| M5 | `Energi::GJ_PER_LITER` dkk. tetap ada sebagai konstanta **deprecated** yang hanya dipakai bila registri kosong di lingkungan uji; produksi wajib registri |
| M6 | `ringkasan()` di `EnergyController` dialihkan ke `Inventaris`/agregat; keluaran kunci lama (`gj`, `tco2e`, `rupiah`, `intensitas`, `l_ton`, …) tetap disediakan agar `Dasbor.vue` lama tidak rusak sebelum diganti |
| M7 | Laporan perbandingan migrasi: tCO₂e & GJ 12 bulan terakhir rumus lama vs baru, diserahkan ke Petugas GRK untuk diterima |

Seluruh migrasi skema bersifat aditif (kolom nullable/tabel baru); tidak ada kolom lama yang dihapus pada rilis F1.

---

## 15. Perbaikan cacat yang diketahui

| # | Cacat | Lokasi | Perbaikan |
|---|---|---|---|
| D1 | Form peluang default `status: 'diusulkan'`, padahal `Energi::STATUS_PELUANG` berisi `usulan` → simpan gagal validasi | `resources/js/Pages/Energi/Halaman.vue` (deklarasi `peluang = useForm`) | Default `'usulan'`; ambil daftar status dari prop server |
| D2 | Kalkulator memakai konstanta 0,0358/0,0036 di frontend dan tanpa CO₂ | `Halaman.vue` (`hasilKalkulator`) | Panggil `POST /energi/api/kalkulator` |
| D3 | Gas tidak punya form input | `EnergyController@input` | Form "Gas & energi lain" (§8.1) |
| D4 | Mode selain dasbor "tampilan darurat" | `Halaman.vue` | Komponen per halaman (§8) |
| D5 | `hapusUnit` menghapus kaskade catatan BBM (`cascadeOnDelete`) | migrasi `create_energy_tables` | Ganti menjadi nonaktifkan (`aktif=false`); hapus hanya bila tanpa catatan; data terkunci tidak boleh terhapus |
| D6 | `laporan()` memanggil `$this->perusahaanKop($request ?? null)` | `EnergyController@laporan` | Tinjau saat refaktor ke Pusat Pelaporan (pastikan perusahaan kop = perusahaan terpilih) |

---

## 16. Pengujian & kriteria penerimaan

### 16.1 Uji unit (`tests/Unit/Energi/*`)
- `KalkulatorTest`: 1.000 L solar B0 (ρ 0,84, NCV 43,0, EF 74.100 kg/TJ) → 36,12 GJ; CO₂ 2,676 t; CH₄/N₂O sesuai kategori; CO₂e AR5. Kasus B40 & B50: CO₂ fosil turun sesuai `f_energi`, CO₂ biogenik terpisah.
- `RegistriFaktorTest`: pemilihan prioritas (§6.1), interval tidak tumpang tindih, faktor tidak tersedia → null + penanda.
- `AmbangTest`: batas tepat 4.000 TOE = WAJIB (≥).
- `EnpiTest`: regresi data sintetis memulihkan koefisien; ambang R²/CV(RMSE).
- `AnomaliTest`: z robust, HM mundur.

### 16.2 Uji fitur (`tests/Feature/Energi/*`, memperluas `EnergiTest`)
- Batas perusahaan: pengguna perusahaan A tidak melihat baris/agregat/laporan perusahaan B pada semua rute baru (termasuk BOD, ekspor, API).
- Alur: draf → diajukan → terverifikasi → terkunci; perubahan baris terkunci ditolak (403/422); koreksi disetujui → laporan `perlu_revisi`.
- Snapshot: ubah faktor setelah laporan terbit → PDF/angka laporan tidak berubah; hash cocok.
- Kunci periode ditolak bila syarat §7.2 tidak terpenuhi.
- Ingest API: idempoten, batas baris, token kedaluwarsa ditolak.
- BOD: angka kartu = angka laporan untuk filter sama; instruksi membuat `TindakLanjut`.
- Regresi: seluruh uji `EnergiTest` lama tetap hijau.

### 16.3 Uji antarmuka
Playwright (sudah ada `tools/tembak-fitur.mjs` untuk tangkapan layar): skenario input → verifikasi → kunci → susun laporan → terbit → unduh; dasbor BOD drill-down. Setelah rilis, terbitkan ulang `docs/jual/energi`.

### 16.4 Uji penerimaan pengguna (UAT)
Satu siklus bulanan penuh di satu site percontohan + penyusunan `ESDM_ME` dan `GRK_PU` tahun 2026 dari data nyata, dibandingkan dengan perhitungan manual Manajer Energi (toleransi selisih ≤0,5%).

---

## 17. Lampiran: nilai faktor acuan awal

> **Penting:** nilai di bawah adalah nilai acuan umum dari IPCC 2006 Vol. 2 [R-20] dan praktik industri untuk mengisi registri saat pemasangan. **Setiap nilai harus dicocokkan dengan dokumen sumber** oleh Petugas GRK sebelum diaktifkan, dan faktor nasional (ESDM) diutamakan bila tersedia. Faktor grid **tidak** diisi di sini — wajib diambil dari lampiran Kepmen ESDM 163.K/HK.02/MEM.S/2021 atau penetapan terbaru Ditjen Ketenagalistrikan [R-10][A-03].

| Kode energi | Parameter | Nilai acuan | Satuan | Sumber acuan |
|---|---|---|---|---|
| solar (gas/diesel oil) | ncv | 43,0 | MJ/kg | IPCC 2006 Vol.2 Tabel 1.2 |
| solar | densitas | 0,84 | kg/L | asumsi umum, ganti dengan data pemasok |
| solar | ef_co2 | 74.100 | kg/TJ | IPCC 2006 Vol.2 Tabel 1.4 |
| solar — stasioner | ef_ch4 / ef_n2o | 3 / 0,6 | kg/TJ | IPCC 2006 Vol.2 Tabel 2.2–2.3 |
| solar — bergerak off-road (industri) | ef_ch4 / ef_n2o | 4,15 / 28,6 | kg/TJ | IPCC 2006 Vol.2 Tabel 3.3.1 |
| solar — bergerak jalan | ef_ch4 / ef_n2o | 3,9 / 3,9 | kg/TJ | IPCC 2006 Vol.2 Tabel 3.2.2 |
| biodiesel_fame | ncv / densitas | 27,0 MJ/kg (IPCC) — verifikasi; 0,88 kg/L | | IPCC 2006 Tabel 1.2 (biodiesel); densitas umum |
| biodiesel_fame | ef_co2 (biogenik) | 70.800 | kg/TJ | IPCC 2006 Tabel 1.4 |
| bensin | ncv / ef_co2 | 44,3 MJ/kg / 69.300 kg/TJ | | IPCC 2006 |
| lpg | ncv / ef_co2 | 47,3 MJ/kg / 63.100 kg/TJ | | IPCC 2006 |
| gas_alam | ncv / ef_co2 | 48,0 MJ/kg / 56.100 kg/TJ | | IPCC 2006 (konversi ke m³ memakai densitas pemasok) |
| anfo | ef_co2 | ≈0,17–0,19 | tCO₂/t | stoikiometri (5,5–6% minyak solar) [A-09] |
| — | toe | 41,868 | GJ/TOE | definisi IEA [A-02] |
| — | GWP AR5 | CH₄ 28 · N₂O 265 · SF₆ 23.500 · HFC-134a 1.300 | – | IPCC AR5 WG1 |
| — | GWP AR6 | CH₄ fosil 29,8 · N₂O 273 · SF₆ 25.200 · HFC-134a 1.530 | – | IPCC AR6 WG1 |
| — | harga karbon dasar | 30.000 | Rp/tCO₂e | UU 7/2021 HPP (tarif minimum pajak karbon) [R-12] |

Cek konsistensi dengan modul lama: 1 L solar B0 = 0,84 × 43,0 = 36,12 MJ; CO₂ = 0,03612 GJ × 74,1 kg/GJ = 2,677 kg ≈ konstanta lama 2,68 kg/L ✔.

---

*Akhir FSD v1.0. Perubahan berikutnya dicatat di tabel riwayat revisi di bawah.*

| Versi | Tanggal | Perubahan | Oleh |
|---|---|---|---|
| 1.0 | 21 Nov 2026 | Draf awal, termasuk dasbor BOD | Claude untuk Febrian |
