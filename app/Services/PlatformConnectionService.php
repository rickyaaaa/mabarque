<?php

namespace App\Services;

use App\Models\PlatformConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PlatformConnectionService
{
    public function listForUser(User $user): Collection
    {
        return $user->platformConnections()->latest('provider')->get();
    }

    public function save(User $user, array $attributes): PlatformConnection
    {
        $connection = $user->platformConnections()->updateOrCreate(
            ['provider' => $attributes['provider']],
            [
                'label' => $attributes['label'] ?? null,
                'webhook_url' => $attributes['webhook_url'],
                'signing_secret' => $attributes['signing_secret'] ?? null,
                'status' => 'paused',
                'last_error' => null,
            ]
        );

        return $connection->fresh();
    }

    public function pause(User $user, PlatformConnection $connection): PlatformConnection
    {
        abort_unless($connection->isOwnedBy($user), 403);

        $connection->forceFill(['status' => 'paused'])->save();

        return $connection->fresh();
    }
}
