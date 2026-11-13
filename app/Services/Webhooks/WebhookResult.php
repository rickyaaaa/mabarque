<?php

namespace App\Services\Webhooks;

use App\Models\Donation;

/** Hasil pemrosesan satu webhook: status HTTP + isi respons untuk provider. */
final class WebhookResult
{
    public function __construct(
        public readonly int $status,
        public readonly array $body,
        public readonly ?Donation $donation = null,
    ) {
    }

    public static function donation(Donation $donation, bool $isNew): self
    {
        return new self($isNew ? 201 : 200, ['data' => [
            'id' => $donation->id,
            'status' => $donation->status,
            'duplicate' => ! $isNew,
        ]], $donation);
    }

    /** Diterima tetapi sengaja tidak diproses (tetap 2xx supaya provider tidak menganggap gagal). */
    public static function ignored(string $reason, ?Donation $donation = null): self
    {
        return new self(202, ['data' => ['status' => 'ignored', 'reason' => $reason, 'id' => $donation?->id]], $donation);
    }

    public static function test(): self
    {
        return new self(200, ['data' => ['status' => 'test', 'message' => 'Webhook uji diterima. Koneksi berfungsi.']]);
    }
}
