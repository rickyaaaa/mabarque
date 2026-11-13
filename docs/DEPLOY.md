# Deploy & CI/CD ke Hostinger

Alur otomatis (`.github/workflows/ci-cd.yml`):

1. **Setiap push / pull request** → GitHub Actions menjalankan seluruh test di PHP 8.2 dan 8.3.
2. **Push ke `main` dan test lolos** → GitHub login ke Hostinger lewat SSH dan menjalankan `scripts/deploy.sh`:
   maintenance mode → `git reset --hard origin/main` → `composer install --no-dev` → `migrate --force` →
   cache config/route/view → `storage:link` → restart queue → situs nyala lagi (juga bila ada langkah yang gagal).
3. Selama secret SSH belum diisi, job deploy **dilewati dengan peringatan** (bukan gagal).

Deploy bisa juga dipicu manual: tab **Actions → CI/CD → Run workflow**, atau di server: `bash scripts/deploy.sh`.

---

## Setup sekali di Hostinger

### 1. PHP & SSH
- hPanel → **Advanced → PHP Configuration**: pilih **PHP 8.2 atau 8.3**.
- hPanel → **Advanced → SSH Access**: aktifkan SSH, catat **host (IP)**, **port** (biasanya `65002`), dan **username**.
- Cek dari terminal: `ssh -p 65002 USER@HOST "php -v && composer -V"`.
  Jika `php -v` di bawah 8.2, set `PHP_BIN` (lihat bagian Masalah umum).

### 2. Kunci SSH khusus deploy
Di komputer kamu (bukan di server):

```bash
ssh-keygen -t ed25519 -C "github-actions-mabarqueue" -f mabarqueue_deploy -N ""
```

- Isi `mabarqueue_deploy.pub` → hPanel **SSH Access → SSH Keys → Add SSH key**.
- Isi `mabarqueue_deploy` (kunci privat) → secret GitHub `SSH_PRIVATE_KEY` (langkah 5). Setelah itu hapus file kunci privat dari komputer.

### 3. Folder aplikasi & document root
Folder aplikasi harus berupa **clone git** dari repo ini (fitur hPanel **Git** atau `git clone` lewat SSH).

Pilihan yang disarankan — aplikasi di luar `public_html`:

```bash
cd ~/domains/DOMAINKAMU
git clone https://github.com/rickyaaaa/mabarque.git mabarque
rm -rf public_html && ln -s mabarque/public public_html
```

Jika repo sudah terpasang langsung di `public_html` (lewat fitur Git hPanel), itu juga jalan: `.htaccess` di root repo
meneruskan semua request ke `public/` dan memblokir `.env`, `vendor/`, `storage/`, dll.

> Jika fitur Git hPanel memakai **auto-deployment**, matikan — deploy sudah dijalankan GitHub Actions
> (yang juga menjalankan composer & migrasi). Dua deploy bersamaan bisa saling tabrakan.

### 4. Database, `.env`, dan cron
- hPanel → **Databases → MySQL**: buat database + user, catat namanya.
- Di folder aplikasi buat `.env` (sekali saja, **tidak** di-commit):

```bash
cp .env.example .env && nano .env
php artisan key:generate
```

Nilai penting untuk produksi:

```dotenv
APP_NAME=MabarQueue
APP_ENV=production
APP_DEBUG=false                  # WAJIB false: kalau true, error menampilkan stack trace & path server
APP_URL=https://DOMAINKAMU
APP_LOCALE=id
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456_mabarqueue
DB_USERNAME=u123456_mabarqueue
DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
SANCTUM_STATEFUL_DOMAINS=DOMAINKAMU,www.DOMAINKAMU

MAIL_MAILER=smtp                 # email reset password & persetujuan beta
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=noreply@DOMAINKAMU
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@DOMAINKAMU
MAIL_FROM_NAME="MabarQueue"
```

- Jalankan deploy pertama manual: `bash scripts/deploy.sh`, lalu buat Super Admin:
  `php artisan mabarqueue:create-admin emailkamu@domain.com`
- hPanel → **Advanced → Cron Jobs**, tambahkan **satu** cron tiap menit (menjalankan scheduler + queue worker):

```
* * * * * cd /home/USER/domains/DOMAINKAMU/mabarque && php artisan schedule:run >> /dev/null 2>&1
```

### 5. Secret GitHub
Repo → **Settings → Secrets and variables → Actions → New repository secret**:

| Secret | Contoh | Keterangan |
|---|---|---|
| `SSH_HOST` | `153.92.x.x` | IP/host dari hPanel SSH Access |
| `SSH_PORT` | `65002` | opsional, default 65002 |
| `SSH_USER` | `u123456789` | username SSH |
| `SSH_PRIVATE_KEY` | isi file `mabarqueue_deploy` | kunci privat lengkap termasuk baris BEGIN/END |
| `DEPLOY_PATH` | `/home/u123456789/domains/DOMAINKAMU/mabarque` | folder clone aplikasi |

Atau lewat terminal: `gh secret set SSH_HOST -R rickyaaaa/mabarque` (nilai diketik, tidak tersimpan di riwayat).

Job deploy memakai environment GitHub **`production`** — di **Settings → Environments → production** kamu bisa
menambahkan *required reviewers* supaya setiap deploy harus disetujui dulu.

---

## Masalah umum

| Gejala | Penyebab & solusi |
|---|---|
| Job deploy "dilewati" | Secret di tabel atas belum lengkap. |
| `Permission denied (publickey)` | Kunci publik belum ditambahkan di hPanel, atau `SSH_PRIVATE_KEY` terpotong. |
| `Butuh PHP 8.2+` | PHP CLI server lebih lama dari PHP web. Set path PHP: ubah perintah di workflow menjadi `PHP_BIN=/opt/alt/php83/usr/bin/php bash scripts/deploy.sh` (cek path dengan `ls /opt/alt/`). |
| `.env belum ada di server` | Lakukan langkah 4. |
| Login berhasil tapi langsung keluar | `APP_URL`/`SANCTUM_STATEFUL_DOMAINS` tidak sama dengan domain yang dibuka, atau `SESSION_SECURE_COOKIE=true` tanpa HTTPS. |
| Email reset tidak terkirim | Cron belum dipasang (queue tidak jalan) atau `MAIL_*` salah — cek `storage/logs/laravel.log`. |
| Webhook Trakteer berhenti | Trakteer mematikan webhook setelah 3 respons ≥ 300; cek `last_error` di halaman Donasi, lalu aktifkan ulang di dashboard Trakteer. |
