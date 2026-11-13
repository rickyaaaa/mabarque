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
        /** Payload uji dari dashboard provider (mis. tombol "Send Webhook Test"): tidak masuk antrean. */
        public readonly bool $isTest = false,
    ) {
    }
}
