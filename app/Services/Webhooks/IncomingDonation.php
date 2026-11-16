<?php

namespace App\Services\Webhooks;

/** Donasi hasil normalisasi payload provider — bentuk yang sama untuk Saweria, Trakteer, dan SociaBuzz. */
final class IncomingDonation
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $donorName,
        public readonly int $amount,
        public readonly ?string $donorEmail = null,
        public readonly ?string $message = null,
        public readonly ?string $currency = 'IDR',
        /** Nominal asli bila mata uangnya bukan rupiah (mis. 25 untuk 25 MYR); `amount` tetap rupiah. */
        public readonly ?float $originalAmount = null,
        /** Payload uji dari dashboard provider (mis. tombol "Send Webhook Test"): tidak masuk antrean. */
        public readonly bool $isTest = false,
    ) {
    }
}
