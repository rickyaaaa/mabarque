<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BetaAccessService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * php artisan mabarqueue:beta nadia@example.com active     # setujui
 * php artisan mabarqueue:beta nadia-gaming inactive        # nonaktifkan (pakai slug juga bisa)
 */
class SetBetaStatus extends Command
{
    protected $signature = 'mabarqueue:beta
        {user : Email atau slug streamer}
        {status : active | pending | inactive}';

    protected $description = 'Setujui, tunda, atau nonaktifkan akses beta streamer';

    public function handle(BetaAccessService $service): int
    {
        $key = strtolower(trim((string) $this->argument('user')));
        $user = User::query()->where('email', $key)->orWhere('slug', $key)->first();

        if ($user === null) {
            $this->error("Akun '{$key}' tidak ditemukan.");

            return self::FAILURE;
        }

        try {
            $changed = $service->setStatus($user, strtolower((string) $this->argument('status')));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->info($changed
            ? "{$user->email} (@{$user->slug}) sekarang berstatus {$user->beta_status}."
            : "{$user->email} sudah berstatus {$user->beta_status}; tidak ada perubahan.");

        return self::SUCCESS;
    }
}
