<?php

namespace App\Services\Webhooks;

use Illuminate\Http\Request;

/**
 * SociaBuzz (TRIBE → Integrations → Webhook). Format dikonfirmasi dari "Test Notifikasi" asli (2026-11-17):
 *
 * - Header `sb-webhook-token` = webhook token dari dashboard SociaBuzz (dibandingkan langsung).
 * - Payload: id, amount, currency, amount_settled, currency_settled, supporter, email_supporter, message,
 *   media_type, media_url, item{name,qty}, level{title,price,...}, vote{...}, content{...}, created_at.
 * - Notifikasi uji selalu "Jessica" / jessica@example.com / "Ini hanya test notifikasi" dengan id tetap →
 *   dikenali sebagai uji (hanya menandai koneksi aktif, tidak tercatat sebagai donasi).
 * - Nominal = `amount` (yang dibayar donatur, sama dengan harga paket). Donasi non-IDR masuk "Perlu cek".
 * - `id` sama untuk semua donasi (bukan nomor transaksi) → penanda unik donasi = sidik jari isinya.
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

        // Nama header/field token SociaBuzz tidak terdokumentasi dan bisa berbeda antara notifikasi uji
        // dan donasi asli: terima token di header atau field tingkat atas mana pun yang nilainya persis sama.
        foreach ($request->headers->all() as $values) {
            $candidates[] = (string) ($values[0] ?? '');
        }

        foreach ($payload as $value) {
            if (is_scalar($value)) {
                $candidates[] = (string) $value;
            }
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

        // `id` SociaBuzz BUKAN nomor transaksi: nilainya sama untuk notifikasi uji dan setiap donasi asli
        // (terbukti 2026-11-17), sehingga donasi kedua dst. dianggap duplikat. Sidik jari dari isi donasi
        // membedakan tiap donasi, sedangkan kiriman ulang yang identik tetap terdeteksi sebagai duplikat.
        $fingerprint = implode('|', [
            (string) $id,
            (string) ($payload['created_at'] ?? ''),
            $name,
            (string) $this->first($payload, ['email_supporter', 'email']),
            (string) $amount,
            (string) $this->first($payload, ['message', 'supporter_message', 'support_message']),
            (string) ($payload['media_url'] ?? ''),
        ]);

        return new IncomingDonation(
            externalId: 'sb-'.substr(hash('sha256', $fingerprint), 0, 40),
            donorName: $name,
            amount: (int) round((float) $amount),
            donorEmail: ($email = $this->first($payload, ['email_supporter', 'email'])) ? (string) $email : null,
            message: ($m = $this->first($payload, ['message', 'supporter_message', 'support_message'])) ? (string) $m : null,
            currency: $currency ?: 'IDR',
            isTest: $this->isTestNotification($payload),
        );
    }

    /** Tombol "Test Notifikasi" SociaBuzz mengirim data contoh tetap (Jessica, @example.com, "test notifikasi"). */
    private function isTestNotification(array $payload): bool
    {
        if ((bool) ($payload['is_test'] ?? $payload['test'] ?? false)) {
            return true;
        }

        $email = strtolower(trim((string) $this->first($payload, ['email_supporter', 'email'])));
        $message = strtolower((string) $this->first($payload, ['message', 'supporter_message', 'support_message']));

        return str_ends_with($email, '@example.com') || str_contains($message, 'hanya test notifikasi');
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
