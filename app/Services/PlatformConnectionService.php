<?php

namespace App\Services;

use App\Models\PlatformConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Koneksi provider donasi milik streamer. Status: pending (belum pernah menerima webhook),
 * active, paused (dijeda streamer — webhook ditolak), error.
 */
class PlatformConnectionService
{
    public function listForUser(User $user): Collection
    {
        return $user->platformConnections()->latest('provider')->get();
    }

    /** URL yang ditempel streamer di dashboard provider (Saweria/SociaBuzz/Trakteer). */
    public function webhookEndpoint(PlatformConnection $connection): string
    {
        return url("/api/webhooks/donations/{$connection->provider}/{$connection->id}");
    }

    /**
     * Simpan koneksi per provider. Signing secret wajib saat koneksi pertama kali dibuat;
     * saat diperbarui tanpa secret baru, secret lama dipertahankan (tidak terhapus).
     */
    public function save(User $user, array $attributes): PlatformConnection
    {
        $existing = $user->platformConnections()->where('provider', $attributes['provider'])->first();
        $secret = $attributes['signing_secret'] ?? null;

        if (blank($secret) && blank($existing?->signing_secret)) {
            throw ValidationException::withMessages([
                'signing_secret' => 'Signing secret wajib diisi supaya webhook donasi bisa diverifikasi.',
            ]);
        }

        $values = [
            'label' => $attributes['label'] ?? null,
            'webhook_url' => $attributes['webhook_url'] ?? $existing?->webhook_url ?? '',
            'last_error' => null,
        ];

        if ($existing === null) {
            $values['status'] = 'pending';
        }

        if (filled($secret)) {
            $values['signing_secret'] = $secret;
        }

        $connection = $user->platformConnections()->updateOrCreate(['provider' => $attributes['provider']], $values);

        if (blank($connection->webhook_url)) {
            $connection->forceFill(['webhook_url' => $this->webhookEndpoint($connection)])->save();
        }

        return $connection->fresh();
    }

    public function pause(User $user, PlatformConnection $connection): PlatformConnection
    {
        abort_unless($connection->isOwnedBy($user), 403);

        $connection->forceFill(['status' => 'paused'])->save();

        return $connection->fresh();
    }

    /** Lanjutkan koneksi yang dijeda: aktif bila pernah menerima webhook, selain itu kembali pending. */
    public function resume(User $user, PlatformConnection $connection): PlatformConnection
    {
        abort_unless($connection->isOwnedBy($user), 403);

        $connection->forceFill(['status' => $connection->last_received_at ? 'active' : 'pending'])->save();

        return $connection->fresh();
    }
}
