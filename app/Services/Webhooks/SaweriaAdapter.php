<?php

namespace App\Services\Webhooks;

use Illuminate\Http\Request;

/**
 * Saweria. Sumber: library komunitas saweria-webhook-express (src/index.ts) — bukan dokumentasi resmi.
 *
 * - Header `Saweria-Callback-Signature` = hex HMAC-SHA256 dengan **stream key** sebagai kunci,
 *   atas gabungan (tanpa pemisah) field: version + id + amount_raw + donator_name + donator_email.
 * - Payload: version, created_at, id, type ("donation"), amount_raw, cut, donator_name, donator_email, message.
 */
class SaweriaAdapter implements ProviderAdapter
{
    public const SIGNATURE_HEADER = 'Saweria-Callback-Signature';

    private const SIGNED_FIELDS = ['version', 'id', 'amount_raw', 'donator_name', 'donator_email'];

    public function credentialLabel(): string
    {
        return 'Stream key';
    }

    public function secretHeaders(): array
    {
        return [self::SIGNATURE_HEADER];
    }

    public function verify(Request $request, array $payload, string $credential): bool
    {
        $received = (string) $request->header(self::SIGNATURE_HEADER);

        return $received !== '' && hash_equals(self::sign($payload, $credential), strtolower($received));
    }

    public function parse(array $payload): ?IncomingDonation
    {
        if (($payload['type'] ?? null) !== 'donation') {
            return null;
        }

        $id = $payload['id'] ?? null;
        $name = trim((string) ($payload['donator_name'] ?? ''));
        $amount = $payload['amount_raw'] ?? null;

        if (blank($id) || $name === '' || ! is_numeric($amount) || (int) round((float) $amount) <= 0) {
            throw new InvalidPayload('Payload Saweria tidak lengkap (id, donator_name, amount_raw).');
        }

        return new IncomingDonation(
            externalId: (string) $id,
            donorName: $name,
            amount: (int) round((float) $amount), // nominal yang dibayar donatur (sebelum potongan "cut")
            donorEmail: filled($payload['donator_email'] ?? null) ? (string) $payload['donator_email'] : null,
            message: filled($payload['message'] ?? null) ? (string) $payload['message'] : null,
        );
    }

    /** Hitung signature seperti Saweria (dipakai juga oleh test). Nilai digabung seperti Array.join("") di JavaScript. */
    public static function sign(array $payload, string $streamKey): string
    {
        $data = implode('', array_map(fn (string $key) => self::stringify($payload[$key] ?? null), self::SIGNED_FIELDS));

        return hash_hmac('sha256', $data, $streamKey);
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_float($value) && floor($value) === $value => (string) (int) $value,
            default => (string) $value,
        };
    }
}
