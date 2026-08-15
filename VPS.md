# Menerbitkan EQOHSEE ke VPS

Dua berkas bekerja berpasangan:

| Berkas | Berjalan di | Tugas |
|---|---|---|
| `deploy/kirim.sh` | mesin lokal | memeriksa, mengirim ke GitHub, memanggil `deploy.sh` lewat SSH |
| `deploy/deploy.sh` | VPS | menarik kode, memasang, migrasi, menyalakan ulang layanan |

Sehari-hari cukup satu perintah dari mesin lokal:

```bash
bash deploy/kirim.sh
```

Penyiapan di bawah hanya dikerjakan sekali.

---

## 1. Pengaturan di mesin lokal

```bash
cp deploy/vps.conf.example deploy/vps.conf
```

Sunting `deploy/vps.conf` seperlunya:

```bash
: "${VPS_HOST:=root@103.89.4.246}"   # pengguna@alamat
: "${VPS_PORT:=22}"                  # porta SSH
: "${VPS_DIR:=/var/www/EQOHSEE}"     # letak repo di VPS
: "${VPS_SSL:=0}"                    # 1 untuk sekalian menerbitkan HTTPS
```

Berkas ini diabaikan Git dan tidak akan ikut terkirim. Ia **tidak boleh** memuat
kata sandi — masuknya memakai kunci SSH.

Variabel lingkungan mengalahkan isi berkas, berguna untuk server lain:

```bash
VPS_HOST=deploy@staging.eqohsee.id bash deploy/kirim.sh
```

---

## 2. Kunci SSH

`kirim.sh` menolak masuk dengan kata sandi. Itu disengaja: perintah yang
menunggu ketikan sandi tidak dapat dijalankan otomatis, dan sandi yang
disimpan agar tidak perlu diketik justru berakhir tersimpan di tempat yang
tidak semestinya.

Di mesin lokal:

```bash
ssh-keygen -t ed25519            # lewati bila sudah punya kunci
ssh-copy-id -p 22 root@103.89.4.246
```

Uji — harus masuk tanpa ditanya apa pun:

```bash
ssh root@103.89.4.246 "echo berhasil"
```

Pengguna Windows: jalankan dari **Git Bash** atau **WSL**, bukan CMD. Bila
`ssh-copy-id` tidak tersedia:

```bash
cat ~/.ssh/id_ed25519.pub | ssh root@103.89.4.246 \
  "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys"
```

---

## 3. Penyiapan VPS

Dikerjakan sekali, masuk ke VPS lewat SSH.

### 3a. Perangkat yang dibutuhkan

PHP **8.4 atau lebih baru** — ini keharusan, bukan anjuran. `composer.json`
menuntut `^8.4`, dan komponen Symfony yang dipakai menolak berjalan di bawah
8.4.1. Periksa dengan `php -v`.

```bash
apt update
apt install -y nginx git unzip curl \
  php8.4-fpm php8.4-cli php8.4-mbstring php8.4-xml php8.4-curl \
  php8.4-zip php8.4-gd php8.4-bcmath php8.4-sqlite3 php8.4-pgsql

curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs
```

### 3b. Mengambil repo

```bash
mkdir -p /var/www
git clone https://github.com/ozuby24/EQOHSEE.git /var/www/EQOHSEE
cd /var/www/EQOHSEE
```

Untuk repo privat, VPS perlu jalan masuk sendiri ke GitHub. Yang paling
sempit haknya adalah **deploy key** — hanya berlaku untuk satu repo, dan
cukup hak baca:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/github_eqohsee -N ""
cat ~/.ssh/github_eqohsee.pub
```

Tempelkan hasilnya di **Settings → Deploy keys → Add deploy key** pada repo
GitHub, biarkan *Allow write access* tidak dicentang. Lalu:

```bash
cat >> ~/.ssh/config <<'EOF'
Host github.com
    IdentityFile ~/.ssh/github_eqohsee
    IdentitiesOnly yes
EOF

cd /var/www/EQOHSEE
git remote set-url origin git@github.com:ozuby24/EQOHSEE.git
git fetch origin          # harus berhasil tanpa ditanya apa pun
```

### 3c. Berkas `.env`

`.env` tidak pernah dikirim dari mesin lokal dan tidak pernah masuk repo.
Ia dibuat sekali di VPS dan tinggal di sana.

```bash
cd /var/www/EQOHSEE
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
nano .env
```

Yang wajib disesuaikan:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://eqohsee.id

# Basis data. Bawaan sqlite; untuk pgsql isi juga DB_HOST/DB_PORT/
# DB_DATABASE/DB_USERNAME/DB_PASSWORD.
DB_CONNECTION=sqlite

# SSR dibiarkan mati kecuali layanan SSR-nya memang dijalankan.
INERTIA_SSR_ENABLED=false
```

`APP_DEBUG=false` bukan sekadar kerapian: bila menyala, halaman galat
menampilkan isi `.env` — termasuk kredensial basis data — kepada siapa pun
yang berhasil memicu galat.

Bila memakai sqlite:

```bash
touch database/database.sqlite
chown www-data:www-data database/database.sqlite
```

### 3d. Hak akses

```bash
chown -R www-data:www-data /var/www/EQOHSEE
chmod -R 775 /var/www/EQOHSEE/storage /var/www/EQOHSEE/bootstrap/cache
```

### 3e. Menjalankan sekali dari VPS

```bash
cd /var/www/EQOHSEE
bash deploy/deploy.sh
```

Langkah ini memasang vhost Nginx, menjalankan migrasi, dan menyalakan ulang
layanan. Setelah ini situs sudah bisa dibuka lewat HTTP.

---

## 4. HTTPS

Jalankan **setelah** DNS benar-benar mengarah ke VPS. Certbot menolak
menerbitkan sertifikat bila domainnya belum menunjuk ke sana, dan percobaan
gagal yang terlalu sering akan kena pembatasan laju dari Let's Encrypt.

Periksa dulu:

```bash
dig +short eqohsee.id        # harus mengembalikan alamat VPS
```

Lalu setel `VPS_SSL=1` di `deploy/vps.conf`, atau sekali jalan:

```bash
VPS_SSL=1 bash deploy/kirim.sh
```

Jangan lupa ubah `APP_URL` di `.env` VPS menjadi `https://…` lalu
`php artisan config:cache` — `deploy.sh` akan mengingatkan bila keduanya
belum sejalan. Ini bukan soal rapi: `APP_URL` dipakai menyusun tautan di
surel yang keluar, dan tautan yang menunjuk alamat IP terlihat mencurigakan
bagi penerimanya.

---

## 5. Pemakaian sehari-hari

```bash
bash deploy/kirim.sh              # kirim cabang yang sedang aktif
bash deploy/kirim.sh main         # kirim cabang tertentu
bash deploy/kirim.sh --kering     # tampilkan rencananya, tanpa mengubah apa pun
```

**Untuk pemakaian pertama, jalankan `--kering` lebih dulu.** Ia menempuh
seluruh pemeriksaan dan menunjukkan apa yang akan dikerjakan, tanpa mengirim
atau mengubah apa pun.

Pilihan lain:

| Pilihan | Guna |
|---|---|
| `--tanpa-tes` | lewati uji lokal, untuk perbaikan mendesak |
| `--paksa` | teruskan meski worktree lokal atau repo VPS masih kotor |
| `--kering` | tampilkan rencana, tidak mengubah apa pun |

### Yang ditolak sebelum apa pun terkirim

- worktree lokal masih kotor
- `.env` atau `database.sqlite` terlanjur terlacak Git
- uji lokal tidak lulus
- SSH belum bisa masuk tanpa kata sandi
- repo di VPS punya suntingan langsung yang belum kembali ke repo

Yang terakhir paling mudah terlewat. `deploy.sh` menyamakan isi VPS lewat
`git reset --hard`, yang membuang suntingan semacam itu tanpa jejak —
padahal justru suntingan langsung di server biasanya berisi perbaikan
mendesak yang belum sempat masuk repo. Selamatkan dulu, atau buang dengan
sengaja lewat `--paksa`.

---

## 6. Bila ada yang salah

**`Tidak dapat masuk ke … tanpa kata sandi`** — kunci SSH belum terpasang.
Ulangi bagian 2, lalu uji `ssh root@103.89.4.246 "echo berhasil"`.

**`Direktori '/var/www/EQOHSEE' tidak ada atau bukan repo Git`** — bagian 3b
belum dikerjakan, atau `VPS_DIR` salah.

**`VPS berada di … bukan …`** — `deploy.sh` berhenti di tengah. Masuk ke VPS
dan jalankan `bash deploy/deploy.sh` langsung untuk melihat galatnya utuh.

**Situs menampilkan 502** — PHP-FPM mati: `systemctl status php8.4-fpm`.

**Situs tersangkut di halaman perawatan** — `deploy.sh` berhenti sebelum
sempat menyalakan kembali. Pulihkan dengan:

```bash
cd /var/www/EQOHSEE && php artisan up
```

### Mengembalikan basis data

Setiap deploy menyimpan cadangan sebelum migrasi berjalan, sepuluh yang
terakhir disimpan:

```bash
ls -lt /var/www/EQOHSEE/storage/backup-otomatis/
```

Memulihkan (sqlite):

```bash
cd /var/www/EQOHSEE
php artisan down
cp storage/backup-otomatis/db-20260814-093000.sqlite database/database.sqlite
chown www-data:www-data database/database.sqlite
php artisan up
```

### Kembali ke versi sebelumnya

```bash
git log --oneline -10                    # di mesin lokal, cari commit yang sehat
git checkout -b pulihkan <commit>
bash deploy/kirim.sh pulihkan
```

Perlu diingat: mundurnya kode tidak memundurkan basis data. Bila commit yang
ditinggalkan membawa migrasi, pulihkan juga cadangannya.
