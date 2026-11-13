<?php

namespace Tests\Concerns;

use App\Models\PlatformConnection;
use App\Services\Webhooks\SaweriaAdapter;
use Illuminate\Testing\TestResponse;

/** Kirim webhook dengan format asli tiap provider (lihat App\Services\Webhooks\*Adapter). */
trait SendsProviderWebhooks
{
    protected function saweriaPayload(string $id, string $name, int $amount, array $overrides = []): array
    {
        return array_merge([
            'version' => '2022.01',
            'created_at' => '2026-11-13T19:00:00+07:00',
            'id' => $id,
            'type' => 'donation',
            'amount_raw' => $amount,
            'cut' => (int) round($amount * 0.05),
            'donator_name' => $name,
            'donator_email' => strtolower(str_replace(' ', '', $name)).'@example.com',
            'message' => 'Semangat bang!',
        ], $overrides);
    }

    protected function trakteerPayload(string $id, string $name, int $price, array $overrides = []): array
    {
        return array_merge([
            'created_at' => '2026-11-13T19:00:00+07:00',
            'transaction_id' => $id,
            'type' => 'tip',
            'supporter_name' => $name,
            'supporter_avatar' => 'https://trakteer.id/images/v2/stats-1.png',
            'supporter_message' => 'Gas mabar',
            'media' => ['gif' => null],
            'unit' => 'Kopi',
            'quantity' => 1,
            'price' => $price,
            'net_amount' => (int) round($price * 0.95),
        ], $overrides);
    }

    protected function sendSaweria(PlatformConnection $connection, array $payload, ?string $streamKey): TestResponse
    {
        $headers = $streamKey === null ? [] : [SaweriaAdapter::SIGNATURE_HEADER => SaweriaAdapter::sign($payload, $streamKey)];

        return $this->postJson("/api/webhooks/donations/saweria/{$connection->id}", $payload, $headers);
    }

    protected function sendTrakteer(PlatformConnection $connection, array $payload, ?string $token): TestResponse
    {
        return $this->postJson("/api/webhooks/donations/trakteer/{$connection->id}", $payload, $token === null ? [] : ['X-Webhook-Token' => $token]);
    }

    protected function sendSociaBuzz(PlatformConnection $connection, array $payload, ?string $token, string $header = 'sb-webhook-token'): TestResponse
    {
        return $this->postJson("/api/webhooks/donations/sociabuzz/{$connection->id}", $payload, $token === null ? [] : [$header => $token]);
    }
}
