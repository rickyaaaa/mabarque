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

**Data demo lokal** — `php artisan db:seed` membuat Super Admin, streamer aktif `@andrarizky` (dengan 3 paket VIP), dan streamer pending `@nadia-gaming`. Daftar akun & password lokalnya ada di `database/seeders/DatabaseSeeder.php`. Seeder ini menolak jalan di produksi.

## Super Admin & akses beta

Akun baru berstatus `pending` dan belum bisa membuka dashboard sampai disetujui.

```bash
php artisan mabarqueue:create-admin admin@domainkamu.com     # password diketik tersembunyi
php artisan mabarqueue:streamers --status=pending           # antrean persetujuan
php artisan mabarqueue:beta nadia@example.com active        # setujui (email/slug), kirim email notifikasi
php artisan mabarqueue:beta nadia-gaming inactive           # nonaktifkan + cabut sesi login
```

Untuk panel admin nanti tersedia API (khusus Super Admin, selain itu 404): `GET /api/admin/streamers?status=pending` dan `PATCH /api/admin/streamers/{id}/beta-status`.

## Test

```bash
php artisan test
```

Mencakup registrasi (email unik, slug), login/logout + rate limit, proteksi akses beta, reset password, profil & halaman publik, serta isolasi data antar-streamer.

## Struktur

- `app/Services` — logika bisnis (Queue Engine, VIP Pricing, Session & Match, Webhook, Auth, Profil).
- `app/Http/Controllers`, `app/Http/Requests`, `app/Http/Middleware` — lapisan HTTP/API (`routes/api.php`).
- `app/Livewire` + `resources/views/livewire` — papan antrean Livewire.
- `resources/views/pages` — halaman web (Blade); `routes/web.php` mengatur akses (tamu, login, beta aktif, publik `/{slug}`).
- `public/css`, `public/js` — gaya & script halaman; `public/js/api.js` memanggil `/api` dengan session + CSRF.

Buka aplikasi di **http://127.0.0.1:8000** (sama dengan `APP_URL`), bukan `localhost:8000` — login memakai cookie Sanctum yang hanya berlaku untuk domain di `APP_URL`/`SANCTUM_STATEFUL_DOMAINS`.

## Status

- ✅ Tersambung ke backend: masuk, daftar, lupa/reset password, keluar, halaman akun, dan pengaturan profil.
- ✅ Livewire: papan antrean (`/dashboard`), sesi live (`/live-session`), dan hitungan match (`/match-count`) — polling otomatis, tanpa WebSocket.
- ⏳ Masih data tiruan (tahap berikutnya): paket VIP, donasi & koneksi provider, overlay OBS, dan isi halaman antrean publik.
