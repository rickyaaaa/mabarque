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

    /**
     * Payload lolos verifikasi tapi tidak bisa dibaca. Tetap 2xx: balasan 4xx membuat SociaBuzz berhenti
     * mengirim donasi berikutnya (kejadian 2026-11-28) dan Trakteer mematikan webhook setelah 3x gagal.
     */
    public static function unreadable(string $reason): self
    {
        return new self(202, ['data' => ['status' => 'unreadable', 'reason' => $reason]]);
    }

    /** Error tak terduga saat memproses. Tetap 2xx; kiriman tercatat dan bisa diproses ulang (webhooks:replay). */
    public static function failed(): self
    {
        return new self(202, ['data' => ['status' => 'failed', 'reason' => 'Webhook diterima tapi gagal diproses. Tercatat untuk diproses ulang.']]);
    }

    /** Nama hasil untuk kolom webhook_deliveries.result. */
    public function result(): string
    {
        $status = (string) ($this->body['data']['status'] ?? '');

        return match (true) {
            in_array($status, ['test', 'ignored', 'unreadable', 'failed'], true) => $status,
            $this->donation !== null => ($this->body['data']['duplicate'] ?? false) ? 'duplicate' : 'donation',
            default => 'ignored',
        };
    }
}
