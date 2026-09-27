# EQOHSEE untuk Android

Pembungkus WebView, **bukan** penulisan ulang.

## Kenapa pembungkus

Dua puluh tujuh modul EQOHSEE — roster, absensi, payroll, SMKP, izin
kerja, dan seterusnya — memuat aturan yang tidak boleh punya salinan
kedua: lembur PP 35/2021, TER PPh 21, batas fatigue roster, rantai
PKWT. Menulis ulang semuanya dalam Dart berarti setiap aturan itu hidup
di dua tempat, dan sejak hari pertama boleh berbeda tanpa ada yang tahu
— sampai seseorang membandingkan slip gaji dari web dengan slip gaji
dari aplikasi.

Yang ditambahkan aplikasi ini adalah hal-hal yang tidak dapat dilakukan
peramban seluler biasa:

| | |
|---|---|
| Pengenalan pertama | tiga layar singkat, lalu penjelasan izin lokasi **sebelum** dialog izin Android (syarat *prominent disclosure* Play) |
| Mode lapangan | kunjungan dari aplikasi mendarat di `/lapangan` — lapor bahaya, P2H, izin kerja, tugas — dikenali dari penanda `EQOHSEE-Android` di user agent |
| Tanpa sinyal | laporan dan foto diantre di perangkat oleh halaman web, terkirim sendiri saat sinyal kembali; aplikasi memantau sambungan dan memuat ulang otomatis |
| Kunci sidik jari | opsional; terkunci lagi setelah 2 menit di latar — cukup longgar supaya kembali dari aplikasi kamera tidak memicu kunci |
| Pintasan ikon | tekan lama ikon → Lapor bahaya, P2H unit, Tugas saya |
| Unduhan | pengelola unduhan sistem **dengan sesi masuk** (cookie WebView), ke `Download/EQOHSEE` |
| Lembar pengaturan | Profil → Pengaturan aplikasi: kunci, izin lokasi, privasi, hapus akun, dukungan |
| Unggah foto | kamera dan galeri **tanpa izin kamera/galeri** — lewat aplikasi kamera sistem dan pemilih foto |
| Tombol kembali perangkat | menyusuri riwayat halaman, bukan langsung menutup aplikasi |
| Tautan luar | `tel:`, `mailto:`, situs lain keluar ke aplikasinya |

Izin yang diminta, seluruhnya: internet, status jaringan, lokasi (hanya
saat dipakai), dan biometrik. `CAMERA`, `READ_MEDIA_*`, dan
`READ_EXTERNAL_STORAGE` yang dibawa plugin **dibuang** di manifest
(`tools:node="remove"`) — dengan `CAMERA` terdeklarasi tetapi tidak
diberikan, Android justru menolak aplikasi kamera sistem.

## Membangun

```bash
export PATH=/opt/sdk/flutter/bin:$PATH
export ANDROID_HOME=/opt/sdk/android

flutter pub get
flutter test          # uji aturan inang, pintasan, dan kunci
flutter analyze
flutter build apk --release --split-per-abi --obfuscate --split-debug-info=build/simbol
flutter build appbundle --release --obfuscate --split-debug-info=build/simbol
```

Hasilnya: `build/app/outputs/flutter-apk/app-arm64-v8a-release.apk`
(dan `armeabi-v7a`, `x86_64`), serta
`build/app/outputs/bundle/release/app-release.aab`.

Tangkapan layar layar native (pengenalan, izin, kunci, pengaturan) tanpa
emulator:

```bash
flutter test test_tangkapan/tangkapan_test.dart   # → build/tangkapan/*.png
```

### APK atau AAB?

| | APK | AAB (Android App Bundle) |
|---|---|---|
| Perintah | `flutter build apk --release` | `flutter build appbundle --release` |
| Hasil | `flutter-apk/app-release.apk` | `bundle/release/app-release.aab` |
| Dipasang langsung ke ponsel | ya (sideload) | **tidak bisa** |
| Diunggah ke Play Store | tidak | ya |
| Berkunci debug | tetap berguna untuk uji coba | **tidak berguna sama sekali** |

AAB bukan format pasang — ia paket yang dipecah Play Store menjadi APK
sesuai perangkat tiap pemakai. Karena itu ia tidak dapat dicoba dengan
menyalinnya ke ponsel; untuk uji coba lapangan, pakai APK.

Dan karena satu-satunya tujuan AAB adalah Play Store, **AAB berkunci
debug tidak dapat dipakai untuk apa pun**: dipasang tidak bisa,
diunggah ditolak. CI karena itu MENGGAGALKAN build ketika AAB-nya
berkunci debug, sedangkan untuk APK ia hanya memperingatkan.

Menunjuk ke server lain tanpa menyunting kode:

```bash
flutter build apk --release --dart-define=EQOHSEE_URL=https://staging.eqohsee.id
```

> Satu APK memuat semua arsitektur (±43 MB). `--split-per-abi`
> menghasilkan tiga berkas yang masing-masing ±15–19 MB. AAB tidak
> perlu dipecah: Play Store yang mengerjakannya.

## Menerbitkan

Urutan kerjanya — dari keystore, gabung AAB, uji coba lewat APK, sampai
unggah ke Play Store dan rilis berikutnya — ada di **[RILIS.md](RILIS.md)**.

## Penandatanganan rilis

**Tanpa `android/key.properties`, build rilis memakai KUNCI DEBUG.**
APK-nya dapat dipasang untuk uji coba, tetapi Play Store menolaknya —
dan APK berkunci debug tidak dapat diperbarui oleh APK berkunci asli,
karena tanda tangannya berbeda; pemakainya harus mencopot dulu. Build
mencetak peringatan besar ketika ini terjadi.

Membuat kuncinya (**sekali saja, seumur hidup aplikasi**):

```bash
keytool -genkey -v -keystore ~/eqohsee-rilis.jks \
  -storetype PKCS12 -keyalg RSA -keysize 2048 -validity 10000 \
  -alias eqohsee
```

Lalu buat `android/key.properties`:

```properties
storePassword=<kata sandi keystore>
keyPassword=<kata sandi kunci>
keyAlias=eqohsee
storeFile=/jalur/mutlak/ke/eqohsee-rilis.jks
```

### Tiga hal yang tidak dapat diperbaiki belakangan

1. **Keystore itu tidak tergantikan.** Hilang, aplikasinya tidak akan
   pernah dapat diperbarui lagi di Play Store — harus terbit ulang
   dengan nama paket baru, dan seluruh pemakai memasang dari nol.
   Simpan cadangannya di tempat terpisah.
2. **Jangan pernah dikomit.** `key.properties`, `*.jks`, dan
   `*.keystore` sudah masuk `.gitignore`. Yang memegangnya dapat
   menerbitkan pembaruan yang diterima ponsel pemakai sebagai EQOHSEE
   yang asli.
3. **Kata sandinya bukan rahasia yang boleh dibagi di obrolan.** Pakai
   pengelola rahasia, atau `secrets` pada CI.

## Batasan yang perlu diketahui

- **Luring hanya di mode lapangan.** Lapor bahaya dan P2H dapat
  diantre tanpa sinyal; modul lain (HRIS, audit, dst.) tetap perlu
  jaringan dan menampilkan layar "Belum ada sinyal".
- **Belum ada notifikasi dorong.** Pengingat masa berlaku berkas dan
  persetujuan masih lewat surel.
- **Android saja.** iOS belum disiapkan; `flutter create` di sini
  dijalankan dengan `--platforms=android`.

## Berkas yang penting

| Berkas | Isi |
|---|---|
| `lib/main.dart` | titik masuk, alamat situs, `bukaDiDalam()`, siklus kunci |
| `lib/layar_utama.dart` | WebView, unduhan, pintasan, jembatan JS, layar tanpa sinyal |
| `lib/pengenalan.dart` | pengenalan pertama dan penjelasan izin lokasi |
| `lib/kunci.dart` | pengaturan tersimpan, `perluKunci()`, layar kunci |
| `lib/pengaturan.dart` | lembar pengaturan aplikasi |
| `lib/tema.dart` | warna dan tema, sama dengan web `/lapangan` |
| `android/app/src/main/kotlin/…/MainActivity.kt` | saluran unduhan (DownloadManager + cookie sesi) |
| `test/widget_test.dart` | uji aturan inang, pintasan, dan kunci |
| `test_tangkapan/` | render layar native menjadi PNG |
| `android/app/build.gradle.kts` | penandatanganan rilis, targetSdk 36, 16 KB |
| `android/app/src/main/AndroidManifest.xml` | izin, FileProvider kamera, `queries` |
| `playstore/grafik/` | ikon 512, feature graphic, 8 tangkapan layar listing |
| [`PLAYSTORE.md`](PLAYSTORE.md) | seluruh isian Play Console |
