<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\LiveSession;
use App\Models\PlatformConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DonationWebhookService
{
    public function __construct(private readonly DonationQueueService $queueService)
    {
    }

    public function receive(PlatformConnection $connection, Request $request): Donation
    {
        $this->assertProviderMatchesRoute($connection, (string) $request->route('provider'));
        $this->verifySignature($connection, $request);

        $payload = $request->json()->all();
        $normalized = $this->normalize($connection->provider, $payload);

        return DB::transaction(function () use ($connection, $normalized, $payload): Donation {
            $session = LiveSession::query()
                ->where('user_id', $connection->user_id)
                ->where('status', 'active')
                ->latest('started_at')
                ->first();

            $donation = Donation::query()->firstOrCreate(
                [
                    'platform_connection_id' => $connection->id,
                    'external_id' => $normalized['external_id'],
                ],
                [
                    'live_session_id' => $session?->id,
                    'provider' => $connection->provider,
                    'donor_name' => $normalized['donor_name'],
                    'donor_email' => $normalized['donor_email'],
                    'amount' => $normalized['amount'],
                    'status' => 'received',
                    'payload' => $payload,
                    'received_at' => now(),
                ]
            );

            $connection->forceFill([
                'last_received_at' => now(),
                'status' => 'active',
                'last_error' => null,
            ])->save();

            return $this->queueService->process($donation);
        });
    }

    private function assertProviderMatchesRoute(PlatformConnection $connection, string $provider): void
    {
        if ($provider !== $connection->provider) {
            throw ValidationException::withMessages(['provider' => 'Provider webhook tidak cocok dengan koneksi.']);
        }
    }

    private function verifySignature(PlatformConnection $connection, Request $request): void
    {
        if ($connection->signing_secret === null) {
            return;
        }

        $signature = (string) $request->header('X-Webhook-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $connection->signing_secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            abort(401, 'Signature webhook tidak valid.');
        }
    }

    private function normalize(string $provider, array $payload): array
    {
        $externalId = $payload['id'] ?? $payload['transaction_id'] ?? $payload['donation_id'] ?? null;
        $donorName = $payload['donor_name'] ?? $payload['donator_name'] ?? $payload['supporter_name'] ?? $payload['name'] ?? null;
        $amount = $payload['amount'] ?? $payload['donation_amount'] ?? $payload['total'] ?? null;

        if ($externalId === null || $donorName === null || ! is_numeric($amount) || (int) $amount <= 0) {
            throw ValidationException::withMessages(['payload' => "Payload {$provider} tidak lengkap."]);
        }

        return [
            'external_id' => (string) $externalId,
            'donor_name' => trim((string) $donorName),
            'donor_email' => isset($payload['email']) ? trim((string) $payload['email']) : null,
            'amount' => (int) $amount,
        ];
    }
}
