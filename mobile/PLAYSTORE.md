# EQOHSEE di Google Play — isian Play Console

Semua yang diminta Play Console, sudah diisi dan diperiksa terhadap apa
yang **benar-benar** dilakukan aplikasi ini. Salin apa adanya; bagian yang
perlu keputusan Anda ditandai **⚠**.

Urutan pengerjaan yang paling sedikit bolak-balik:

1. [Sebelum mengunggah apa pun](#1-sebelum-mengunggah-apa-pun) — web harus sudah terpasang
2. [Akun pengembang](#2-akun-pengembang) — organisasi, bukan pribadi
3. [Listing toko](#3-listing-toko)
4. [App content](#4-app-content--kebijakan-aplikasi) — privasi, Data safety, akses peninjau, dst.
5. [Unggah AAB](#5-unggah-aab)
6. [Bila ditolak](#6-bila-ditolak)

---

## 1. Sebelum mengunggah apa pun

Peninjau Play membuka tautan dan masuk ke aplikasi dari **server
produksi**. Yang mereka lihat harus sudah versi baru.

- [ ] **Deploy web ke VPS** (`bash deploy/deploy.sh`) — halaman berikut
      harus terbuka tanpa masuk:
  - https://eqohsee.id/kebijakan-privasi
  - https://eqohsee.id/privacy-policy
  - https://eqohsee.id/hapus-akun
  - https://eqohsee.id/delete-account
- [ ] **Kotak surel `privasi@eqohsee.id` benar-benar ada dan dibaca.**
      Alamat ini tercantum di kebijakan privasi, halaman hapus akun, dan
      listing. Surel yang memantul adalah alasan penolakan.
      (Alamatnya diatur lewat `HUKUM_SUREL` di `.env` bila ingin diganti.)
- [ ] **Buat akun peninjau** di VPS:

  ```bash
  php artisan peninjau:pasang --perusahaan=<KODE>
  ```

  Perintah ini mencetak surel dan sandi **sekali saja** — salin langsung
  ke Play Console (bagian 4.3). Menjalankannya lagi mengganti sandinya.

  **⚠ Pilih perusahaan contoh, bukan perusahaan klien sungguhan.**
  Peninjau Google adalah pihak luar; mereka tidak boleh melihat nama,
  NIK, dan hasil MCU pekerja sungguhan. Buat satu perusahaan demo
  (misalnya kode `DEMO`) berisi data rekaan, lalu arahkan
  `--perusahaan` ke sana.

  Setelah aplikasi disetujui dan tidak ada tinjauan yang berjalan:

  ```bash
  php artisan peninjau:pasang --nonaktif
  ```

  Nyalakan lagi (tanpa `--nonaktif`) sebelum mengirim rilis berikutnya —
  tiap rilis ditinjau ulang.

---

## 2. Akun pengembang

**⚠ Daftarkan sebagai Organisasi, bukan Pribadi.**

| | Pribadi | Organisasi |
|---|---|---|
| Syarat sebelum boleh Production | Uji tertutup **≥ 12 penguji** yang ikut **14 hari berturut-turut** | Tidak ada |
| Nama penerbit di toko | nama pribadi | nama perusahaan |
| Yang diminta saat daftar | KTP | **Nomor D-U-N-S** perusahaan (gratis, ±1–2 minggu, di dnb.com) |
| Biaya | USD 25 sekali | USD 25 sekali |

Untuk aplikasi yang dijual ke perusahaan tambang, akun organisasi juga
yang dipercaya: pembeli melihat nama perusahaan sebagai penerbit.
Kalau terlanjur akun pribadi, rencanakan uji tertutup 14 hari sebelum
tanggal peluncuran.

---

## 3. Listing toko

**Grow → Store presence → Main store listing.** Bahasa bawaan:
**Indonesian – id**. Tambahkan terjemahan **English (United States) – en-US**.

### 3.1 Teks — Bahasa Indonesia

**Nama aplikasi** (25/30)
```
EQOHSEE: HSE & K3 Tambang
```

**Deskripsi singkat** (74/80)
```
Lapor bahaya, P2H unit, dan izin kerja tambang — tetap jalan tanpa sinyal.
```

**Deskripsi lengkap**
```
EQOHSEE adalah aplikasi HSE dan K3 untuk perusahaan pertambangan, dibuat untuk dipakai di muka tambang: tombol besar, layar ringkas, dan tetap bekerja ketika sinyal hilang.

LAPOR BAHAYA DALAM HITUNGAN DETIK
• Foto langsung dari kamera ponsel, titik lokasi terisi sendiri dari GPS
• Matriks risiko 5×5: ketuk satu sel, tingkat risiko dan batas tindak lanjut terisi otomatis
• Setiap laporan punya linimasa, dari dilaporkan sampai ditutup dengan foto bukti perbaikan

P2H PRA-OPERASI UNIT
• Daftar periksa harian untuk unit sebelum dipakai
• Butir kritis yang gagal langsung menahan unit dan menerbitkan perintah kerja untuk mekanik

IZIN KERJA
• Izin yang berlaku hari ini dalam satu layar
• Kesegaran uji gas (O2, LEL, H2S, CO) terhadap ambang, lengkap dengan sisa menitnya
• Peringatan bila pekerjaan berbahaya bentrok di lokasi yang sama

TETAP JALAN TANPA SINYAL
• Laporan dan fotonya disimpan aman di ponsel, lalu terkirim otomatis begitu sinyal kembali
• Draf tidak hilang walau aplikasi tertutup

AWAL SHIFT DALAM SATU LAYAR
• Peringatan pemantauan lereng dan kolam, cuaca, serta tugas yang mendekati tenggat

SELURUH MODUL EQOHSEE
Dari aplikasi yang sama tersedia versi lengkap EQOHSEE sesuai hak akses Anda: audit SMKP Minerba, inspeksi, kompetensi dan MCU, roster, absensi, cuti, lembur, penggajian, kepatuhan ISO, dan pelatihan.

PRAKTIS DI LAPANGAN
• Pintasan: tekan lama ikon aplikasi untuk langsung ke Lapor bahaya, P2H, atau Tugas
• Ekspor dan lampiran tersimpan ke folder Download ponsel
• Kunci sidik jari opsional; aplikasi terkunci lagi setelah dua menit ditinggal

HEMAT IZIN, TANPA IKLAN
• Lokasi hanya diminta saat aplikasi dipakai, tidak pernah di latar belakang
• Tanpa izin kamera dan galeri: foto diambil lewat aplikasi kamera ponsel, dan hanya foto yang Anda pilih yang dikirim
• Tanpa iklan, data tidak dijual

UNTUK SIAPA
Pekerja, pengawas, dan tim HSE di perusahaan yang memakai EQOHSEE. Masuk dengan akun dari perusahaan Anda, atau daftar sebagai pekerja baru lalu pilih perusahaan Anda.

Kebijakan privasi: https://eqohsee.id/kebijakan-privasi
```

### 3.2 Teks — English (United States)

**App name** (24/30)
```
EQOHSEE: Mining HSE & K3
```

**Short description** (76/80)
```
Hazard reports, pre-start checks and work permits for mines — works offline.
```

**Full description**
```
EQOHSEE is an HSE and occupational safety app for mining companies, built for the pit: large buttons, compact screens, and it keeps working when the signal drops.

REPORT A HAZARD IN SECONDS
• Take photos with the phone camera; the location fills itself in from GPS
• 5×5 risk matrix: tap one cell and the risk level and follow-up deadline are set for you
• Every report has a timeline, from reported to closed with photo evidence of the fix

PRE-START EQUIPMENT CHECKS (P2H)
• Daily checklist before a unit goes to work
• A failed critical item holds the unit and raises a work order for the mechanic

WORK PERMITS
• Today's active permits on one screen
• Gas test freshness (O2, LEL, H2S, CO) against thresholds, with minutes remaining
• A warning when hazardous jobs clash at the same location

WORKS WITHOUT SIGNAL
• Reports and their photos are kept safely on the phone and sent automatically when the signal returns
• Drafts survive the app being closed

THE START OF SHIFT ON ONE SCREEN
• Slope and pond monitoring alerts, weather, and tasks nearing their deadline

ALL OF EQOHSEE
The full EQOHSEE platform is one tap away, according to your access: SMKP Minerba audits, inspections, competencies and medical check-ups, rosters, attendance, leave, overtime, payroll, ISO compliance and training.

MADE FOR THE FIELD
• Shortcuts: long-press the app icon to jump to Report hazard, P2H or Tasks
• Exports and attachments are saved to the phone's Download folder
• Optional fingerprint lock; the app locks again after two minutes away

FEW PERMISSIONS, NO ADS
• Location is requested only while the app is in use, never in the background
• No camera or photo-library permission: photos are taken by the phone's camera app, and only the photos you pick are sent
• No ads, and data is never sold

WHO IT IS FOR
Workers, supervisors and HSE teams at companies that use EQOHSEE. Sign in with the account from your company, or register as a new worker and choose your company.

Privacy policy: https://eqohsee.id/privacy-policy
```

> Nama "EQOHSEE: Mining HSE & K3" sengaja tetap memuat "K3": itu istilah
> yang dicari pekerja Indonesia walau ponselnya berbahasa Inggris.

### 3.3 Grafik

Semua ada di `mobile/playstore/grafik/`, sudah sesuai ukuran Play:

| Kolom di Console | Berkas | Ukuran |
|---|---|---|
| App icon | `ikon-512.png` | 512 × 512, tanpa transparansi |
| Feature graphic | `feature-graphic-1024x500.png` | 1024 × 500 |
| Phone screenshots (urut) | `ponsel-01.png` … `ponsel-08.png` | 1080 × 1920 (9:16) |

Urutan tangkapan layar sudah disusun untuk dibaca dari kiri: beranda →
lapor → matriks → luring → P2H → izin kerja → linimasa → privasi.
Dua atau tiga yang pertama adalah yang terlihat di hasil pencarian, jadi
jangan diacak.

Isi tangkapan layar diambil dari aplikasi yang sebenarnya berjalan
dengan data contoh (bukan gambar rekaan), sesuai aturan metadata Play.

### 3.4 Rincian kontak dan kategori

**Store settings:**

| Kolom | Isi |
|---|---|
| App category | **Business** |
| Tags | Business, Productivity (pilih yang ditawarkan Console) |
| Email | `privasi@eqohsee.id` (**⚠** atau alamat dukungan lain yang dibaca) |
| Website | `https://eqohsee.id` |
| Phone | opsional |

---

## 4. App content — kebijakan aplikasi

**Policy and programs → App content.** Semua bagian harus hijau sebelum
rilis mana pun dapat dikirim.

### 4.1 Privacy policy

```
https://eqohsee.id/kebijakan-privasi
```

Halaman ini publik, berbahasa Indonesia dengan tautan ke versi Inggris
(`/privacy-policy`), menyebut nama paket `id.eqohsee.eqohsee`, dan —
yang paling sering membuat aplikasi ditolak — **isinya cocok dengan
jawaban Data safety di bawah**. Kalau salah satunya diubah, ubah juga
yang lain.

Di dalam aplikasi, tautannya ada di **Profil → Kebijakan privasi** dan
di **Profil → Pengaturan aplikasi**.

### 4.2 Ads

**No, my app does not contain ads.**

### 4.3 App access

Pilih **All or some functionality in my app is restricted**, lalu
**Add instructions**:

| Kolom | Isi |
|---|---|
| Instruction name | `Akun peninjau` |
| Username / email | hasil `php artisan peninjau:pasang` (bawaan `peninjau.play@eqohsee.id`) |
| Password | hasil perintah yang sama |
| Any other information | lihat di bawah |

```
The app opens on a short introduction and a location-permission explanation; tap "Nanti saja" to skip or "Izinkan lokasi & mulai" to allow.
Sign in with the credentials above. No verification code or two-factor step is required for this account.
After sign-in the app opens field mode: Beranda (home), Modul, Lapor (report a hazard), Tugas (tasks) and Profil.
To test account deletion: Profil → Akun & kata sandi → Hapus akun.
In-app privacy policy: Profil → Kebijakan privasi.
```

Ceklis **Allow Android to use the credentials you provide for
performance and app compatibility testing** — supaya laporan pra-peluncuran
(pre-launch report) juga dapat masuk, bukan berhenti di halaman masuk.

### 4.4 Content rating

Isi kuesioner IARC dengan kategori **All other app types**. Jawab
jujur; untuk aplikasi ini:

| Pertanyaan | Jawaban |
|---|---|
| Kekerasan, seksual, bahasa kasar, zat terlarang, judi | **No** |
| Users can interact or exchange content | **Yes** — laporan bahaya beserta fotonya dibaca pemakai lain di perusahaan yang sama |
| Shares user's current physical location with other users | **Yes** — titik lokasi laporan bahaya terlihat oleh pengawas |
| Digital purchases | **No** |
| Web browser / search engine | **No** — WebView hanya membuka eqohsee.id; situs lain dilempar ke peramban |

Hasil yang wajar: rating umur rendah dengan keterangan *Users Interact*
dan *Shares Location*. Jangan menjawab "No" untuk dua butir di atas
supaya ratingnya bersih — ketidaksesuaian rating adalah alasan
penurunan aplikasi, bukan hanya penolakan.

### 4.5 Target audience and content

- Target age: **18 and over** saja.
- Appeals to children: **No**.

Aplikasi kerja untuk orang dewasa; memilih kelompok umur di bawah 18
menyeret aplikasi ke kebijakan Families yang tidak relevan.

### 4.6 Data safety

Jawaban ini disusun dari kebijakan privasi bagian 1 dan 4a — keduanya
harus tetap sepadan.

**Data collection and security**

| Pertanyaan | Jawaban |
|---|---|
| Does your app collect or share any of the required user data types? | **Yes** |
| Is all of the user data collected by your app encrypted in transit? | **Yes** (HTTPS saja; cleartext dimatikan di aplikasi) |
| Do you provide a way for users to request that their data is deleted? | **Yes** — URL: `https://eqohsee.id/hapus-akun` |

**Jenis data** (semua: *Collected* = Yes, *Shared* = **No**,
*Processed ephemerally* = No):

| Kategori → jenis | Wajib/opsional | Tujuan |
|---|---|---|
| Location → **Precise location** | Optional | App functionality |
| Personal info → **Name** | Required | App functionality, Account management |
| Personal info → **Email address** | Required | App functionality, Account management |
| Personal info → **User IDs** | Required | App functionality, Account management |
| Personal info → **Other info** (NIK, nomor induk, NPWP, jabatan) | Optional | App functionality |
| Financial info → **Other financial info** (gaji, PPh 21, BPJS) | Optional | App functionality |
| Health and fitness → **Health info** (status MCU) | Optional | App functionality |
| Photos and videos → **Photos** | Optional | App functionality |
| Files and docs → **Files and docs** | Optional | App functionality |
| App activity → **App interactions** (jejak tindakan pada data penting) | Required | App functionality, Fraud prevention, security, and compliance |

Mengapa **Shared = No**: menurut definisi Play, penyedia layanan yang
memproses data atas nama Anda (server, surel) dan permintaan resmi
instansi **bukan** "sharing". Data tidak dikirim ke pihak ketiga lain.

Yang **tidak** dicentang, dan alasannya:

- *Approximate location* — tidak dipakai; yang dikumpulkan hanya titik
  presisi pada laporan bahaya.
- *Device or other IDs* — aplikasi tidak membaca ID perangkat maupun ID
  iklan.
- *Crash logs / Diagnostics* — tidak ada SDK pelaporan galat di aplikasi.
- *Biometric* — kunci sidik jari dicocokkan oleh Android di perangkat,
  hasilnya tidak pernah dikirim.

### 4.7 Deklarasi lain

| Deklarasi | Jawaban |
|---|---|
| **Account deletion** | Aplikasi membuat akun (ada pendaftaran) → wajib. Di dalam aplikasi: **Profil → Akun & kata sandi → Hapus akun**. URL web: `https://eqohsee.id/hapus-akun` |
| **Government apps** | No |
| **Financial features** | *My app doesn't provide any financial features* (slip gaji hanya ditampilkan; tidak ada pembayaran, pinjaman, atau dompet) |
| **News apps** | No |
| **Health apps** | **⚠** Jangan pilih "tidak ada fitur kesehatan" — aplikasi menyimpan status MCU. Pilih kategori yang paling dekat dengan pengelolaan catatan kesehatan / kesehatan kerja; baca keterangan tiap pilihan di Console. |
| **Location permissions** | Tidak perlu deklarasi: aplikasi tidak meminta `ACCESS_BACKGROUND_LOCATION` |
| **Photo and video permissions** | Tidak perlu deklarasi: aplikasi tidak meminta `READ_MEDIA_IMAGES`/`READ_MEDIA_VIDEO` |
| **Foreground service** | Tidak perlu: tidak ada foreground service |

---

## 5. Unggah AAB

**Test and release → Testing → Internal testing** dulu.

1. **Create new release**.
2. **App integrity**: pilih **Use Google-generated key** (Play App
   Signing). Keystore `eqohsee-rilis.jks` menjadi *upload key* — lihat
   `RILIS.md` §0 untuk apa artinya kalau hilang.
3. Unggah `EQOHSEE-1.1.0.aab` (gabungkan dulu bila dikirim terpecah —
   `RILIS.md` §1).
4. **Release name**: `1.1.0 (2)`.
5. **Release notes**:

```
<id-ID>
• Mode lapangan: lapor bahaya dengan foto, GPS, dan matriks risiko 5×5
• P2H pra-operasi: butir kritis gagal langsung menahan unit
• Izin kerja: kesegaran uji gas dan peringatan bentrok lokasi
• Tetap jalan tanpa sinyal — laporan terkirim otomatis saat sinyal kembali
• Kunci sidik jari, pintasan ikon, dan unduhan ke folder Download
</id-ID>
<en-US>
• Field mode: hazard reports with photos, GPS and a 5×5 risk matrix
• Pre-start checks: a failed critical item holds the unit
• Work permits: gas test freshness and location clash warnings
• Works offline — reports are sent automatically when the signal returns
• Fingerprint lock, app shortcuts and downloads to the Download folder
</en-US>
```

6. **Save → Review release → Start rollout to Internal testing.**
7. **Testers**: buat daftar surel penguji, bagikan tautan ikut serta
   (*opt-in URL*). Penguji memasang dari Play Store seperti biasa.

Setelah internal testing baik:

- **Akun organisasi**: **Production → Create new release → Promote**
  dari internal. Pilih negara **Indonesia** (tambahkan lain bila perlu).
- **Akun pribadi**: naikkan dulu ke **Closed testing** dengan ≥ 12
  penguji selama 14 hari, baru **Apply for production** muncul.

Tinjauan Google untuk aplikasi baru biasanya beberapa hari, kadang
lebih dari seminggu. Aplikasi baru juga dapat butuh waktu sebelum muncul
di hasil pencarian — tautan langsung
`https://play.google.com/store/apps/details?id=id.eqohsee.eqohsee`
sudah berfungsi begitu rilis produksi disetujui.

### Peringatan yang boleh diabaikan

- *"This App Bundle contains native code, and you've not uploaded debug
  symbols"* — hanya saran untuk membaca laporan crash; tidak menahan
  rilis.
- *"There is no deobfuscation file associated with this App Bundle"* —
  kode Dart disamarkan oleh Flutter (bukan R8); peta simbolnya ada di
  `build/simbol` dan dipakai lewat `flutter symbolize`, bukan diunggah
  ke Play.

---

## 6. Bila ditolak

Surel penolakan menyebut kebijakan yang dilanggar. Yang paling mungkin
untuk aplikasi seperti ini, dan jawabannya:

| Alasan | Yang dilakukan |
|---|---|
| **Minimum functionality / WebView** | Aplikasi ini bukan sekadar situs dibungkus: layar pengenalan native, penjelasan izin sebelum diminta, kunci sidik jari, pintasan ikon, unduhan lewat pengelola unduhan sistem, antrean kirim tanpa sinyal, dan lembar pengaturan native. Sebutkan ini di banding (*appeal*), beserta bahwa aplikasi adalah alat kerja B2B yang memerlukan akun perusahaan. |
| **App access — tidak dapat masuk** | Akun peninjau nonaktif atau sandinya berganti. Jalankan `php artisan peninjau:pasang` lagi, perbarui sandi di 4.3, kirim ulang. |
| **Privacy policy tidak cocok** | Bandingkan 4.6 dengan halaman kebijakan privasi yang **sedang tayang** — sering kali web belum di-deploy. |
| **Account deletion** | Pastikan `https://eqohsee.id/hapus-akun` terbuka tanpa masuk dan surel `privasi@eqohsee.id` tidak memantul. |
| **Prominent disclosure (lokasi)** | Penjelasan tampil di layar native *sebelum* dialog izin sistem, dengan tombol menolak ("Nanti saja"). Sertakan tangkapan layar `ponsel-08.png` di banding. |

---

## 7. Rilis berikutnya

1. Naikkan `version:` di `pubspec.yaml` — angka sesudah `+` wajib naik
   (sekarang `1.1.0+2`).
2. Bangun: lihat `RILIS.md` §4.
3. Aktifkan lagi akun peninjau (bagian 1).
4. Bila aplikasi mulai mengumpulkan data baru atau meminta izin baru,
   **ubah kebijakan privasi dan Data safety lebih dulu**, baru unggah.
