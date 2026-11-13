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
- ✅ Livewire: papan antrean (`/dashboard`), sesi live (`/live-session`), hitungan match (`/match-count`), paket VIP (`/vip-packages`), donasi & koneksi provider (`/donations`), dan pengaturan overlay (`/overlay-settings`) — polling otomatis, tanpa WebSocket.
- ✅ Halaman tanpa login: antrean publik `/{slug}` dan overlay OBS `/o/{token}` (JS ringan + `/api`, diperbarui tiap 3–5 detik).
- ✅ Webhook per provider (`app/Services/Webhooks`): Trakteer & Saweria memakai format aslinya; SociaBuzz masih sementara.
- ⚠️ Belum: konfirmasi format SociaBuzz dari notifikasi uji asli, uji di MySQL, dan deploy.

### Menyambungkan provider donasi

1. Buka **VIP & Donasi** → *Hubungkan* provider, isi kredensialnya, lalu tempel URL webhook yang muncul di dashboard provider.
2. Donasi dengan nominal **tepat** sama dengan harga paket aktif otomatis masuk antrean; sisanya muncul sebagai "Perlu cek".
3. *Jeda* membuat donasi dicatat sebagai "diabaikan" tanpa masuk antrean — tetap dibalas 2xx.

| Provider | Kredensial | Verifikasi | Nominal yang dicocokkan | Sumber format |
|---|---|---|---|---|
| Trakteer | Webhook token | header `X-Webhook-Token` = token (dibandingkan langsung) | `price` (total) | [dokumentasi resmi](https://help.trakteer.id/help-center/articles/70/panduan-webhook) |
| Saweria | Stream key | header `Saweria-Callback-Signature` = HMAC-SHA256(stream key, `version`+`id`+`amount_raw`+`donator_name`+`donator_email`) | `amount_raw` | library komunitas `saweria-webhook-express` |
| SociaBuzz | Webhook token | token di header kandidat (`sb-webhook-token`, `x-webhook-token`, …) | `amount` (non-IDR → "Perlu cek") | **belum terkonfirmasi** |

**Kebijakan respons:** kasus normal selalu 2xx (donasi baru 201, duplikat 200, uji/diabaikan/dijeda 200–202) karena Trakteer mematikan webhook setelah 3 respons ≥ 300. Hanya kredensial salah (401) dan payload rusak (422) yang dibalas 4xx. Payload uji Trakteer (`transaction_id` berawalan `test-`) hanya menandai koneksi aktif.

**Memastikan format SociaBuzz:** hubungkan SociaBuzz, tekan *Test Notification* di dashboard SociaBuzz, lalu buka *Lihat contoh webhook terakhir* di halaman Donasi (kredensial disamarkan). Sesuaikan `SociaBuzzAdapter` bila nama field/header berbeda.
