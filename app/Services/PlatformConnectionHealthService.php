<?php

namespace App\Services;

use App\Models\PlatformConnection;
use Illuminate\Support\Facades\Http;

class PlatformConnectionHealthService
{
    public function check(PlatformConnection $connection): PlatformConnection
    {
        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->acceptJson()
                ->head($connection->webhook_url);

            $status = $response->successful() || $response->status() === 405 ? 'active' : 'error';
            $error = $status === 'active' ? null : "Endpoint merespons HTTP {$response->status()}";
        } catch (\Throwable $exception) {
            $status = 'error';
            $error = 'Endpoint tidak dapat dihubungi.';
        }

        $connection->forceFill([
            'status' => $status,
            'last_tested_at' => now(),
            'last_error' => $error,
        ])->save();

        return $connection->fresh();
    }
}
