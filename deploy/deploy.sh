#!/bin/bash
# EQOHSEE VPS deploy helper.
# Run this ON THE VPS from inside the repo: bash deploy/deploy.sh [server_name]
# server_name defaults to the VPS public IP if omitted.
set -e

REPO_DIR="/var/www/EQOHSEE"
SERVER_NAME="${1:-103.89.4.246}"

echo "==> Pulling latest code"
cd "$REPO_DIR"
git pull origin "$(git rev-parse --abbrev-ref HEAD)"

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader

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

echo "==> Done. Visit: http://$SERVER_NAME"
