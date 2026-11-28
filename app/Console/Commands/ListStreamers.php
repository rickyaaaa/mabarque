<?php

namespace App\Console\Commands;

use App\Services\BetaAccessService;
use Illuminate\Console\Command;

/** php artisan mabarku:streamers --status=pending */
class ListStreamers extends Command
{
    protected $signature = 'mabarku:streamers
        {--status= : Filter pending | active | inactive}
        {--search= : Cari email, nama channel, atau slug}';

    protected $description = 'Daftar akun streamer beserta status beta (antrean persetujuan private alpha)';

    /** Nama lama sebelum ganti merek ke Mabarku — tetap jalan untuk catatan/skrip lama. */
    protected $aliases = ['mabarqueue:streamers'];

    public function handle(BetaAccessService $service): int
    {
        $status = $this->option('status');

        if ($status !== null && ! in_array($status, BetaAccessService::STATUSES, true)) {
            $this->error('Status harus pending, active, atau inactive.');

            return self::INVALID;
        }

        $streamers = $service->listStreamers($status, $this->option('search'), 100);

        if ($streamers->isEmpty()) {
            $this->info('Tidak ada streamer yang cocok.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Email', 'Channel', 'Slug', 'Status', 'Daftar'],
            $streamers->getCollection()->map(fn ($user) => [
                $user->id, $user->email, $user->channel_name, $user->slug, $user->beta_status,
                $user->created_at?->timezone(config('app.timezone'))->format('d M Y H:i'),
            ])
        );

        $this->line("Total: {$streamers->total()}".($streamers->total() > 100 ? ' (menampilkan 100 terbaru)' : ''));

        return self::SUCCESS;
    }
}
