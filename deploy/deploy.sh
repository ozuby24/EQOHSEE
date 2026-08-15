#!/bin/bash
# EQOHSEE VPS deploy helper.
# Jalankan DI VPS dari dalam repo: bash deploy/deploy.sh [server_name]
#
# Dari mesin lokal, tidak perlu masuk ke VPS sendiri: deploy/kirim.sh
# memeriksa, mengirim ke GitHub, lalu memanggil berkas ini lewat SSH.
#
# server_name memuat domain DAN alamat IP sekaligus. IP-nya sengaja tetap
# didaftarkan: selama DNS eqohsee.id belum menyebar — atau bila nanti
# bermasalah — situsnya tetap dapat dibuka lewat IP, sehingga tidak ada
# jendela waktu ketika aplikasi sama sekali tak terjangkau.
#
# HTTPS: jalankan dengan EQOHSEE_SSL=1 setelah DNS benar-benar mengarah
# ke VPS ini. Certbot menolak menerbitkan sertifikat bila domainnya belum
# menunjuk ke sini, jadi ini tidak dijalankan otomatis.
set -e

REPO_DIR="/var/www/EQOHSEE"
SERVER_NAME="${1:-eqohsee.id www.eqohsee.id 103.89.4.246}"
DOMAIN_UTAMA="$(echo "$SERVER_NAME" | awk '{print $1}')"

echo "==> Pulling latest code"
cd "$REPO_DIR"

# Izin berkas di server diatur dari luar Git — lihat `chmod -R 775` pada
# langkah "Setting permissions" di bawah. Chmod itu ikut mengenai berkas
# yang DILACAK Git: sebelas berkas .gitignore di dalam storage/ dan
# bootstrap/cache/ tercatat 100644 di index, dan sesudah chmod menjadi
# 775 di disk.
#
# Git membaca selisih itu sebagai perubahan isi. Akibatnya deploy yang
# berhasil membuat worktree server kotor secara permanen, dan deploy
# BERIKUTNYA ditolak kirim.sh dengan "Masih ada perubahan yang belum
# di-commit" — daftar berkas yang tidak pernah disunting siapa pun.
# Kegagalannya menuduh orang menyunting di server, padahal skrip inilah
# yang mengubahnya.
#
# core.fileMode=false membuat Git mengabaikan bit izin pada klon ini
# saja. Aman di sini justru karena izinnya memang bukan urusan Git:
# yang menentukan adalah chmod di bawah, tiap kali deploy berjalan.
git config core.fileMode false

# Bash membaca skrip sambil menjalankannya, berdasarkan posisi byte. `git pull`
# di sini dapat mengganti isi berkas yang sedang dibaca itu juga — dan bila
# panjangnya bergeser, perintah berikutnya dibaca dari tengah baris lain.
# Gejalanya galat sintaks yang tidak cocok dengan isi berkas mana pun, atau
# lebih buruk: separuh langkah terlewat tanpa satu pun pesan.
#
# Karena itu sidik jarinya dibandingkan sebelum dan sesudah pull; bila skrip
# ini ikut berubah, prosesnya diganti dengan versi baru dari awal.
SIDIK_SEBELUM="$(sha256sum "$0" | cut -d' ' -f1)"

git pull origin "$(git rev-parse --abbrev-ref HEAD)"

if [ -z "${EQOHSEE_DIMUAT_ULANG:-}" ] \
   && [ "$SIDIK_SEBELUM" != "$(sha256sum "$0" | cut -d' ' -f1)" ]; then
    echo "==> deploy.sh ikut diperbarui — menjalankan ulang versi barunya"
    export EQOHSEE_DIMUAT_ULANG=1
    exec bash "$0" "$@"
fi

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader

# Mulai dari sini kode di disk sudah kode baru, sementara singgahan config,
# rute, dan tampilan masih milik kode lama, dan migrasinya belum jalan.
# Melayani pengunjung dalam keadaan setengah itu memunculkan galat yang
# menyesatkan — bukan gejala kerusakan, hanya deploy yang belum selesai.
# `up` dipasang sebagai jebakan EXIT supaya situs tetap kembali menyala
# walau ada langkah di bawah yang gagal dan skrip berhenti mendadak.
echo "==> Memasuki mode perawatan"
php artisan down --retry=60 2>/dev/null || true
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

if ! command -v npm >/dev/null 2>&1; then
    echo "==> npm not found, installing nodejs/npm"
    apt-get install -y nodejs npm
fi

echo "==> Building frontend assets"
# `npm ci` dipakai, bukan `npm install`: `install` boleh menulis ulang
# package-lock.json (versi npm/Node yang beda antara mesin dev dan server
# bisa meregenerasi lockfile-nya sedikit berbeda meski paketnya sama),
# meninggalkan working tree kotor. `ci` memasang persis apa yang tercatat
# di lockfile dan tidak pernah mengubahnya — begitu pula, ia gagal keras
# kalau package.json dan lockfile tidak sinkron, alih-alih diam-diam
# menambal keduanya. Deploy berikutnya jadi tidak lagi bentrok dengan
# `git pull` gara-gara berkas yang sebenarnya tidak ada yang menyunting.
npm ci
npm run build

echo "==> Detecting PHP-FPM socket"
PHP_SOCK=$(ls /run/php/*.sock 2>/dev/null | head -n1)
if [ -z "$PHP_SOCK" ]; then
    echo "ERROR: no PHP-FPM socket found in /run/php/. Is php-fpm running?"
    exit 1
fi
echo "    Using socket: $PHP_SOCK"

# ── Vhost Nginx ───────────────────────────────────────────────────
# Templatnya dipilih menurut ada tidaknya sertifikat di disk, bukan menurut
# EQOHSEE_SSL. Sebelumnya vhost selalu dibangkitkan dari templat yang hanya
# mengenal `listen 80`, sementara certbot menambahkan blok 443-nya ke berkas
# yang sama — jadi setiap deploy biasa (tanpa EQOHSEE_SSL=1) menghapus HTTPS
# yang sudah jalan. Tidak ada yang mendengarkan di 443 lagi, dan peramban
# yang sudah pernah dialihkan ke https menerima ERR_CONNECTION_REFUSED.
# Karena Laravel tidak pernah dijalankan, lognya bersih dan penyebabnya tidak
# terlihat sama sekali dari sisi aplikasi.
BERKAS_VHOST="/etc/nginx/sites-available/eqohsee"
SERTIFIKAT="/etc/letsencrypt/live/$DOMAIN_UTAMA/fullchain.pem"

if [ -f "$SERTIFIKAT" ]; then
    TEMPLAT="$REPO_DIR/deploy/nginx-eqohsee-ssl.conf"
    echo "==> Installing Nginx vhost + HTTPS (server_name: $SERVER_NAME)"
else
    TEMPLAT="$REPO_DIR/deploy/nginx-eqohsee.conf"
    echo "==> Installing Nginx vhost, HTTP saja (server_name: $SERVER_NAME)"
    echo "    Belum ada sertifikat di $SERTIFIKAT."
    echo "    Terbitkan sekali dengan: EQOHSEE_SSL=1 bash deploy/deploy.sh"
fi

# Ditulis ke berkas sementara lalu diuji, bukan langsung ke tempatnya.
# `nginx -t` menguji seluruh konfigurasi sekaligus, jadi templat yang rusak
# akan menahan `systemctl restart` — dan nginx yang berhenti membawa serta
# seluruh situs, bukan hanya perubahan yang salah.
sed -e "s#__SERVER_NAME__#${SERVER_NAME}#g" \
    -e "s#__PHP_FPM_SOCK__#${PHP_SOCK}#g" \
    -e "s#__DOMAIN__#${DOMAIN_UTAMA}#g" \
    "$TEMPLAT" > "$BERKAS_VHOST.baru"

[ -f "$BERKAS_VHOST" ] && cp "$BERKAS_VHOST" "$BERKAS_VHOST.sebelumnya"
mv "$BERKAS_VHOST.baru" "$BERKAS_VHOST"

ln -sf "$BERKAS_VHOST" /etc/nginx/sites-enabled/eqohsee
rm -f /etc/nginx/sites-enabled/default

mkdir -p /var/www/html/.well-known/acme-challenge

echo "==> Testing Nginx config"
if ! nginx -t; then
    echo "!!  Konfigurasi baru ditolak nginx — vhost dikembalikan ke yang lama."
    if [ -f "$BERKAS_VHOST.sebelumnya" ]; then
        mv "$BERKAS_VHOST.sebelumnya" "$BERKAS_VHOST"
        nginx -t && systemctl reload nginx
    fi
    exit 1
fi

echo "==> Restarting services"
systemctl restart nginx
PHP_SERVICE=$(systemctl list-units --type=service --all | grep -o 'php[0-9.]*-fpm.service' | head -n1)
if [ -n "$PHP_SERVICE" ]; then
    systemctl restart "$PHP_SERVICE"
else
    systemctl restart php-fpm 2>/dev/null || true
fi

echo "==> Setting permissions"
chown -R www-data:www-data "$REPO_DIR"
chmod -R 775 "$REPO_DIR/storage" "$REPO_DIR/bootstrap/cache"

# Cadangan diambil sebelum migrasi, bukan sesudah. Migrasi yang menghapus
# atau mengubah kolom tidak punya jalan pulang: `down()` mengembalikan
# bentuk tabelnya, bukan isinya. Untuk aplikasi yang menyimpan rekaman
# audit SMKP dan ISO, isi itulah yang tidak tergantikan.
echo "==> Mencadangkan basis data"
CADANGAN="$REPO_DIR/storage/backup-otomatis"
mkdir -p "$CADANGAN"
STEMPEL="$(date +%Y%m%d-%H%M%S)"
DB_CONN="$(grep -E '^DB_CONNECTION=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"' || echo sqlite)"

case "$DB_CONN" in
    sqlite)
        DB_BERKAS="$(grep -E '^DB_DATABASE=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"' || true)"
        DB_BERKAS="${DB_BERKAS:-$REPO_DIR/database/database.sqlite}"
        if [ -f "$DB_BERKAS" ]; then
            cp "$DB_BERKAS" "$CADANGAN/db-$STEMPEL.sqlite"
            echo "    $CADANGAN/db-$STEMPEL.sqlite"
        else
            echo "    Berkas sqlite belum ada — dilewati."
        fi
        ;;
    pgsql)
        if command -v pg_dump >/dev/null 2>&1; then
            DB_NAMA="$(grep -E '^DB_DATABASE=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"')"
            DB_USER="$(grep -E '^DB_USERNAME=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"')"
            DB_HOST="$(grep -E '^DB_HOST=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"')"
            PGPASSWORD="$(grep -E '^DB_PASSWORD=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"')" \
                pg_dump -h "${DB_HOST:-127.0.0.1}" -U "$DB_USER" "$DB_NAMA" \
                > "$CADANGAN/db-$STEMPEL.sql" \
                && echo "    $CADANGAN/db-$STEMPEL.sql" \
                || echo "!!  pg_dump gagal — migrasi tetap dilanjutkan tanpa cadangan."
        else
            echo "!!  pg_dump tidak terpasang — migrasi berjalan tanpa cadangan."
        fi
        ;;
    *)
        echo "    DB_CONNECTION '$DB_CONN' tidak dikenal — dilewati."
        ;;
esac

# Sepuluh cadangan terakhir disimpan; selebihnya dibuang supaya disk VPS
# tidak diam-diam penuh oleh berkas yang tidak pernah ada yang menghapus.
ls -1t "$CADANGAN"/db-* 2>/dev/null | tail -n +11 | xargs -r rm -f

echo "==> Running database migrations"
php artisan migrate --force

echo "==> Linking public storage"
php artisan storage:link 2>/dev/null || true

echo "==> Laravel optimize"
cd "$REPO_DIR"
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── HTTPS (opsional) ──────────────────────────────────────────────
if [ "${EQOHSEE_SSL:-0}" = "1" ]; then
    echo "==> Menerbitkan sertifikat HTTPS untuk $DOMAIN_UTAMA"

    if ! command -v certbot >/dev/null 2>&1; then
        apt-get update -y && apt-get install -y certbot python3-certbot-nginx
    fi

    # `certonly --webroot`, bukan `--nginx`. Plugin nginx menyunting vhost
    # yang setiap deploy ditulis ulang dari repo, sehingga suntingannya hilang
    # pada deploy berikutnya — persis cacat yang diperbaiki di atas. Dengan
    # certonly, certbot hanya mengurus sertifikatnya; bentuk vhost sepenuhnya
    # ditentukan nginx-eqohsee-ssl.conf, dan itu ikut versi bersama kode.
    #
    # Hanya nama yang berupa domain yang diajukan; certbot menolak alamat IP.
    DOMAIN_ARGS=""
    for n in $SERVER_NAME; do
        case "$n" in
            *[a-zA-Z]*) DOMAIN_ARGS="$DOMAIN_ARGS -d $n" ;;
        esac
    done

    if certbot certonly --webroot -w /var/www/html $DOMAIN_ARGS \
        --non-interactive --agree-tos --keep-until-expiring \
        --deploy-hook "systemctl reload nginx" \
        -m "${EQOHSEE_EMAIL:-admin@${DOMAIN_UTAMA}}"; then

        # Sertifikatnya baru ada sesudah vhost dipasang di atas, jadi
        # vhost-nya dibangun ulang sekarang — kali ini dengan blok 443.
        if [ -f "$SERTIFIKAT" ]; then
            echo "==> Memasang ulang vhost dengan HTTPS"
            cp "$BERKAS_VHOST" "$BERKAS_VHOST.sebelumnya"
            sed -e "s#__SERVER_NAME__#${SERVER_NAME}#g" \
                -e "s#__PHP_FPM_SOCK__#${PHP_SOCK}#g" \
                -e "s#__DOMAIN__#${DOMAIN_UTAMA}#g" \
                "$REPO_DIR/deploy/nginx-eqohsee-ssl.conf" > "$BERKAS_VHOST"

            if nginx -t; then
                systemctl reload nginx
            else
                echo "!!  Vhost HTTPS ditolak nginx — dikembalikan ke HTTP."
                mv "$BERKAS_VHOST.sebelumnya" "$BERKAS_VHOST"
                nginx -t && systemctl reload nginx
            fi
        fi
    else
        echo "!!  Certbot gagal. Situs tetap berjalan lewat HTTP."
        echo "!!  Pastikan DNS $DOMAIN_UTAMA sudah mengarah ke server ini, lalu ulangi."
    fi
fi

# ── Pemeriksaan APP_URL ───────────────────────────────────────────
# APP_URL dipakai untuk menyusun alamat mutlak: tautan di surel, dan
# alamat aset. Bila ia masih menunjuk IP sementara situsnya sudah diakses
# lewat domain, tautan pada surel yang keluar akan mengarah ke IP —
# terlihat mencurigakan bagi penerimanya, dan gagal begitu IP berganti.
#
# Skemanya ditentukan oleh ada tidaknya sertifikat, bukan oleh EQOHSEE_SSL.
# Bendera itu hanya menyatakan "terbitkan sertifikat pada jalannya kali ini";
# memakainya di sini membuat setiap deploy biasa pada situs ber-HTTPS
# menyarankan APP_URL kembali ke http:// — saran yang justru merusak.
APP_URL_KINI="$(grep -E '^APP_URL=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"' || true)"

if [ -f "$SERTIFIKAT" ]; then
    SKEMA="https"
else
    SKEMA="http"
fi
APP_URL_HARAP="${SKEMA}://${DOMAIN_UTAMA}"

if [ "$APP_URL_KINI" != "$APP_URL_HARAP" ]; then
    echo "!!  APP_URL di .env masih '$APP_URL_KINI'."
    echo "!!  Sebaiknya '$APP_URL_HARAP' — lalu jalankan: php artisan config:cache"
fi

echo "==> Keluar dari mode perawatan"
php artisan up

echo "==> Selesai. Buka: ${SKEMA}://$DOMAIN_UTAMA"
echo "    Nama yang dilayani: $SERVER_NAME"
if [ "$SKEMA" = "http" ]; then
    echo "    HTTPS belum aktif. Sekali saja: EQOHSEE_SSL=1 bash deploy/deploy.sh"
fi
