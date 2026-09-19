# Menerbitkan EQOHSEE

Urutan kerja dari keystore sampai aplikasi terpasang di ponsel orang.

---

## 0. Simpan keystore lebih dulu

Sebelum apa pun. Berkasnya `eqohsee-rilis.jks`, alias `eqohsee`.

- Simpan di pengelola sandi perusahaan (1Password, Bitwarden, Vaultwarden)
  bersama sandinya — berkas dan sandi di tempat yang sama, karena yang
  satu tidak berguna tanpa yang lain.
- Buat satu cadangan di tempat **berbeda**: brankas, atau drive
  perusahaan yang dibatasi aksesnya.
- **Jangan** taruh di repo, Google Drive pribadi, atau lampiran chat
  yang bisa dibuka seluruh tim.

### Seberapa gawat kalau hilang

Tergantung jalur distribusinya, dan ini sering disalahpahami:

| Jalur | Kalau keystore hilang |
|---|---|
| **Play Store** (dengan Play App Signing) | **Dapat dipulihkan.** Kunci ini hanya *upload key*; kunci penanda tangan aplikasi yang sesungguhnya dipegang Google. Minta reset upload key lewat dukungan Play Console. |
| **Bagi APK langsung** (sideload, tanpa Play) | **Tidak dapat dipulihkan.** Pembaruan harus bertanda tangan sama; tanpa kuncinya, tiap pemakai wajib mencopot dan memasang ulang dari nol. |

Jadi: kalau distribusinya lewat Play Store, kehilangan kunci ini
merepotkan tetapi tidak fatal. Kalau APK-nya dibagikan langsung ke
ponsel pekerja — dan di site tambang itu biasa — kehilangannya fatal.

---

## 1. Menggabungkan AAB yang dipecah

Berkas AAB dikirim dalam dua bagian karena batas ukuran lampiran.

**Linux / macOS**
```bash
cat EQOHSEE-1.0.0.aab.bagian00 EQOHSEE-1.0.0.aab.bagian01 > EQOHSEE-1.0.0.aab
sha256sum EQOHSEE-1.0.0.aab
```

**Windows (Command Prompt)**
```cmd
copy /b EQOHSEE-1.0.0.aab.bagian00+EQOHSEE-1.0.0.aab.bagian01 EQOHSEE-1.0.0.aab
certutil -hashfile EQOHSEE-1.0.0.aab SHA256
```

Hasilnya **harus** persis:

```
a06914be7584f6e68c3e8cc8991b92da882eae2701ff52bff339646ab57c9c2d
```

Ukuran: 38.986.869 byte.

Kalau tidak cocok, satu bagian rusak atau belum terunduh penuh. **Jangan
diunggah** — Play Console akan menolaknya dengan pesan yang tidak
menyinggung sebab sebenarnya, dan waktu habis untuk mencari di tempat
yang salah.

---

## 2. Uji coba dulu lewat APK

AAB tidak dapat dipasang ke ponsel. Untuk mencoba aplikasinya, pakai APK.

1. Kirim `EQOHSEE-1.0.0-arm64.apk` ke ponsel penguji (WhatsApp, Drive,
   kabel — bebas).
2. Di ponsel: **Setelan → Aplikasi → Akses khusus → Instal aplikasi
   tidak dikenal** → izinkan aplikasi yang dipakai membuka berkasnya.
3. Buka berkas APK-nya, tekan **Instal**.

> Pakai `arm64` untuk hampir semua ponsel sejak 2017. `arm32` hanya
> untuk perangkat lama. Salah pilih akan ditolak saat pasang, tidak
> merusak apa pun.

**Penting:** APK yang bertanda tangan kunci debug (yang dibagikan
sebelum keystore ini ada) **tidak dapat ditimpa** oleh APK ini — tanda
tangannya berbeda. Copot dulu yang lama.

Yang perlu dicoba di lapangan, bukan di kantor:

- [ ] Masuk dengan akun sungguhan
- [ ] Tombol kembali menyusuri halaman, bukan langsung keluar
- [ ] Unggah foto pada Hazard Report — dari kamera **dan** dari galeri
- [ ] Unduh satu berkas (ekspor CSV atau lampiran dokumen)
- [ ] Matikan data seluler → layar "tidak ada sambungan" muncul, tombol
      Coba lagi bekerja
- [ ] Tekan nomor telepon di halaman → membuka aplikasi telepon

---

## 3. Mengunggah ke Play Store

### 3.1 Akun pengembang

Sekali saja: daftar di [play.google.com/console](https://play.google.com/console),
biaya USD 25 sekali seumur akun. Verifikasi identitas perusahaan makan
waktu beberapa hari — mulai lebih awal daripada yang dikira perlu.

### 3.2 Buat aplikasinya

**Create app** → nama tampilan, bahasa bawaan, tipe **App**, dan
**Free**. Nama paketnya sudah tetap: `id.eqohsee.eqohsee`.

### 3.3 Isi dulu yang wajib sebelum boleh rilis

Play Console menahan rilis sampai semuanya hijau. Yang paling sering
menahan proyek seperti ini:

- **Kebijakan privasi** — wajib URL publik. Aplikasi ini memuat data
  pekerja (nama, NIK, hasil MCU), jadi ini bukan formalitas.
- **Data safety** — deklarasikan apa yang dikumpulkan. Jawab apa adanya:
  aplikasi mengirim data yang diketik pemakai ke server EQOHSEE.
- **Content rating** — kuesioner singkat.
- **Target audience** — dewasa, bukan anak-anak.
- **App access** — aplikasi ini **wajib login**, jadi Play meminta akun
  uji. Sediakan satu akun demo beserta sandinya di kolom itu, kalau
  tidak peninjau hanya melihat halaman masuk dan menolaknya.

### 3.4 Unggah

**Testing → Internal testing** dulu, jangan langsung Production.

1. **Create new release**
2. Unggah `EQOHSEE-1.0.0.aab`
3. Saat ditanya soal penandatanganan, pilih **Let Google manage my app
   signing key** (Play App Signing). Keystore Anda menjadi *upload key*.
4. Isi catatan rilis
5. **Review release** → **Start rollout**

Bagikan tautan penguji internal ke beberapa orang lebih dulu. Baru
setelah itu naikkan ke Closed testing, lalu Production.

---

## 4. Rilis berikutnya: naikkan nomornya

Play Console menolak unggahan dengan `versionCode` yang sama. Nomornya
diambil dari satu tempat: `pubspec.yaml`.

```yaml
version: 1.0.1+2
#        ^^^^^ versionName — yang dilihat orang
#              ^ versionCode — yang diperiksa Play, WAJIB naik
```

Angka sesudah `+` harus lebih besar dari rilis sebelumnya, selalu.
Lupa menaikkannya adalah penolakan paling sering, dan pesannya jelas.

Lalu:

```bash
flutter build appbundle --release --obfuscate --split-debug-info=build/simbol
```

---

## 5. Membangun lewat CI (disarankan)

Supaya tidak perlu Flutter di laptop, dan kuncinya tidak berpindah-pindah.

### 5.1 Ubah keystore jadi teks

```bash
base64 -w0 eqohsee-rilis.jks          # Linux
base64 -i eqohsee-rilis.jks | tr -d '\n'   # macOS
```

Salin hasilnya. Jangan tempelkan ke chat, tiket, atau berkas mana pun —
langsung ke kolom secret.

### 5.2 Pasang secrets

GitHub → repo → **Settings → Secrets and variables → Actions → New
repository secret**. Empat buah:

| Nama | Isi |
|---|---|
| `ANDROID_KEYSTORE_BASE64` | hasil base64 tadi |
| `ANDROID_STORE_PASSWORD` | sandi keystore |
| `ANDROID_KEY_PASSWORD` | sandi kunci (sama) |
| `ANDROID_KEY_ALIAS` | `eqohsee` |

### 5.3 Jalankan

**Actions → Bangun APK dan AAB → Run workflow.** Atau dorong tag versi:

```bash
git tag v1.0.1 && git push origin v1.0.1
```

Hasilnya diunduh dari bagian **Artifacts** pada halaman run: satu
artefak berisi ketiga APK, satu lagi berisi AAB.

CI **menggagalkan** build kalau AAB-nya ternyata berkunci debug —
artinya secret-nya belum terpasang benar. Untuk APK ia hanya
memperingatkan, karena APK berkunci debug masih berguna untuk uji coba.

---

## 6. Memeriksa tanda tangan sebuah berkas

Kalau ragu sebuah APK/AAB memakai kunci yang benar:

```bash
# APK
apksigner verify --print-certs aplikasi.apk | grep "certificate DN"

# AAB (pakai jarsigner, bukan apksigner)
jarsigner -verify -verbose:summary -certs aplikasi.aab | grep "Signed by"
```

Yang benar memulangkan:

```
CN=EQOHSEE, OU=HSE, O=EQOHSEE, L=Makassar, ST=Sulawesi Selatan, C=ID
```

Sidik jari SHA-256 kuncinya:

```
14:E5:91:F2:CE:87:E1:28:5B:46:F3:39:0E:D1:B2:49:C4:C3:8A:8A:13:88:7D:B1:83:D5:C0:AA:5C:2F:8D:B3
```

Kalau yang muncul `CN=Android Debug`, berkas itu **bukan** yang siap
terbit — `key.properties` tidak terbaca saat dibangun.

---

## 7. Mengganti sandi keystore

Sandinya dapat diganti tanpa mengubah kuncinya. Identitas penandatanganan
tetap sama, aplikasi yang sudah terbit tidak terpengaruh, dan sandi lama
berhenti berlaku:

```bash
keytool -storepasswd -keystore eqohsee-rilis.jks
keytool -keypasswd -alias eqohsee -keystore eqohsee-rilis.jks
```

Kerjakan ini kalau sandinya pernah lewat kanal yang tidak Anda
percayai — chat, surel, tiket.

Jangan lupa perbarui `key.properties` dan secrets CI sesudahnya.
