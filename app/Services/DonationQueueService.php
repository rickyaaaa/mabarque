<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\QueueEntry;
use App\Models\VipPackage;
use Illuminate\Support\Facades\DB;

class DonationQueueService
{
    public function process(Donation $donation): Donation
    {
        if ($donation->queue_entry_id !== null || $donation->live_session_id === null) {
            return $donation->fresh();
        }

        return DB::transaction(function () use ($donation): Donation {
            $donation = Donation::query()->lockForUpdate()->findOrFail($donation->id);

            if ($donation->queue_entry_id !== null || $donation->live_session_id === null) {
                return $donation->fresh();
            }

            $session = $donation->liveSession()->lockForUpdate()->first();
            if ($session === null || $session->status !== 'active') {
                return $donation->fresh();
            }

            $package = VipPackage::query()
                ->where('user_id', $session->user_id)
                ->where('is_active', true)
                ->where('price', $donation->amount)
                ->orderBy('sort_order')
                ->first();

            if ($package === null) {
                $donation->forceFill(['status' => 'review'])->save();

                return $donation->fresh();
            }

            // reorder(): relasi queueEntries punya ORDER BY, yang ditolak MySQL (ONLY_FULL_GROUP_BY) di query agregat.
            $position = ((int) $session->queueEntries()->reorder()->max('position')) + 1;
            $entry = QueueEntry::query()->create([
                'live_session_id' => $session->id,
                'vip_package_id' => $package->id,
                'player_name' => $donation->donor_name,
                'provider' => $donation->provider,
                'donation_amount' => $donation->amount,
                'matches_total' => $package->match_count,
                'matches_played' => 0,
                'status' => 'ready',
                'position' => $position,
                'joined_at' => now(),
            ]);

            $donation->forceFill([
                'vip_package_id' => $package->id,
                'queue_entry_id' => $entry->id,
                'status' => 'queued',
                'processed_at' => now(),
            ])->save();

            $session->increment('total_players');
            $session->increment('total_donation', $donation->amount);

            return $donation->fresh();
        });
    }
}
