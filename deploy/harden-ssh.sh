#!/usr/bin/env bash
# OPSIONAL, jalankan setelah kamu BERHASIL login sebagai "deploy" dengan kunci SSH
# (dari terminal lain!). Mematikan login password dan login root lewat SSH.
# Kalau terkunci, masuk lewat console web provider dan hapus /etc/ssh/sshd_config.d/99-ammar.conf
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Jalankan sebagai root (sudo)."; exit 1; }

[ -s /home/deploy/.ssh/authorized_keys ] || { echo "Kunci SSH user deploy belum ada. Batal."; exit 1; }

cat > /etc/ssh/sshd_config.d/99-ammar.conf <<CONF
PasswordAuthentication no
PermitRootLogin no
CONF

sshd -t
systemctl reload ssh
echo "Selesai. JANGAN tutup sesi ini; tes login baru dari terminal lain dulu."
