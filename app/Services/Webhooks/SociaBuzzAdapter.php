<?php

namespace App\Services\Webhooks;

use Illuminate\Http\Request;

/**
 * SociaBuzz — SEMENTARA, format belum terkonfirmasi (tidak ada dokumentasi publik).
 *
 * - Field yang dibaca integrasi komunitas (Streamerbot-SociaBuzz): supporter, amount, currency.
 * - Ada "webhook token" di dashboard SociaBuzz, tetapi nama headernya tidak terdokumentasi; adapter ini
 *   mencoba beberapa kandidat header/field. Contoh payload terakhir (header disamarkan) disimpan di koneksi
 *   supaya format asli bisa dicocokkan setelah streamer menekan "Test Notification".
 * - Donasi non-IDR tidak dicocokkan otomatis ke paket (masuk "Perlu cek").
 */
class SociaBuzzAdapter implements ProviderAdapter
{
    /** Kandidat lokasi token (urut prioritas). */
    public const TOKEN_HEADERS = ['sb-webhook-token', 'x-sociabuzz-webhook-token', 'x-sociabuzz-token', 'x-webhook-token'];

    private const TOKEN_FIELDS = ['sb_webhook_token', 'webhook_token', 'token'];

    public function credentialLabel(): string
    {
        return 'Webhook token';
    }

    public function secretHeaders(): array
    {
        return [...self::TOKEN_HEADERS, 'authorization'];
    }

    public function verify(Request $request, array $payload, string $credential): bool
    {
        $candidates = array_map(fn (string $header) => (string) $request->header($header), self::TOKEN_HEADERS);

        if (preg_match('/^Bearer\s+(.+)$/i', (string) $request->header('Authorization'), $match)) {
            $candidates[] = $match[1];
        }

        foreach (self::TOKEN_FIELDS as $field) {
            $candidates[] = is_scalar($payload[$field] ?? null) ? (string) $payload[$field] : '';
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && hash_equals($credential, $candidate)) {
                return true;
            }
        }

        return false;
    }

    public function parse(array $payload): ?IncomingDonation
    {
        $id = $this->first($payload, ['id', 'transaction_id', 'order_id', 'reference_id']);
        $name = trim((string) $this->first($payload, ['supporter', 'supporter_name', 'name', 'donor_name']));
        $amount = $this->first($payload, ['amount', 'total', 'nominal', 'amount_raw']);
        $currency = strtoupper(trim((string) ($this->first($payload, ['currency', 'currency_code']) ?? 'IDR')));

        if ($name === '' || ! is_numeric($amount) || (int) round((float) $amount) <= 0) {
            throw new InvalidPayload('Payload SociaBuzz tidak dikenali. Cek contoh payload terakhir di halaman Donasi.');
        }

        return new IncomingDonation(
            // Tanpa id dari provider, sidik jari payload mencegah donasi yang sama tercatat dua kali saat dikirim ulang.
            externalId: filled($id) ? (string) $id : 'sb-'.substr(hash('sha256', json_encode($payload)), 0, 40),
            donorName: $name,
            amount: (int) round((float) $amount),
            donorEmail: filled($payload['email'] ?? null) ? (string) $payload['email'] : null,
            message: ($m = $this->first($payload, ['message', 'supporter_message', 'support_message'])) ? (string) $m : null,
            currency: $currency ?: 'IDR',
            isTest: (bool) ($payload['is_test'] ?? $payload['test'] ?? false),
        );
    }

    private function first(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && $payload[$key] !== '') {
                return $payload[$key];
            }
        }

        return null;
    }
}
