<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\QueueEntry;
use App\Models\User;
use App\Models\VipPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VipPackageService
{
    public function listForUser(User $user, bool $activeOnly = false): Collection
    {
        $query = $user->vipPackages()->orderBy('sort_order')->orderBy('id');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function add(User $user, array $attributes): VipPackage
    {
        $attributes['sort_order'] ??= ((int) $user->vipPackages()->max('sort_order')) + 1;
        $attributes['is_active'] ??= true;

        return $user->vipPackages()->create($attributes);
    }

    public function update(User $user, VipPackage $package, array $attributes): VipPackage
    {
        if ((int) $package->user_id !== (int) $user->id) {
            abort(404);
        }

        $package->update($attributes);

        return $package->refresh();
    }

    public function setActive(User $user, VipPackage $package, bool $active): VipPackage
    {
        if ((int) $package->user_id !== (int) $user->id) {
            abort(404);
        }

        $package->update(['is_active' => $active]);

        return $package->refresh();
    }

    /**
     * Hapus paket. Riwayat antrean & donasi yang pernah memakai paket ini tetap ada,
     * hanya tautan ke paketnya dilepas (jumlah game yang sudah tercatat tidak berubah).
     */
    public function delete(User $user, VipPackage $package): void
    {
        if ((int) $package->user_id !== (int) $user->id) {
            abort(404);
        }

        DB::transaction(function () use ($package): void {
            QueueEntry::where('vip_package_id', $package->id)->update(['vip_package_id' => null]);
            Donation::where('vip_package_id', $package->id)->update(['vip_package_id' => null]);
            $package->delete();
        });
    }
}
