# Modul Audit Internal SMKP Minerba

Modul audit tujuh elemen SMKP Minerba — **Kepdirjen Minerba No.
185.K/37.04/DJB/2019, Lampiran II** — diangkat dari aplikasi EQOHSEE menjadi
paket Laravel yang berdiri sendiri, agar dapat dipasang pada aplikasi Laravel
lain atau dibaca sebagai referensi implementasi.

Yang dibawa modul ini bukan hanya formulir penilaian, melainkan **seluruh alur
audit sebagaimana diatur Kepdirjen**: Tahap I (kelayakan, kecukupan
dokumentasi, Rencana Audit sembilan komponen), Tahap II (rapat pembukaan,
penilaian, temuan, rapat penutupan), lalu pelaporan — beserta berkas cetaknya
sebagai dokumen terkendali.

---

## 1. Isi modul

| Bagian | Berkas | Keterangan |
|---|---|---|
| Mesin hitung | `src/Support/Smkp.php` | Penilaian berbasis poin, kategori temuan, tingkat penerapan. Tanpa basis data, tanpa Laravel. |
| Tahapan | `src/Support/SmkpTahap.php` | Daftar acuan Tahap I & II, mandays, Rencana Audit, alur kerja empat babak. |
| Kop dokumen | `src/Support/Kop.php` | Nomor dokumen terkendali, revisi, divisi, dan tanggal untuk berkas cetak. |
| Acuan kriteria | `resources/data/elemen.json` | 7 elemen · 51 sub-elemen · 100 butir · 349 poin, tiap sub-elemen menyebut halaman acuannya. |
| Model | `src/Models/*.php` | `SmkpAudit`, `SmkpFinding`, `SmkpAttendee`. |
| Controller | `src/Http/Controllers/SmkpController.php` | 27 aksi: daftar, Tahap I, Rencana Audit, rapat, penilaian, temuan, empat berkas cetak. |
| Rute | `routes/smkp.php` | 33 rute; awalan, nama, dan middleware dari config. |
| Migrasi | `database/migrations/…` | Tiga tabel; kunci asing dipasang hanya bila tabel rujukannya ada. |
| Tampilan | `resources/js/…` | Dua halaman Inertia + Vue (kerja & cetak), tiga komponen pendukung. |
| Gaya | `resources/css/smkp.css` | Sepuluh kelas di luar Tailwind bawaan, termasuk aturan cetak A4. |
| Uji | `tests/*.php` | 50 uji: mesin hitung, tahapan, penomoran dokumen, dan pemasangan modul. |

### Cara penilaian

Penilaian berbasis **poin**, bukan sekadar sesuai/tidak sesuai. Auditor mengisi
capaian `0..maks` pada tiap butir; seratus butir masing-masing punya maksimum
sendiri (2 sampai 4) dan seluruhnya berjumlah 349 poin bila semua butir
berlaku.

```
capaian butir  = nilai / maks
capaian elemen = Σ nilai butir berlaku / Σ maks butir berlaku
nilai elemen   = capaian elemen × bobot elemen          (bobot 3..35 %)
nilai akhir    = Σ nilai elemen ÷ Σ bobot terpakai × 100
```

Dua keputusan yang membedakannya dari hitungan naif:

* Butir **N/A** (mis. tambang bawah tanah, kapal keruk, bahan peledak) keluar
  dari **pembagi** — tidak menghukum capaian perusahaan yang memang tidak punya
  kegiatan itu. Elemen yang seluruh butirnya N/A ikut keluar dari pembagi
  bobot, lalu hasilnya dinormalkan ke skala 100 agar tetap sebanding
  antar perusahaan.
* Butir yang **belum dinilai** dihitung **nol**, bukan dilewati. Audit yang baru
  berjalan 10 % karena itu tampak rendah — bukan tinggi palsu.

Kategori temuan **diturunkan dari nilai**, bukan dipilih auditor, sesuai kolom
"Kategori Temuan (Berdasarkan Nilai)" pada formulir kriteria: capaian < 50 %
Mayor, 50 % s.d. < 100 % Minor, 100 % Kesesuaian.

---

## 2. Prasyarat

* PHP 8.2+
* Laravel 11 atau 12
* Inertia + Vue 3 pada aplikasi induk (`@inertiajs/vue3`, Vite)
* Tailwind CSS — dipakai halaman modul; `resources/css/smkp.css` menutup kelas
  yang bukan bawaan Tailwind

Basis data mana pun yang didukung Laravel. Kolom JSON dipakai untuk isian
formulir, dan pengurutan jenis temuan ditulis sebagai `CASE WHEN` — SQL baku,
sehingga berlaku di SQLite, MySQL, maupun PostgreSQL.

---

## 3. Pemasangan

### 3.1 Ambil paketnya

Salin folder `packages/smkp-audit` ke aplikasi tujuan, lalu daftarkan sebagai
repositori path pada `composer.json` aplikasi:

```json
{
    "repositories": [
        { "type": "path", "url": "packages/smkp-audit" }
    ],
    "require": {
        "eqohsee/smkp-audit": "*"
    }
}
```

```bash
composer update eqohsee/smkp-audit
```

Paket ini menyebut `"version"` pada composer.json-nya supaya repositori path
terselesaikan sebagai rilis stabil; tanpa itu Composer menandainya `dev-<cabang>`
dan `"*"` ditolak oleh `minimum-stability: stable`. Bila Anda membuang ruas itu,
pakai `"eqohsee/smkp-audit": "@dev"`.

Penyedia layanannya ditemukan otomatis lewat package discovery; tidak ada yang
perlu ditambahkan ke `bootstrap/providers.php`.

### 3.2 Terbitkan tampilan dan gaya

**Wajib.** Vite hanya memindai `resources/js` milik aplikasi, jadi berkas Vue
harus disalin ke sana — tidak bisa dimuat dari dalam paket.

```bash
php artisan vendor:publish --tag=smkp-vue
```

Yang tersalin:

```
resources/js/Pages/Smkp/Halaman.vue        halaman kerja (index, form, show, acuan, Tahap I, rencana, rapat, nilai, temuan)
resources/js/Pages/Print/Smkp.vue          berkas cetak (berita acara, rencana, daftar hadir, laporan)
resources/js/Components/SmkpPrintShell.vue bingkai layar + tombol cetak
resources/js/Components/SmkpDocHeader.vue  kop dokumen terkendali
resources/js/Layouts/SmkpBlankLayout.vue   tata letak kosong untuk halaman cetak
resources/css/smkp.css                     kelas di luar Tailwind + aturan cetak A4
```

Lalu impor gayanya pada `resources/css/app.css`:

```css
@import './smkp.css';
```

Halaman cetak memakai `SmkpBlankLayout` lewat `defineOptions({ layout })`.
Bila aplikasi Anda memasang tata letak bawaan di `resolve()` — pola
`komponen.default.layout ??= AppLayout` — pemasangan itu tetap benar: `??=`
tidak menimpa tata letak yang sudah ditentukan halaman.

### 3.3 Migrasi

```bash
php artisan migrate
```

Migrasi paket ikut berjalan langsung dari paket. Bila ingin menyuntingnya,
terbitkan lebih dulu lalu matikan yang otomatis:

```bash
php artisan vendor:publish --tag=smkp-migrations
# config/smkp.php → 'migrasi' => false
```

### 3.4 Setelan

```bash
php artisan vendor:publish --tag=smkp-config
```

Modul jalan tanpa satu pun perubahan setelan. Yang di bawah ini menyesuaikannya
dengan aplikasi Anda.

---

## 4. Titik sambung ke aplikasi induk

Modul ini tidak menyebut satu pun kelas aplikasi induk. Lima hal yang biasanya
mengikat sebuah modul ke aplikasinya dibuat dapat ditukar — perusahaan, jejak
aktivitas, kop dokumen, rute, dan acuan kriteria:

### 4.1 Perusahaan (multi-tenant)

Kosongkan bila aplikasi Anda melayani satu perusahaan — kolom `company_id` tetap
ada, tetapi tidak pernah diisi maupun disaring.

```php
// config/smkp.php
'model' => [
    'perusahaan' => App\Models\Company::class,
    'pengguna'   => App\Models\User::class,
],

'perusahaan' => [
    'penyaring' => App\Smkp\PenyaringPerusahaan::class,  // opsional
    'atribut'   => 'company_id',   // atribut pada model pengguna
    'nama'      => 'name',         // kolom nama perusahaan
    'tabel'     => 'companies',    // tabel rujukan kunci asing
    'admin'     => 'isAdmin',      // metode pengguna yang menjawab "boleh lintas"
],
```

Kolom perusahaan pada tabel modul selalu bernama `company_id`; yang dapat
disesuaikan adalah ke tabel mana ia menunjuk dan dari mana perusahaan aktif
dibaca.

Penyaring bawaan (`PerusahaanDariPengguna`) membaca `auth()->user()->company_id`
dan `auth()->user()->isAdmin()`. Bila aplikasi Anda menyimpannya di tempat lain
— tabel pivot, klaim token, tenancy pihak ketiga — buat kelas sendiri:

```php
class PenyaringPerusahaan implements \Eqohsee\SmkpAudit\Contracts\BatasPerusahaan
{
    public function idAktif(): int|string|null { return tenant()?->id; }
    public function lintasPerusahaan(): bool   { return auth()->user()?->can('smkp.lintas') ?? false; }
}
```

Batasnya dipasang sebagai **global scope**, bukan sebagai `where` di tiap
controller: batas yang ditulis ulang di tiap tempat pemakaian akan tertinggal
cepat atau lambat, dan yang tertinggal tidak menimbulkan galat — hanya data
perusahaan lain yang diam-diam ikut terbaca.

Temuan dan daftar hadir tidak punya kolom perusahaan sendiri; keduanya menyaring
lewat induknya (`whereHas('audit')`). Itu bukan penghematan kolom melainkan
penjagaan rute: `PUT /smkp/{smkp}/temuan/{temuan}` mengikat anaknya langsung,
dan tanpa penjaga di sisi anak, pengguna perusahaan mana pun dapat menyunting
temuan milik perusahaan lain.

### 4.2 Jejak aktivitas

```php
'jejak' => App\Smkp\JejakAktivitas::class,
```

```php
class JejakAktivitas implements \Eqohsee\SmkpAudit\Contracts\PencatatJejak
{
    public function catat(string $aksi, ?string $rincian = null): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'module'  => 'smkp',
            'action'  => $aksi,
            'detail'  => $rincian,
        ]);
    }
}
```

Bawaannya `JejakDiam` (tidak mencatat apa pun). Tersedia juga `JejakLog` yang
menulis ke kanal log Laravel.

Modul mencatat tiga perbuatan: pembuatan periode audit, penghapusannya, dan
perpindahan tahap.

### 4.3 Kop dokumen terkendali

Berkas cetak terbit sebagai dokumen terkendali: tiap lembar membawa nomor
dokumen, tanggal penerbitan, tanggal persetujuan, nomor revisi, dan nomor
halaman. Nomornya tersusun dari prefiks perusahaan dan kode formulir —
`CAM-OHSE-IV.067h`, `GBU-OHSE-IV.059`.

```php
'kop' => [
    'divisi'     => 'Occupational Health, Safety and Environment, External',
    'departemen' => 'Occupational Health, Safety and Environment',
    'atribut' => [
        'nama' => 'name', 'prefiks' => 'doc_no_prefix',
        'terbit' => 'doc_terbit', 'setuju' => 'doc_setuju',
        'revisi' => 'doc_revisi', 'logo' => 'logo',
    ],
],
```

Atribut yang tidak ada pada model perusahaan Anda diabaikan tanpa galat, dan
perusahaan tanpa prefiks tetap menghasilkan nomor yang terbaca (prefiks
diturunkan dari inisial namanya: "PT Cemerlang Asa Mandiri" → `CAM`). Berkas
cetak tidak boleh gagal hanya karena data induk belum lengkap.

### 4.4 Rute

```php
'rute' => [
    'daftar'     => true,
    'awalan'     => 'smkp',
    'nama'       => 'smkp.',
    'middleware' => ['web', 'auth'],
    'middleware_hapus' => ['can:admin'],   // penghapusan periode audit
],
```

Awalan dikirim ke Vue sebagai prop `awalan`, sehingga memindahkan modul ke
`/audit-smkp` tidak menuntut satu pun suntingan pada berkas Vue.

Aplikasi yang ingin menyusun rutenya sendiri menyetel `'daftar' => false` lalu
menyalin `routes/smkp.php`. Urutannya perlu dipertahankan: rute beruas tetap
(`buat`, `acuan`, `lanjut/…`) harus berada **sebelum** `{smkp}`, jika tidak
pengikat model akan mencari audit bernomor "buat" dan menjawab 404.

### 4.5 Acuan kriteria

Untuk menyunting kriteria, terbitkan berkasnya lalu tunjuk salinannya:

```bash
php artisan vendor:publish --tag=smkp-data     # → resources/data/smkp/elemen.json
```

```php
'acuan' => resource_path('data/smkp/elemen.json'),
```

Bentuk berkasnya: `meta`, `penilaian`, `kategori`, `tingkat`, `elemen[]`. Tiap
sub-elemen menyebut `ref` (halaman acuan pada Kepdirjen) sehingga setiap angka
dapat ditelusuri kembali ke sumbernya. Nilai maksimum sub-elemen yang punya
rincian **tidak** disimpan terpisah melainkan dijumlahkan dari rinciannya — pada
sumber aslinya kedua angka itu sempat berbeda (V.5 tertulis 8 padahal
rinciannya berjumlah 20).

---

## 5. Tabel

| Tabel | Isi |
|---|---|
| `smkp_audits` | Satu periode audit per perusahaan per tahun. Kolom JSON: `hasil`, `auditor`, `profil`, `permulaan`, `rencana`, `kecukupan`, `kinerja`, `risiko`. |
| `smkp_findings` | Temuan beserta tindakan perbaikannya (CAR): akar masalah, tindakan, penanggung jawab, target, status, verifikasi. |
| `smkp_attendees` | Daftar hadir rapat pembukaan dan penutupan Tahap II. |

Isian formulir sengaja disimpan sebagai JSON, bukan kolom tersendiri: bentuk
formulir mengikuti acuan yang dapat berubah, dan menaruhnya di kolom berarti
bermigrasi tiap kali satu baris isian bertambah. Bentuk `hasil`:

```php
$audit->hasil['II.2.1']  = ['v' => 3, 'ket' => '…', 'bukti' => '…'];
$audit->hasil['IV.5.1']  = ['v' => 'N/A'];
```

---

## 6. Alur pemakaian

1. **Buat periode audit** — satu perusahaan, satu tahun (dijaga indeks unik).
2. **Tahap I** `/smkp/{id}/tahap-1` — kontak awal, tujuh indikator kelayakan,
   sepuluh angka kinerja keselamatan pertambangan, hari kerja audit (mandays),
   dan kecukupan dokumentasi tujuh elemen.
3. **Rencana Audit** `/smkp/{id}/rencana` — sembilan komponen wajib, disahkan
   KTT dan Ketua Tim.
4. **Tahap II terbuka** hanya setelah seluruh elemen ditinjau kecukupannya
   **dan** Rencana Audit lengkap. Inilah yang membedakan dua tahap dari sekadar
   dua menu — lihat `SmkpAudit::siapTahapDua()`.
5. **Rapat pembukaan** `/smkp/{id}/rapat` — peserta pertama memindahkan audit ke
   tahap lapangan.
6. **Penilaian** `/smkp/{id}/elemen/{kode}` — per elemen, dengan keterangan dan
   bukti objektif tiap butir.
7. **Temuan** `/smkp/{id}/temuan` — ketidaksesuaian diangkat menjadi CAR
   sekaligus; penomorannya berjalan terpisah per jenis (`NC-MYR-01`,
   `NC-MNR-01`).
8. **Berkas cetak** — Berita Acara Tahap I, Laporan Rencana Audit, dua daftar
   hadir, dan Laporan Audit. Semuanya lembar A4 bernomor; pemenggalan halaman
   ditentukan sisi server karena peramban tidak mendukung `counter(page)` di
   sini, sehingga "Halaman 2 dari 3" pada kop selalu benar.

Menu samping dapat menautkan `smkp.ke.tahap1`, `smkp.ke.rencana`,
`smkp.ke.rapat`, `smkp.ke.temuan`, `smkp.ke.berita`, `smkp.ke.rencana-cetak`,
dan `smkp.ke.laporan` — rute tanpa parameter yang menyalurkan ke periode yang
sedang berjalan.

---

## 7. Uji

```bash
cd packages/smkp-audit
composer install
vendor/bin/phpunit
```

50 uji, 319 pemeriksaan:

| Berkas | Yang dijaga |
|---|---|
| `SmkpTest.php` | Struktur acuan (7 elemen, 51 sub-elemen, bobot genap 100 %), rumus penilaian, perlakuan N/A, kategori temuan, penomoran `NC-MYR-01`. |
| `SmkpTahapTest.php` | Sembilan komponen Rencana Audit, kecukupan dokumentasi, hitungan mandays, empat babak alur. |
| `KopTest.php` | Prefiks nomor dokumen dan kode formulir. |
| `PemasanganTest.php` | Setelan termuat, dua kontrak terikat ke adaptor bawaan dan dapat ditukar, seluruh rute terdaftar dengan nama serta urutan yang benar, tiap rute menunjuk aksi yang memang ada. |

Angka pembanding pada uji hitungan diambil dari dokumen audit nyata — Formulir
Kriteria PT CAM 2025 dan Berita Acara Tahap I PT GBU 2023 — supaya hasilnya
dapat diperiksa terhadap sesuatu yang benar-benar terjadi, bukan hanya terhadap
rumus yang sama yang sedang diuji.

Tiga berkas pertama berjalan tanpa membangkitkan aplikasi Laravel sama sekali;
itu sekaligus membuktikan mesin hitungnya benar-benar terlepas dari aplikasi
induk. `PemasanganTest` membangkitkan aplikasi sekadarnya — tanpa basis data,
tanpa HTTP — karena yang diuji memang pemasangannya, bukan alurnya.

Alur HTTP dan basis datanya diuji di aplikasi yang memasang modul ini; di
situlah rute, migrasi, dan penggunanya berada.

---

## 8. Perbedaan dari sumber (EQOHSEE)

Yang **dilepas** karena milik aplikasi induk:

* `ActivityLog`, `Company`, `User`, `KopDokumen`, `Document` → diganti kontrak,
  setelan, dan model bayangan.
* Tautan temuan → dokumen terkendali (`smkp_findings.document_id`) — modul
  dokumen tidak ikut diangkat.
* Berkas Blade lama `resources/views/smkp/*.blade.php` — sudah tidak dipakai
  sejak modul berpindah ke Inertia; hanya versi Vue yang diangkat.

Yang **diperbaiki** saat diangkat. Tiga yang pertama cacat nyata pada sumber:
formulir mengirim nilai yang ditolak validasi, dan penolakannya tidak
menjelaskan apa pun kepada auditor yang mengisinya.

* Pilihan kecukupan dokumentasi mengirim `Lengkap`/`Tidak lengkap`, sedangkan
  server memvalidasi `lengkap`/`tidak_lengkap` — pilihannya kini datang dari
  server (`kecukupanPilihan`).
* Pilihan kelas risiko memuat `Menengah`, sedangkan server menerima `Rendah`,
  `Sedang`, `Tinggi` — daftarnya kini datang dari server (`risikoKelas`).
* Formulir kinerja menampilkan kunci JSON (`fr`, `sr`, `asr`) sebagai label;
  kini menampilkan label dan satuannya.
* Kop dokumen pada halaman cetak dulu dirakit sebagai komponen fungsional tiga
  sel di dalam halaman — tanpa nomor dokumen, tanggal, maupun revisi. Kini
  komponennya utuh (`SmkpDocHeader.vue`), sepadan dengan kop pada berkas Blade
  yang menjadi acuannya.

---

## 9. Batas yang diketahui

* Halaman Vue-nya **fungsional, bukan halus**: satu berkas untuk sembilan mode.
  Untuk pemakaian sungguhan, ia lebih pantas dipecah per mode — modul ini
  diangkat sebagai referensi alur dan hitungan, dan di situlah nilainya.
* Tanda tangan pada daftar hadir masih berupa kolom kosong untuk ditandatangani
  setelah dicetak; kolom `tanda_tangan` sudah ada tetapi unggahannya belum
  dibuat.
* Modul belum memuat Rencana Tindak Lanjut (RTL) sebagai berkas cetak
  tersendiri, walau kode formulirnya (`tindak-lanjut`) sudah terdaftar pada
  `Kop::daftar()`.
* Bahasa antarmuka dan istilah kode: Indonesia, mengikuti istilah Kepdirjen.

---

## 10. Acuan

* Keputusan Direktur Jenderal Mineral dan Batubara No. 185.K/37.04/DJB/2019,
  Lampiran II — Petunjuk Teknis Pelaksanaan Audit SMKP Minerba (hal. 336–400).
* Berkas audit nyata yang menjadi pembanding istilah dan urutan isian: PT Gunung
  Bara Utama (2023) dan PT Cemerlang Asa Mandiri (2025).
