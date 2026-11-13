<?php

namespace App\Services;

use App\Models\PlatformConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class PlatformConnectionService
{
    public function listForUser(User $user): Collection
    {
        return $user->platformConnections()->latest('provider')->get();
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
            'webhook_url' => $attributes['webhook_url'],
            'status' => 'paused',
            'last_error' => null,
        ];

        if (filled($secret)) {
            $values['signing_secret'] = $secret;
        }

        $connection = $user->platformConnections()->updateOrCreate(['provider' => $attributes['provider']], $values);

        return $connection->fresh();
    }

    public function pause(User $user, PlatformConnection $connection): PlatformConnection
    {
        abort_unless($connection->isOwnedBy($user), 403);

        $connection->forceFill(['status' => 'paused'])->save();

        return $connection->fresh();
    }
}
