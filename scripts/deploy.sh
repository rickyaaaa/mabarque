#!/usr/bin/env bash
# Deploy MabarQueue di server (Hostinger). Dijalankan GitHub Actions lewat SSH dari folder aplikasi,
# atau manual:  cd ~/domains/<domain>/mabarque && bash scripts/deploy.sh
#
# Variabel opsional:
#   DEPLOY_BRANCH  cabang yang dideploy (default: main)
#   PHP_BIN        perintah PHP CLI (default: php)   — mis. /opt/alt/php83/usr/bin/php
#   COMPOSER_BIN   perintah Composer (default: composer)
set -euo pipefail

BRANCH="${DEPLOY_BRANCH:-main}"
PHP="${PHP_BIN:-php}"
COMPOSER="${COMPOSER_BIN:-composer}"

step() { printf '\n\033[1m▶ %s\033[0m\n' "$1"; }

if [ ! -f artisan ]; then
  echo "File artisan tidak ditemukan — jalankan script ini dari folder aplikasi." >&2
  exit 1
fi

if [ ! -f .env ]; then
  echo ".env belum ada di server. Buat dulu dari panduan docs/DEPLOY.md (APP_ENV=production, APP_DEBUG=false, DB_*, MAIL_*)." >&2
  exit 1
fi

step "PHP: $($PHP -r 'echo PHP_VERSION;')"
$PHP -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || { echo "Butuh PHP 8.2+ (set PHP_BIN)." >&2; exit 1; }

step "Mode maintenance"
$PHP artisan down --retry=15 || true
# Apa pun yang terjadi (termasuk gagal di tengah), situs dinyalakan lagi. Bila Laravel sendiri gagal boot,
# "artisan up" tidak bisa jalan — hapus penanda maintenance-nya langsung supaya situs tidak tertahan 503.
trap '$PHP artisan up 2>/dev/null || rm -f storage/framework/down' EXIT

step "Ambil kode terbaru ($BRANCH)"
git fetch --prune origin "$BRANCH"
# Server cermin dari repo: perubahan lokal pada file yang dilacak git ditimpa. File tak dilacak (.env, storage) aman.
git reset --hard "origin/$BRANCH"

step "Install dependensi produksi"
# --no-scripts: Hostinger menonaktifkan proc_open di PHP CLI, padahal Composer memakainya untuk menjalankan
# script "@php artisan package:discover". Script itu dijalankan langsung di bawah ini.
$COMPOSER install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts
# Cache daftar package/provider bisa masih menyebut paket dev yang barusan dihapus (mis. Laravel Pail),
# sehingga Laravel gagal boot. Hapus dulu, lalu bangun ulang dari paket yang benar-benar terpasang.
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php
$PHP artisan package:discover --ansi

step "Migrasi database"
$PHP artisan migrate --force

step "Cache konfigurasi, route, view, event"
$PHP artisan optimize:clear
$PHP artisan optimize

step "Symlink storage & restart queue"
$PHP artisan storage:link 2>/dev/null || true
$PHP artisan queue:restart

step "Selesai — commit $(git rev-parse --short HEAD)"
