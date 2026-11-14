<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\QueueEntry;
use App\Models\VipPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DonationQueueService
{
    /**
     * Masukkan donasi yang perlu dicek (nominal tidak cocok / masuk sebelum sesi dibuka) ke antrean
     * sesi aktif secara manual dengan paket pilihan streamer.
     *
     * @throws \InvalidArgumentException donasi sudah diproses, atau paket/sesi bukan milik streamer yang sama
     */
    public function queueWithPackage(Donation $donation, VipPackage $package, \App\Models\LiveSession $session): Donation
    {
        return DB::transaction(function () use ($donation, $package, $session): Donation {
            $donation = Donation::query()->with('platformConnection')->lockForUpdate()->findOrFail($donation->id);
            $ownerId = (int) $donation->platformConnection?->user_id;

            if (! in_array($donation->status, ['received', 'review'], true) || $donation->queue_entry_id !== null) {
                throw new \InvalidArgumentException('Donasi ini sudah diproses.');
            }

            if ($ownerId !== (int) $session->user_id || $ownerId !== (int) $package->user_id || $session->status !== 'active') {
                throw new \InvalidArgumentException('Paket atau sesi tidak valid untuk donasi ini.');
            }

            $entry = QueueEntry::query()->create([
                'live_session_id' => $session->id,
                'vip_package_id' => $package->id,
                'player_name' => $donation->donor_name,
                'provider' => $donation->provider,
                'donation_amount' => $donation->amount,
                'matches_total' => $package->match_count,
                'matches_played' => 0,
                'status' => 'ready',
                'position' => ((int) $session->queueEntries()->reorder()->max('position')) + 1,
                'joined_at' => now(),
            ]);

            $donation->forceFill([
                'live_session_id' => $session->id,
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

    /** Tandai donasi tidak masuk antrean (mis. donasi biasa, bukan VIP). */
    public function ignore(Donation $donation): Donation
    {
        if (! in_array($donation->status, ['received', 'review'], true)) {
            throw new \InvalidArgumentException('Donasi ini sudah diproses.');
        }

        $donation->forceFill(['status' => 'ignored', 'processed_at' => now()])->save();

        return $donation->fresh();
    }

    /**
     * Hitung jatah game dari nominal donasi: ambil paket termahal yang masih muat, ulangi sampai sisa
     * nominal di bawah harga paket termurah (sisa itu tidak dihitung).
     * Contoh paket 25rb = 2 game & 50rb = 5 game: 50rb → 5, 75rb → 5 + 2 = 7, 100rb → 10, 60rb → 5.
     *
     * @param  Collection<int, VipPackage>  $packages  paket aktif milik streamer
     * @return array{0: VipPackage, 1: int}|null  [paket terbesar yang terpakai, total game]; null bila di bawah harga termurah
     */
    public static function matchesFor(int $amount, Collection $packages): ?array
    {
        $remaining = $amount;
        $total = 0;
        $main = null;

        // Harga sama: utamakan yang game-nya lebih banyak.
        foreach ($packages->sortBy([['price', 'desc'], ['match_count', 'desc']]) as $package) {
            $count = intdiv($remaining, (int) $package->price);
            if ($count < 1) {
                continue;
            }

            $main ??= $package;
            $total += $count * (int) $package->match_count;
            $remaining -= $count * (int) $package->price;
        }

        return $main !== null && $total > 0 ? [$main, $total] : null;
    }

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

            $packages = VipPackage::query()
                ->where('user_id', $session->user_id)
                ->where('is_active', true)
                ->where('price', '>', 0)
                ->get();

            // Belum ada paket aktif: tidak bisa dihitung otomatis → streamer yang memutuskan.
            if ($packages->isEmpty()) {
                $donation->forceFill(['status' => 'review'])->save();

                return $donation->fresh();
            }

            $result = self::matchesFor((int) $donation->amount, $packages);

            // Di bawah harga paket termurah: bukan donasi VIP, tetap tercatat di kotak masuk tanpa masuk antrean.
            if ($result === null) {
                $donation->forceFill(['status' => 'ignored', 'processed_at' => now()])->save();

                return $donation->fresh();
            }

            [$package, $matchesTotal] = $result;

            // reorder(): relasi queueEntries punya ORDER BY, yang ditolak MySQL (ONLY_FULL_GROUP_BY) di query agregat.
            $position = ((int) $session->queueEntries()->reorder()->max('position')) + 1;
            $entry = QueueEntry::query()->create([
                'live_session_id' => $session->id,
                'vip_package_id' => $package->id,
                'player_name' => $donation->donor_name,
                'provider' => $donation->provider,
                'donation_amount' => $donation->amount,
                'matches_total' => $matchesTotal,
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
