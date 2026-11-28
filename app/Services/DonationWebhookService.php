<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\LiveSession;
use App\Models\PlatformConnection;
use App\Models\WebhookDelivery;
use App\Services\Webhooks\IncomingDonation;
use App\Services\Webhooks\InvalidPayload;
use App\Services\Webhooks\ProviderAdapter;
use App\Services\Webhooks\ProviderAdapters;
use App\Services\Webhooks\WebhookResult;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Penerima webhook donasi. Format tiap provider ditangani adapter (App\Services\Webhooks\*Adapter).
 *
 * Kebijakan respons: semua kiriman yang lolos verifikasi kredensial SELALU dibalas 2xx — termasuk payload
 * yang tidak bisa dibaca dan error tak terduga. Trakteer mematikan webhook setelah 3 kali gagal, dan
 * SociaBuzz terbukti berhenti mengirim donasi berikutnya setelah satu balasan 422 (kejadian 2026-11-28).
 * Hanya kredensial salah (401) dan URL provider yang tidak cocok (422) yang dibalas 4xx.
 *
 * Supaya tidak ada donasi yang hilang tanpa jejak, setiap kiriman:
 *  1. ditulis dulu ke jurnal harian storage/logs/webhooks-YYYY-MM-DD.jsonl (tetap ada walau database gagal),
 *  2. dicatat di tabel webhook_deliveries (hasil + error, tampil di halaman Donasi),
 *  3. bisa diproses ulang dengan `php artisan webhooks:replay`.
 */
class DonationWebhookService
{
    private const SAMPLE_MAX_BYTES = 16384;

    public function __construct(
        private readonly DonationQueueService $queueService,
        private readonly ProviderAdapters $adapters,
    ) {
    }

    public function receive(PlatformConnection $connection, Request $request): WebhookResult
    {
        if ((string) $request->route('provider') !== $connection->provider) {
            throw ValidationException::withMessages(['provider' => 'Provider webhook tidak cocok dengan koneksi.']);
        }

        $adapter = $this->adapters->for($connection->provider);
        $payload = $request->json()->all();
        $credential = (string) $connection->signing_secret;
        $sample = $this->sample($adapter, $request, $payload, $credential);

        // Tanpa kredensial, siapa pun yang tahu URL koneksi bisa menyuntik donasi palsu ke antrean.
        $verified = $credential !== '' && $adapter->verify($request, $payload, $credential);

        // Jurnal file dulu, sebelum menyentuh database (SQLite bisa sedang terkunci).
        $this->journal($connection, $sample, $verified);

        if (! $verified) {
            $this->safely(fn () => $connection->forceFill([
                'last_error' => $adapter->credentialLabel().' tidak cocok — webhook ditolak.',
            ] + $this->samplePatch($sample))->save());
            abort(401, $adapter->credentialLabel().' tidak cocok.');
        }

        $delivery = $this->safely(fn () => WebhookDelivery::query()->create([
            'platform_connection_id' => $connection->id,
            'provider' => $connection->provider,
            'payload' => $this->fits($sample) ? $sample : ['payload' => ['_note' => 'Payload terlalu besar untuk disimpan; lihat jurnal webhooks-*.jsonl.']],
        ]));

        try {
            $result = $this->handle($connection, $adapter, $payload, now(), $sample);
            $error = $result->result() === 'unreadable' ? (string) ($result->body['data']['reason'] ?? '') : null;
        } catch (Throwable $exception) {
            Log::error('Webhook donasi gagal diproses', [
                'platform_connection_id' => $connection->id,
                'provider' => $connection->provider,
                'webhook_delivery_id' => $delivery?->id,
                'exception' => $exception,
            ]);
            $result = WebhookResult::failed();
            $error = Str::limit($exception->getMessage(), 1000);
            $this->safely(fn () => $connection->forceFill([
                'last_received_at' => now(),
                'last_error' => 'Webhook '.now()->format('H:i').' gagal diproses (error server). Tercatat untuk diproses ulang.',
            ])->save());
        }

        $this->safely(fn () => $delivery?->forceFill([
            'http_status' => $result->status,
            'result' => $result->result(),
            'error' => $error,
            'donation_id' => $result->donation?->id,
        ])->save());

        return $result;
    }

    /**
     * Proses ulang kiriman yang tercatat (jurnal atau webhook_deliveries). Idempoten: donasi yang sudah
     * tercatat dikenali lewat external_id dan tidak dobel. Tidak mengubah status koneksi.
     */
    public function replay(PlatformConnection $connection, array $payload, CarbonInterface $receivedAt): WebhookResult
    {
        return $this->handle($connection, $this->adapters->for($connection->provider), $payload, $receivedAt, null);
    }

    /** Proses ulang satu baris webhook_deliveries; baris ditandai selesai bila donasinya kini tercatat. */
    public function replayDelivery(WebhookDelivery $delivery): WebhookResult
    {
        $connection = $delivery->platformConnection()->firstOrFail();
        $result = $this->replay($connection, $delivery->body(), $delivery->created_at ?? now());
        $settled = ! in_array($result->result(), WebhookDelivery::PROBLEMS, true);

        $delivery->forceFill([
            'result' => $result->result(),
            'http_status' => $result->status,
            'error' => $settled ? null : (string) ($result->body['data']['reason'] ?? $delivery->error),
            'donation_id' => $result->donation?->id ?? $delivery->donation_id,
            'replayed_at' => now(),
            'resolved_at' => $settled ? now() : null,
        ])->save();

        return $result;
    }

    /** @param  array|null  $sample  null = proses ulang (status koneksi tidak disentuh) */
    private function handle(PlatformConnection $connection, ProviderAdapter $adapter, array $payload, CarbonInterface $receivedAt, ?array $sample): WebhookResult
    {
        $live = $sample !== null;

        try {
            $incoming = $adapter->parse($payload);
        } catch (InvalidPayload $exception) {
            // Koneksi tetap dianggap hidup (kredensial benar); kirimannya tampil sebagai "gagal dibaca".
            if ($live) {
                $this->markReceived($connection, $sample, 'Webhook '.now()->format('H:i').' gagal dibaca: '.$exception->getMessage());
            }

            return WebhookResult::unreadable($exception->getMessage());
        }

        if ($incoming === null || $incoming->isTest) {
            if ($live) {
                $this->markReceived($connection, $sample);
            }

            return $incoming === null ? WebhookResult::ignored('Bukan event donasi.') : WebhookResult::test();
        }

        // Koneksi dijeda: donasi dicatat sebagai "diabaikan" (terlihat di kotak masuk) tanpa masuk antrean.
        if ($connection->status === 'paused') {
            $donation = $this->record($connection, $incoming, $payload, null, 'ignored', $receivedAt);
            if ($live) {
                $this->markReceived($connection, $sample);
            }

            return WebhookResult::ignored('Koneksi sedang dijeda oleh streamer.', $donation);
        }

        return DB::transaction(function () use ($connection, $incoming, $payload, $receivedAt, $sample, $live): WebhookResult {
            // Sesi live aktif, atau daftar sisa game saat streamer belum live (donasi tetap bisa masuk antrean).
            // Proses ulang: sesi yang sedang berjalan saat donasi diterima, bila ada.
            $session = (! $live ? $this->sessionAt($connection, $receivedAt) : null)
                ?? app(LiveSessionService::class)->queueSession($connection->user);

            // Nominal non-IDR tidak bisa dicocokkan ke harga paket → selalu perlu dicek streamer.
            $donation = $this->record($connection, $incoming, $payload, $session, $incoming->currency === 'IDR' ? 'received' : 'review', $receivedAt);
            $isNew = $donation->wasRecentlyCreated;
            if ($live) {
                $this->markReceived($connection, $sample);
            }

            $processed = $donation->status === 'received' ? $this->queueService->process($donation) : $donation->fresh();

            if ($isNew) {
                // Notifikasi untuk panel "Notifikasi masuk" di halaman donasi streamer.
                $processed->notifications()->create([
                    'user_id' => $connection->user_id,
                    'type' => in_array($processed->status, ['queued', 'review', 'reward'], true) ? $processed->status : 'received',
                    'context' => ['donor_name' => $processed->donor_name, 'amount' => $processed->amount, 'provider' => $processed->provider]
                        + ($processed->status === 'reward' ? ['reward' => $processed->vipPackage?->getsLabel()] : [])
                        + ($processed->isForeign() ? ['currency' => $processed->currency, 'original_amount' => (float) $processed->original_amount] : []),
                ]);
            }

            return WebhookResult::donation($processed, $isNew);
        });
    }

    private function record(PlatformConnection $connection, IncomingDonation $incoming, array $payload, ?LiveSession $session, string $status, CarbonInterface $receivedAt): Donation
    {
        return Donation::query()->firstOrCreate(
            ['platform_connection_id' => $connection->id, 'external_id' => Str::limit($incoming->externalId, 250, '')],
            [
                'live_session_id' => $session?->id,
                'provider' => $connection->provider,
                'donor_name' => Str::limit($incoming->donorName, 120, ''),
                'donor_email' => $incoming->donorEmail,
                'amount' => $incoming->amount,
                'currency' => $incoming->currency ?: 'IDR',
                'original_amount' => $incoming->originalAmount,
                'message' => $incoming->message !== null ? Str::limit(trim($incoming->message), 500, '') : null,
                'status' => $status,
                'payload' => $payload,
                'received_at' => $receivedAt,
                'processed_at' => $status === 'ignored' ? now() : null,
            ]
        );
    }

    /** Sesi live milik streamer yang sedang berjalan pada waktu tertentu (untuk proses ulang). */
    private function sessionAt(PlatformConnection $connection, CarbonInterface $at): ?LiveSession
    {
        return LiveSession::query()
            ->where('user_id', $connection->user_id)
            ->where('title', '!=', LiveSessionService::STANDBY_TITLE)
            ->where('started_at', '<=', $at)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $at))
            ->latest('started_at')
            ->first();
    }

    /**
     * Kiriman berkredensial benar diterima: koneksi hidup (kecuali dijeda streamer). Contoh payload terakhir
     * disimpan sekalian dalam satu tulis — membantu streamer mencocokkan format.
     */
    private function markReceived(PlatformConnection $connection, array $sample, ?string $error = null): void
    {
        $connection->forceFill([
            'last_received_at' => now(),
            'status' => $connection->status === 'paused' ? 'paused' : 'active',
            'last_error' => $error,
        ] + $this->samplePatch($sample))->save();
    }

    /** Headers + payload dengan kredensial disamarkan. */
    private function sample(ProviderAdapter $adapter, Request $request, array $payload, string $credential): array
    {
        $mask = fn ($value) => is_string($value) && $credential !== '' && str_contains($value, $credential) ? '***' : $value;
        $secretHeaders = array_map('strtolower', $adapter->secretHeaders());

        $headers = collect($request->headers->all())
            ->except(['cookie', 'host', 'content-length', 'x-forwarded-for', 'x-real-ip'])
            ->map(fn (array $values, string $name) => in_array(strtolower($name), $secretHeaders, true) ? '***' : $mask($values[0] ?? ''))
            ->all();

        return ['received_at' => now()->toIso8601String(), 'headers' => $headers, 'payload' => array_map($mask, $payload)];
    }

    private function samplePatch(array $sample): array
    {
        return $this->fits($sample) ? ['last_payload' => $sample] : [];
    }

    private function fits(array $sample): bool
    {
        return strlen((string) json_encode($sample)) <= self::SAMPLE_MAX_BYTES;
    }

    /** Satu baris JSON per kiriman. Tidak pernah melempar error — jurnal tidak boleh menggagalkan webhook. */
    private function journal(PlatformConnection $connection, array $sample, bool $verified): void
    {
        try {
            $line = json_encode([
                'received_at' => $sample['received_at'],
                'platform_connection_id' => $connection->id,
                'provider' => $connection->provider,
                'verified' => $verified,
            ] + $sample, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

            $path = self::journalPath(now());
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            file_put_contents($path, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public static function journalPath(CarbonInterface $date): string
    {
        return rtrim((string) config('logging.webhook_journal_path', storage_path('logs')), '/\\').DIRECTORY_SEPARATOR
            .'webhooks-'.$date->format('Y-m-d').'.jsonl';
    }

    /** Tulis ke database tanpa menggagalkan webhook bila database bermasalah (error tetap dilaporkan). */
    private function safely(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
