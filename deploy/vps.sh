#!/usr/bin/env bash
# Shortcut untuk mengelola VPS dari Mac. Pemakaian: ./deploy/vps.sh <perintah>
#
# Setup sekali: salin deploy/vps.conf.example menjadi deploy/vps.conf lalu isi.
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
CONF="$HERE/vps.conf"
[ -f "$CONF" ] || { echo "Belum ada $CONF (salin dari vps.conf.example)."; exit 1; }
# shellcheck disable=SC1090
source "$CONF"

: "${VPS_HOST:?}" "${VPS_USER:?}"
VPS_PORT="${VPS_PORT:-22}"
APP_DIR="${APP_DIR:-/var/www/ammar-be}"
DOMAIN="${DOMAIN:-}"

run()  { ssh -p "$VPS_PORT" "$VPS_USER@$VPS_HOST" "$@"; }
runt() { ssh -t -p "$VPS_PORT" "$VPS_USER@$VPS_HOST" "$@"; }
artisan() { run "cd $APP_DIR && php artisan $*"; }

case "${1:-help}" in
  ssh)      runt ;;
  deploy)   run "bash $APP_DIR/deploy/deploy.sh" ;;
  status)
    run "uptime; echo; free -h | head -2; echo; df -h / | tail -1; echo;
         for s in nginx php8.4-fpm mysql; do printf '%-12s %s\n' \$s \$(systemctl is-active \$s); done; echo;
         cd $APP_DIR && echo \"versi: \$(git rev-parse --short HEAD) - \$(git log -1 --pretty=%s)\""
    [ -n "$DOMAIN" ] && { echo; printf 'health: '; curl -s -o /dev/null -w '%{http_code}\n' "https://$DOMAIN/up"; }
    ;;
  logs)     runt "tail -n ${2:-100} -f $APP_DIR/storage/logs/laravel-\$(date +%Y-%m-%d).log" ;;
  nginx-logs) runt "sudo tail -n 100 -f /var/log/nginx/ammar-error.log" ;;
  restart)  run "sudo systemctl restart php8.4-fpm && sudo systemctl reload nginx && echo 'PHP-FPM & Nginx dimuat ulang'" ;;
  migrate)  artisan migrate --force ;;
  seed)     artisan db:seed --force ;;
  tinker)   runt "cd $APP_DIR && php artisan tinker" ;;
  artisan)  shift; artisan "$@" ;;
  env-edit) runt "nano $APP_DIR/.env" && run "cd $APP_DIR && php artisan config:cache && sudo systemctl reload php8.4-fpm" ;;
  backup)   run "sudo /usr/local/bin/ammar-backup" ;;
  health)   curl -s -o /dev/null -w "HTTP %{http_code} (%{time_total}s)\n" "https://${DOMAIN:?isi DOMAIN di vps.conf}/up" ;;
  help|*)
    cat <<HELP
Perintah:
  ssh          masuk ke VPS
  deploy       tarik kode terbaru + migrate + cache + reload
  status       ringkasan server, service, versi, health
  logs [n]     ikuti log Laravel hari ini
  nginx-logs   ikuti log error Nginx
  restart      muat ulang PHP-FPM dan Nginx
  migrate      jalankan migrasi database
  seed         jalankan seeder (hati-hati: data awal)
  tinker       buka artisan tinker
  artisan ...  jalankan perintah artisan apa saja
  env-edit     edit .env di server lalu terapkan
  backup       buat backup database sekarang
  health       cek https://DOMAIN/up
HELP
    ;;
esac
