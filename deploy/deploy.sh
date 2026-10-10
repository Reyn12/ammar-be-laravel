#!/usr/bin/env bash
# Jalankan DI SERVER sebagai user "deploy": menarik kode terbaru dan menerapkannya.
# Dari Mac: ./deploy/vps.sh deploy
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/ammar-be}"
BRANCH="${BRANCH:-main}"

cd "$APP_DIR"

echo "==> Menarik kode terbaru ($BRANCH)"
git fetch --prune origin
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "==> Install dependency (tanpa dev)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Migrasi database"
php artisan migrate --force

echo "==> Cache konfigurasi"
php artisan config:cache
php artisan route:cache
php artisan event:cache

echo "==> Muat ulang PHP-FPM"
sudo systemctl reload php8.3-fpm

echo "==> Selesai. Versi sekarang: $(git rev-parse --short HEAD) - $(git log -1 --pretty=%s)"
