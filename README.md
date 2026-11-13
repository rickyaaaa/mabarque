# mabarque

**MabarQueue** — SaaS antrean VIP mabar untuk streamer Mobile Legends. Donasi masuk, antrean VIP otomatis.

Stack: Laravel 12 · Livewire 4 · Sanctum (SPA cookie auth) · MySQL (SQLite untuk lokal/test) · Database queue.

## Menjalankan di lokal

Butuh PHP 8.2+, Composer, dan Node.js.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

**Database** — default `.env.example` memakai SQLite (paling cepat untuk coba-coba):

```bash
touch database/database.sqlite
php artisan migrate
```

Untuk MySQL, ubah di `.env`: `DB_CONNECTION=mysql`, lalu isi `DB_HOST`, `DB_PORT`, `DB_DATABASE=mabarqueue`, `DB_USERNAME`, `DB_PASSWORD`, buat database-nya, dan jalankan `php artisan migrate`.

**Server & worker**

```bash
php artisan serve        # aplikasi di http://127.0.0.1:8000
php artisan queue:work   # email reset password & proses webhook (database queue)
```

## Test

```bash
php artisan test
```

Mencakup registrasi (email unik, slug), login/logout + rate limit, proteksi akses beta, reset password, profil & halaman publik, serta isolasi data antar-streamer.

## Struktur

- `app/Services` — logika bisnis (Queue Engine, VIP Pricing, Session & Match, Webhook, Auth, Profil).
- `app/Http/Controllers`, `app/Http/Requests`, `app/Http/Middleware` — lapisan HTTP/API (`routes/api.php`).
- `app/Livewire` + `resources/views/livewire` — papan antrean Livewire.
- File `*.html/*.css/*.js` di root — **prototipe frontend statis (data tiruan)**, belum dipindah ke Blade.

## Status

Backend API sudah teruji lewat feature test. Halaman dashboard/publik/overlay masih prototipe statis dan belum disambungkan ke Blade/Livewire.
