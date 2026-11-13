<?php

namespace App\Services;

use App\Models\PlatformConnection;
use Illuminate\Support\Facades\Http;

class PlatformConnectionHealthService
{
    public function check(PlatformConnection $connection): PlatformConnection
    {
        $blocked = $this->blockedReason((string) $connection->webhook_url);

        if ($blocked !== null) {
            $status = 'error';
            $error = $blocked;
        } else {
            try {
                $response = Http::timeout(5)
                    ->connectTimeout(3)
                    ->withoutRedirecting() // redirect bisa membelokkan request ke alamat internal
                    ->acceptJson()
                    ->head($connection->webhook_url);

                $status = $response->successful() || $response->status() === 405 ? 'active' : 'error';
                $error = $status === 'active' ? null : "Endpoint merespons HTTP {$response->status()}";
            } catch (\Throwable $exception) {
                $status = 'error';
                $error = 'Endpoint tidak dapat dihubungi.';
            }
        }

        $connection->forceFill([
            'status' => $status,
            'last_tested_at' => now(),
            'last_error' => $error,
        ])->save();

        return $connection->fresh();
    }

    /**
     * Cegah SSRF: URL buatan streamer tidak boleh membuat server memanggil alamat internal
     * (localhost, jaringan privat, link-local/metadata cloud, alamat reserved).
     */
    private function blockedReason(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? null;

        if ($host === null || ! in_array($scheme, ['http', 'https'], true)) {
            return 'URL webhook tidak valid.';
        }

        if (app()->isProduction() && $scheme !== 'https') {
            return 'URL webhook wajib memakai HTTPS.';
        }

        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : array_merge(gethostbynamel($host) ?: [], array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'));

        if ($ips === []) {
            return 'Host webhook tidak dapat di-resolve.';
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return 'URL webhook mengarah ke alamat internal dan tidak diizinkan.';
            }
        }

        return null;
    }
}
