<?php

namespace App\Console\Commands;

use App\Models\PlatformConnection;
use App\Models\WebhookDelivery;
use App\Services\DonationWebhookService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Proses ulang webhook donasi yang tercatat. Idempoten: donasi yang sudah ada (external_id sama) tidak dobel.
 *
 *   php artisan webhooks:replay 42                  → satu baris webhook_deliveries
 *   php artisan webhooks:replay failed              → semua kiriman gagal yang belum ditangani
 *   php artisan webhooks:replay 2026-11-28          → jurnal storage/logs/webhooks-2026-11-28.jsonl
 *   php artisan webhooks:replay path/ke/file.jsonl  → file jurnal tertentu
 */
class ReplayWebhooks extends Command
{
    protected $signature = 'webhooks:replay
        {target : ID delivery, "failed", tanggal YYYY-MM-DD, atau path file .jsonl}
        {--connection= : Batasi ke ID koneksi provider tertentu}
        {--dry-run : Tampilkan saja yang akan diproses, tanpa mengubah data}';

    protected $description = 'Proses ulang webhook donasi dari webhook_deliveries atau jurnal webhooks-*.jsonl';

    public function handle(DonationWebhookService $service): int
    {
        $target = (string) $this->argument('target');
        $only = $this->option('connection') !== null ? (int) $this->option('connection') : null;

        if (ctype_digit($target) || $target === 'failed') {
            $deliveries = WebhookDelivery::query()
                ->when(ctype_digit($target), fn ($q) => $q->whereKey((int) $target), fn ($q) => $q->unresolvedProblems())
                ->when($only, fn ($q) => $q->where('platform_connection_id', $only))
                ->oldest()
                ->get();

            if ($deliveries->isEmpty()) {
                $this->info('Tidak ada kiriman yang cocok.');

                return self::SUCCESS;
            }

            $rows = $deliveries->map(fn (WebhookDelivery $delivery) => [
                $delivery->id, $delivery->created_at?->format('Y-m-d H:i:s'), $delivery->provider, $delivery->summary(),
                $this->option('dry-run') ? '(dry-run)' : $this->attempt(fn () => $service->replayDelivery($delivery)->result()),
            ]);
            $this->table(['ID', 'Diterima', 'Provider', 'Isi', 'Hasil'], $rows->all());

            return self::SUCCESS;
        }

        $path = preg_match('/^\d{4}-\d{2}-\d{2}$/', $target) ? DonationWebhookService::journalPath(Carbon::parse($target)) : $target;
        if (! is_file($path)) {
            $this->error("File jurnal tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $rows = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $number => $line) {
            $entry = json_decode($line, true);
            // Hanya kiriman yang lolos verifikasi kredensial saat diterima.
            if (! is_array($entry) || ! ($entry['verified'] ?? false) || ($only && (int) ($entry['platform_connection_id'] ?? 0) !== $only)) {
                continue;
            }

            $connection = PlatformConnection::query()->find((int) ($entry['platform_connection_id'] ?? 0));
            $summary = (new WebhookDelivery(['payload' => ['payload' => $entry['payload'] ?? []]]))->summary();

            $rows[] = [
                $number + 1, $entry['received_at'] ?? '-', $entry['provider'] ?? '-', $summary,
                match (true) {
                    $connection === null => 'koneksi tidak ditemukan',
                    (bool) $this->option('dry-run') => '(dry-run)',
                    default => $this->attempt(fn () => $service->replay($connection, (array) ($entry['payload'] ?? []), Carbon::parse($entry['received_at'] ?? 'now'))->result()),
                },
            ];
        }

        $rows === [] ? $this->info('Tidak ada kiriman terverifikasi di jurnal ini.') : $this->table(['Baris', 'Diterima', 'Provider', 'Isi', 'Hasil'], $rows);

        return self::SUCCESS;
    }

    private function attempt(callable $callback): string
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            return 'error: '.$exception->getMessage();
        }
    }
}
