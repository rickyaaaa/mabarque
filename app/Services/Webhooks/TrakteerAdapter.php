<?php

namespace App\Services\Webhooks;

use Illuminate\Http\Request;

/**
 * Trakteer. Sumber: dokumentasi resmi https://help.trakteer.id/help-center/articles/70/panduan-webhook
 *
 * - Header `X-Webhook-Token` berisi token statis dari halaman pengaturan webhook (dibandingkan langsung).
 * - Payload event "tip": created_at, transaction_id, type, supporter_name, supporter_message, media,
 *   unit, unit_icon, quantity, price (total), net_amount.
 * - Tombol "Send Webhook Test" mengirim transaction_id berawalan "test-".
 * - Respons >= 300 dihitung gagal; 3 kali gagal → webhook dimatikan otomatis oleh Trakteer.
 */
class TrakteerAdapter implements ProviderAdapter
{
    public const TOKEN_HEADER = 'X-Webhook-Token';

    public function credentialLabel(): string
    {
        return 'Webhook token';
    }

    public function secretHeaders(): array
    {
        return [self::TOKEN_HEADER];
    }

    public function verify(Request $request, array $payload, string $credential): bool
    {
        $received = (string) $request->header(self::TOKEN_HEADER);

        return $received !== '' && hash_equals($credential, $received);
    }

    public function parse(array $payload): ?IncomingDonation
    {
        if (($payload['type'] ?? null) !== 'tip') {
            return null;
        }

        $id = $payload['transaction_id'] ?? null;
        $name = trim((string) ($payload['supporter_name'] ?? ''));
        $price = Amount::parse($payload['price'] ?? null);

        if (blank($id) || $price === null || (int) round($price) <= 0) {
            throw new InvalidPayload('Payload Trakteer tidak lengkap (transaction_id, price).');
        }

        return new IncomingDonation(
            externalId: (string) $id,
            donorName: $name !== '' ? $name : 'Anonim', // donatur anonim tetap dicatat
            amount: (int) round($price), // total harga unit × quantity (sebelum potongan, lihat net_amount)
            message: filled($payload['supporter_message'] ?? null) ? (string) $payload['supporter_message'] : null,
            isTest: str_starts_with((string) $id, 'test-'),
        );
    }
}
