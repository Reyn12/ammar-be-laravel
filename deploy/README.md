# Deploy backend ke VPS

Panduan dari VPS kosong sampai backend jalan dengan HTTPS. Target: **Ubuntu 24.04**, 1 vCPU / 1-2 GB RAM.
Contoh domain: `api-ammar.pranalatech.com`.

> Skrip di folder ini ditulis dan dicek sintaksnya, tapi **belum pernah dijalankan di server sungguhan**.
> Jalankan langkah demi langkah dan baca outputnya.

## 0. Sebelum mulai
1. **DNS**: buat **A record** `api-ammar` -> IP VPS. Kalau DNS-nya di Cloudflare, set **DNS only** (awan abu-abu),
   bukan Proxied, supaya SSE tidak terputus.
2. **Akses SSH dengan kunci** dari Mac (jangan pakai password di chat):
   ```bash
   ssh-copy-id root@IP-VPS        # atau tempel public key lewat panel provider
   ```
3. **Repo di GitHub.** Kalau privat, buat *deploy key* (read-only):
   ```bash
   # di VPS, sebagai root
   ssh-keygen -t ed25519 -f /root/.ssh/ammar_deploy -N ""
   cat /root/.ssh/ammar_deploy.pub     # tempel ke GitHub: Repo > Settings > Deploy keys
   ```
   lalu pakai `REPO_URL=git@github.com:USER/ammar-be-laravel.git`.

## 1. Setup server (sekali)
Salin folder `deploy/` ke server lalu jalankan:
```bash
scp -r deploy root@IP-VPS:/root/deploy
ssh root@IP-VPS
cd /root/deploy
sudo DOMAIN=api-ammar.pranalatech.com \
     REPO_URL=git@github.com:USER/ammar-be-laravel.git \
     EMAIL=kamu@email.com \      # opsional
     SEED=1 \
     bash server-setup.sh
```
Yang dikerjakan: update sistem, Nginx + PHP 8.4-FPM + MySQL + Certbot, user `deploy`, firewall (22/80/443),
fail2ban, swap (kalau RAM < 2 GB), database + user MySQL khusus (password acak di `/root/.ammar-db-password`),
clone repo, `.env` produksi, `migrate`, seeder (kalau `SEED=1`), konfigurasi Nginx/FPM (termasuk SSE),
HTTPS, cron scheduler, dan backup harian.

`SEED=1` hanya untuk pertama kali (mengisi toko, menu, meja, dan akun demo). **Ganti password akun demo**
(`owner`, `kasir1`, `koki1`, default `password`) sebelum dipakai sungguhan.

## 2. Isi kredensial rahasia
```bash
ssh deploy@IP-VPS
nano /var/www/ammar-be/.env         # isi TRIPAY_*, FONNTE_TOKEN; cek FIREBASE_PROJECT_ID
cd /var/www/ammar-be && php artisan config:cache && sudo systemctl reload php8.4-fpm
```

## 3. Pasang shortcut di Mac
```bash
cp deploy/vps.conf.example deploy/vps.conf     # isi IP, user (deploy), domain
./deploy/vps.sh help
```
| Perintah | Fungsi |
|---|---|
| `./deploy/vps.sh deploy` | tarik kode terbaru, `composer install`, `migrate`, cache, reload |
| `./deploy/vps.sh status` | RAM, disk, status service, versi, health check |
| `./deploy/vps.sh logs` | ikuti log Laravel |
| `./deploy/vps.sh restart` | muat ulang PHP-FPM dan Nginx |
| `./deploy/vps.sh env-edit` | edit `.env` di server lalu terapkan |
| `./deploy/vps.sh backup` | backup database sekarang |
| `./deploy/vps.sh artisan ...` | perintah artisan apa saja |

## 4. Hubungkan layanan lain
- **Vercel** -> Environment Variables: `API_BASE_URL=https://api-ammar.pranalatech.com`, lalu Redeploy.
- **Tripay** (dashboard) -> URL Callback: `https://api-ammar.pranalatech.com/api/v1/payments/tripay/callback`
- **Flutter**: base URL `https://api-ammar.pranalatech.com` dan matikan mock (`mockStatus = false`).
- **QR meja**: domain Vercel (`/m/{no}?t={token}`), bukan alamat backend.

## 5. Monitoring
- **Uptime**: UptimeRobot / Better Stack (gratis), cek `https://api-ammar.pranalatech.com/up` tiap 5 menit,
  alert ke email / WhatsApp.
- **Cek manual**: `./deploy/vps.sh status`.
- (Opsional) **Netdata** untuk grafik CPU/RAM/disk: `curl https://get.netdata.cloud/kickstart.sh | sh`.

## 6. Mengunci SSH (opsional, setelah semuanya jalan)
Buka **terminal lain**, pastikan `ssh deploy@IP-VPS` berhasil dengan kunci. Baru jalankan di server:
```bash
sudo bash /var/www/ammar-be/deploy/harden-ssh.sh
```
Ini mematikan login password dan login root lewat SSH.

## Catatan teknis
- **SSE**: Nginx memakai `fastcgi_buffering off` untuk `/api/v1/stream/orders`, dan pool FPM `ammar` punya
  `pm.max_children = 20` (tiap tablet yang tersambung memegang satu proses). Naikkan kalau tablet bertambah.
- **Backup**: `/var/backups/ammar/*.sql.gz` (7 hari terakhir), jalan jam 02:00. Salin berkala ke tempat lain
  (mis. `scp` ke Mac) karena backup di server yang sama tidak menyelamatkan kalau server hilang.
- **Update**: setelah `git push`, cukup `./deploy/vps.sh deploy`.
- **Rollback cepat**: di server `cd /var/www/ammar-be && git checkout <commit-lama> && composer install --no-dev -o && php artisan config:cache && sudo systemctl reload php8.4-fpm`
  (migrasi yang sudah jalan tidak otomatis di-rollback).
