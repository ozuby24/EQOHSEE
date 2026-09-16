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
| Tombol kembali perangkat | menyusuri riwayat halaman, bukan langsung menutup aplikasi |
| Unggah berkas | kamera dan galeri, untuk laporan bahaya dan inspeksi |
| Unduhan | diserahkan ke pengelola unduhan sistem (CSV, lampiran, cetak) |
| Layar tanpa jaringan | pesan yang jujur beserta tombol coba lagi |
| Tautan luar | `tel:`, `mailto:`, situs lain keluar ke aplikasinya |

## Membangun

```bash
export PATH=/opt/sdk/flutter/bin:$PATH
export ANDROID_HOME=/opt/sdk/android

flutter pub get
flutter test          # uji aturan inang
flutter analyze
flutter build apk --release
```

Hasilnya: `build/app/outputs/flutter-apk/app-release.apk`

Menunjuk ke server lain tanpa menyunting kode:

```bash
flutter build apk --release --dart-define=EQOHSEE_URL=https://staging.eqohsee.id
```

> Satu APK memuat semua arsitektur (±43 MB). `--split-per-abi`
> menghasilkan tiga berkas yang masing-masing ±15 MB.

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

- **Perlu jaringan.** Tidak ada mode luring; di site tanpa sinyal,
  aplikasi menampilkan layar "tidak ada sambungan". Laporan bahaya
  dari lapangan mati sinyal belum dapat diantre.
- **Belum ada notifikasi dorong.** Pengingat masa berlaku berkas dan
  persetujuan masih lewat surel.
- **Android saja.** iOS belum disiapkan; `flutter create` di sini
  dijalankan dengan `--platforms=android`.

## Berkas yang penting

| Berkas | Isi |
|---|---|
| `lib/main.dart` | seluruh aplikasi, termasuk `bukaDiDalam()` |
| `test/widget_test.dart` | uji aturan inang |
| `android/app/build.gradle.kts` | penandatanganan rilis |
| `android/app/src/main/AndroidManifest.xml` | izin dan `queries` |
