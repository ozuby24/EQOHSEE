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
git pull origin "$(git rev-parse --abbrev-ref HEAD)"

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

echo "==> Installing Nginx vhost (server_name: $SERVER_NAME)"
sed -e "s#__SERVER_NAME__#${SERVER_NAME}#g" \
    -e "s#__PHP_FPM_SOCK__#${PHP_SOCK}#g" \
    "$REPO_DIR/deploy/nginx-eqohsee.conf" > /etc/nginx/sites-available/eqohsee

ln -sf /etc/nginx/sites-available/eqohsee /etc/nginx/sites-enabled/eqohsee
rm -f /etc/nginx/sites-enabled/default

echo "==> Testing Nginx config"
nginx -t

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

    # --nginx menyunting vhost yang barusan dipasang dan menambahkan
    # blok 443 beserta pengalihan dari 80. Hanya nama yang berupa domain
    # yang diajukan; certbot menolak alamat IP.
    DOMAIN_ARGS=""
    for n in $SERVER_NAME; do
        case "$n" in
            *[a-zA-Z]*) DOMAIN_ARGS="$DOMAIN_ARGS -d $n" ;;
        esac
    done

    certbot --nginx $DOMAIN_ARGS --non-interactive --agree-tos --redirect \
        -m "${EQOHSEE_EMAIL:-admin@${DOMAIN_UTAMA}}" || {
        echo "!!  Certbot gagal. Situs tetap berjalan lewat HTTP."
        echo "!!  Pastikan DNS $DOMAIN_UTAMA sudah mengarah ke server ini, lalu ulangi."
    }
fi

# ── Pemeriksaan APP_URL ───────────────────────────────────────────
# APP_URL dipakai untuk menyusun alamat mutlak: tautan di surel, dan
# alamat aset. Bila ia masih menunjuk IP sementara situsnya sudah diakses
# lewat domain, tautan pada surel yang keluar akan mengarah ke IP —
# terlihat mencurigakan bagi penerimanya, dan gagal begitu IP berganti.
APP_URL_KINI="$(grep -E '^APP_URL=' "$REPO_DIR/.env" | cut -d= -f2- | tr -d '"' || true)"
APP_URL_HARAP="https://${DOMAIN_UTAMA}"

if [ "${EQOHSEE_SSL:-0}" != "1" ]; then APP_URL_HARAP="http://${DOMAIN_UTAMA}"; fi

if [ "$APP_URL_KINI" != "$APP_URL_HARAP" ]; then
    echo "!!  APP_URL di .env masih '$APP_URL_KINI'."
    echo "!!  Sebaiknya '$APP_URL_HARAP' — lalu jalankan: php artisan config:cache"
fi

echo "==> Keluar dari mode perawatan"
php artisan up

echo "==> Selesai. Buka: http://$DOMAIN_UTAMA"
echo "    Nama yang dilayani: $SERVER_NAME"
