#!/usr/bin/env bash
# Setup awal server Ubuntu 24.04 KOSONG. Jalankan SEKALI sebagai root (atau sudo):
#
#   sudo DOMAIN=api-ammar.pranalatech.com \
#        REPO_URL=git@github.com:USER/ammar-be-laravel.git \
#        EMAIL=kamu@email.com \
#        bash server-setup.sh
#
# Opsional: SEED=1 (isi data awal: toko, menu, meja, akun demo), BRANCH=main
# Aman diulang: langkah yang sudah selesai dilewati.
set -euo pipefail

: "${DOMAIN:?isi DOMAIN, mis. api-ammar.pranalatech.com}"
: "${REPO_URL:?isi REPO_URL}"
: "${EMAIL:?isi EMAIL untuk sertifikat HTTPS}"
BRANCH="${BRANCH:-main}"
SEED="${SEED:-0}"
APP_DIR=/var/www/ammar-be
APP_USER=deploy
DB_NAME=ammar_be_laravel
DB_USER=ammar
HERE="$(cd "$(dirname "$0")" && pwd)"

[ "$(id -u)" -eq 0 ] || { echo "Jalankan sebagai root (pakai sudo)."; exit 1; }

echo "==> [1/9] Paket sistem"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y
apt-get install -y nginx mysql-server git unzip curl ufw fail2ban unattended-upgrades \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
  php8.3-bcmath php8.3-intl composer certbot python3-certbot-nginx
timedatectl set-timezone Asia/Jakarta

echo "==> [2/9] User deploy"
if ! id "$APP_USER" >/dev/null 2>&1; then
  adduser --disabled-password --gecos "" "$APP_USER"
  # pakai kunci SSH yang sama dengan akun yang sedang login (root/ubuntu)
  if [ -f /root/.ssh/authorized_keys ]; then
    install -d -m 700 -o "$APP_USER" -g "$APP_USER" "/home/$APP_USER/.ssh"
    install -m 600 -o "$APP_USER" -g "$APP_USER" /root/.ssh/authorized_keys "/home/$APP_USER/.ssh/authorized_keys"
  fi
fi
# deploy hanya boleh me-reload service tertentu tanpa password
cat > /etc/sudoers.d/ammar-deploy <<SUDO
$APP_USER ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm, /usr/bin/systemctl restart php8.3-fpm, /usr/bin/systemctl reload nginx, /usr/bin/systemctl restart nginx, /usr/bin/systemctl status php8.3-fpm, /usr/bin/systemctl status nginx, /usr/bin/systemctl status mysql
SUDO
chmod 440 /etc/sudoers.d/ammar-deploy

echo "==> [3/9] Firewall (22, 80, 443) dan fail2ban"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
systemctl enable --now fail2ban
dpkg-reconfigure -f noninteractive unattended-upgrades

echo "==> [4/9] Swap kalau RAM kecil"
MEM_MB=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
if [ "$MEM_MB" -lt 2048 ] && [ ! -f /swapfile ]; then
  fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "==> [5/9] MySQL: database dan user khusus"
DB_PASS_FILE=/root/.ammar-db-password
if [ ! -f "$DB_PASS_FILE" ]; then
  openssl rand -base64 24 | tr -d '=+/' > "$DB_PASS_FILE"
  chmod 600 "$DB_PASS_FILE"
fi
DB_PASS="$(cat "$DB_PASS_FILE")"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "==> [6/9] Kode aplikasi"
install -d -o "$APP_USER" -g www-data /var/www
if [ ! -d "$APP_DIR/.git" ]; then
  sudo -u "$APP_USER" git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
fi
if [ ! -f "$APP_DIR/.env" ]; then
  sed "s#__DOMAIN__#$DOMAIN#g" "$HERE/env.production.example" > "$APP_DIR/.env"
  sed -i "s#^DB_PASSWORD=.*#DB_PASSWORD=$DB_PASS#" "$APP_DIR/.env"
  chown "$APP_USER":www-data "$APP_DIR/.env"
  chmod 640 "$APP_DIR/.env"
fi
cd "$APP_DIR"
sudo -u "$APP_USER" composer install --no-dev --optimize-autoloader --no-interaction
grep -q '^APP_KEY=base64' .env || sudo -u "$APP_USER" php artisan key:generate --force
chown -R "$APP_USER":www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
sudo -u "$APP_USER" php artisan migrate --force
if [ "$SEED" = "1" ]; then sudo -u "$APP_USER" php artisan db:seed --force; fi
sudo -u "$APP_USER" php artisan config:cache
sudo -u "$APP_USER" php artisan route:cache
sudo -u "$APP_USER" php artisan event:cache

echo "==> [7/9] PHP-FPM dan Nginx"
install -m 644 "$HERE/php-fpm-ammar.conf" /etc/php/8.3/fpm/pool.d/ammar.conf
rm -f /etc/php/8.3/fpm/pool.d/www.conf
sed "s#__DOMAIN__#$DOMAIN#g" "$HERE/nginx-ammar.conf.template" > /etc/nginx/sites-available/ammar
ln -sf /etc/nginx/sites-available/ammar /etc/nginx/sites-enabled/ammar
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl restart php8.3-fpm
systemctl reload nginx

echo "==> [8/9] HTTPS (Lets Encrypt)"
SERVER_IP="$(curl -s -4 ifconfig.me || true)"
DNS_IP="$(getent ahostsv4 "$DOMAIN" | awk '{print $1; exit}' || true)"
if [ -n "$DNS_IP" ] && [ "$DNS_IP" = "$SERVER_IP" ]; then
  certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect
else
  echo "!! DNS $DOMAIN ($DNS_IP) belum mengarah ke server ini ($SERVER_IP)."
  echo "   Buat A record dulu, lalu jalankan: sudo certbot --nginx -d $DOMAIN -m $EMAIL --agree-tos --redirect"
fi

echo "==> [9/9] Cron (scheduler) dan backup harian"
cat > /etc/cron.d/ammar <<CRON
* * * * * $APP_USER cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1
0 2 * * * root /usr/local/bin/ammar-backup >> /var/log/ammar-backup.log 2>&1
CRON
chmod 644 /etc/cron.d/ammar
install -m 755 "$HERE/backup.sh" /usr/local/bin/ammar-backup

echo
echo "Selesai. Cek: curl -I https://$DOMAIN/up"
echo "Isi kredensial Tripay/Fonnte di $APP_DIR/.env, lalu jalankan: sudo -u $APP_USER php artisan config:cache"
