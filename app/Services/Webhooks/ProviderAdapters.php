<?php

namespace App\Services\Webhooks;

use InvalidArgumentException;

/** Pilih adapter format webhook berdasarkan provider koneksi. */
class ProviderAdapters
{
    public function for(string $provider): ProviderAdapter
    {
        return match ($provider) {
            'saweria' => new SaweriaAdapter(),
            'trakteer' => new TrakteerAdapter(),
            'sociabuzz' => new SociaBuzzAdapter(),
            default => throw new InvalidArgumentException("Provider {$provider} tidak didukung."),
        };
    }
}
