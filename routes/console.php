<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Penjadwal — di Hostinger cukup SATU cron tiap menit:
 *   * * * * * cd /path/aplikasi && php artisan schedule:run >> /dev/null 2>&1
 *
 * Shared hosting tidak punya supervisor, jadi queue worker (email reset password & persetujuan beta)
 * dijalankan scheduler tiap menit dan berhenti sendiri saat antrean kosong / mendekati 1 menit.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

// Bersihkan job gagal yang sudah lama & token reset password kedaluwarsa.
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('auth:clear-resets')->daily();
