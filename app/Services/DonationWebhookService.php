<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\LiveSession;
use App\Models\PlatformConnection;
use App\Services\Webhooks\IncomingDonation;
use App\Services\Webhooks\InvalidPayload;
use App\Services\Webhooks\ProviderAdapter;
use App\Services\Webhooks\ProviderAdapters;
use App\Services\Webhooks\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Penerima webhook donasi. Format tiap provider ditangani adapter (App\Services\Webhooks\*Adapter).
 *
 * Kebijakan respons: kasus normal SELALU 2xx — Trakteer menganggap respons >= 300 gagal dan mematikan
 * webhook setelah 3 kali gagal. Hanya kredensial salah (401) dan payload rusak (422) yang dibalas 4xx.
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

        // Simpan contoh payload terakhir (kredensial disamarkan) untuk membantu streamer mencocokkan format.
        $this->storeSample($connection, $adapter, $request, $payload, $credential);

        // Tanpa kredensial, siapa pun yang tahu URL koneksi bisa menyuntik donasi palsu ke antrean.
        if ($credential === '' || ! $adapter->verify($request, $payload, $credential)) {
            $connection->forceFill(['last_error' => $adapter->credentialLabel().' tidak cocok — webhook ditolak.'])->save();
            abort(401, $adapter->credentialLabel().' tidak cocok.');
        }

        try {
            $incoming = $adapter->parse($payload);
        } catch (InvalidPayload $exception) {
            $connection->forceFill(['status' => 'error', 'last_error' => $exception->getMessage()])->save();
            throw ValidationException::withMessages(['payload' => $exception->getMessage()]);
        }

        if ($incoming === null) {
            return WebhookResult::ignored('Bukan event donasi.');
        }

        if ($incoming->isTest) {
            $this->markReceived($connection);

            return WebhookResult::test();
        }

        // Koneksi dijeda: donasi dicatat sebagai "diabaikan" (terlihat di kotak masuk) tanpa masuk antrean.
        if ($connection->status === 'paused') {
            $donation = $this->record($connection, $incoming, $payload, null, 'ignored');

            return WebhookResult::ignored('Koneksi sedang dijeda oleh streamer.', $donation);
        }

        return DB::transaction(function () use ($connection, $incoming, $payload): WebhookResult {
            $session = LiveSession::query()
                ->where('user_id', $connection->user_id)
                ->where('status', 'active')
                ->latest('started_at')
                ->first();

            // Nominal non-IDR tidak bisa dicocokkan ke harga paket → selalu perlu dicek streamer.
            $donation = $this->record($connection, $incoming, $payload, $session, $incoming->currency === 'IDR' ? 'received' : 'review');
            $isNew = $donation->wasRecentlyCreated;
            $this->markReceived($connection);

            $processed = $donation->status === 'received' ? $this->queueService->process($donation) : $donation->fresh();

            if ($isNew) {
                // Notifikasi untuk panel "Notifikasi masuk" di halaman donasi streamer.
                $processed->notifications()->create([
                    'user_id' => $connection->user_id,
                    'type' => in_array($processed->status, ['queued', 'review'], true) ? $processed->status : 'received',
                    'context' => ['donor_name' => $processed->donor_name, 'amount' => $processed->amount, 'provider' => $processed->provider],
                ]);
            }

            return WebhookResult::donation($processed, $isNew);
        });
    }

    private function record(PlatformConnection $connection, IncomingDonation $incoming, array $payload, ?LiveSession $session, string $status): Donation
    {
        return Donation::query()->firstOrCreate(
            ['platform_connection_id' => $connection->id, 'external_id' => Str::limit($incoming->externalId, 250, '')],
            [
                'live_session_id' => $session?->id,
                'provider' => $connection->provider,
                'donor_name' => Str::limit($incoming->donorName, 120, ''),
                'donor_email' => $incoming->donorEmail,
                'amount' => $incoming->amount,
                'status' => $status,
                'payload' => $payload,
                'received_at' => now(),
                'processed_at' => $status === 'ignored' ? now() : null,
            ]
        );
    }

    private function markReceived(PlatformConnection $connection): void
    {
        $connection->forceFill([
            'last_received_at' => now(),
            'status' => $connection->status === 'paused' ? 'paused' : 'active',
            'last_error' => null,
        ])->save();
    }

    private function storeSample(PlatformConnection $connection, ProviderAdapter $adapter, Request $request, array $payload, string $credential): void
    {
        $mask = fn ($value) => is_string($value) && $credential !== '' && str_contains($value, $credential) ? '***' : $value;
        $secretHeaders = array_map('strtolower', $adapter->secretHeaders());

        $headers = collect($request->headers->all())
            ->except(['cookie', 'host', 'content-length', 'x-forwarded-for', 'x-real-ip'])
            ->map(fn (array $values, string $name) => in_array(strtolower($name), $secretHeaders, true) ? '***' : $mask($values[0] ?? ''))
            ->all();

        $sample = ['received_at' => now()->toIso8601String(), 'headers' => $headers, 'payload' => array_map($mask, $payload)];

        if (strlen((string) json_encode($sample)) <= self::SAMPLE_MAX_BYTES) {
            $connection->forceFill(['last_payload' => $sample])->save();
        }
    }
}
