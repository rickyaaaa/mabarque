<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\LiveSession;
use App\Models\QueueEntry;
use App\Models\User;
use App\Models\VipPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Donasi → antrean.
 *
 * Jumlah game dihitung dari kelipatan paket (paket termahal yang muat dulu, sisa nominal yang tidak cukup
 * untuk paket termurah tidak dihitung). Apakah donasi LANGSUNG masuk antrean ditentukan mode streamer
 * (users.donation_queue_mode), supaya donasi biasa/saweran tidak ikut terhitung sebagai mabar.
 *
 * Paket "Reward lain" (mis. Follback IG 600rb) tidak pernah menjadi game: bila paket termahal yang muat
 * di nominal donasi adalah paket reward, donasi dicatat sebagai reward (sisa nominal tidak dihitung).
 */
class DonationQueueService
{
    public const MODES = [
        'smart' => 'Otomatis bila pesan berisi ID MLBB atau kata "mabar"/"vip"',
        'manual' => 'Selalu konfirmasi manual',
        'auto' => 'Otomatis semua donasi yang cukup nominalnya',
    ];

    /**
     * Rincian paket untuk sebuah nominal.
     * Contoh paket 25rb = 2 game & 50rb = 5 game: 75rb → 50rb + 25rb = 7 game; 60rb → 50rb = 5 game.
     *
     * @param  Collection<int, VipPackage>  $packages  paket aktif milik streamer
     * @return array{package: VipPackage, matches: int, parts: list<array{package: VipPackage, count: int}>}|null
     *         null bila nominal di bawah harga paket termurah
     */
    public static function plan(int $amount, Collection $packages): ?array
    {
        $remaining = $amount;
        $total = 0;
        $parts = [];

        // Harga sama: utamakan yang game-nya lebih banyak.
        foreach ($packages->filter(fn (VipPackage $p) => (int) $p->price > 0)->sortBy([['price', 'desc'], ['match_count', 'desc']]) as $package) {
            $count = intdiv($remaining, (int) $package->price);
            if ($count < 1) {
                continue;
            }

            $parts[] = ['package' => $package, 'count' => $count];
            $total += $count * (int) $package->match_count;
            $remaining -= $count * (int) $package->price;
        }

        return $parts !== [] && $total > 0 ? ['package' => $parts[0]['package'], 'matches' => $total, 'parts' => $parts] : null;
    }

    /** @return array{0: VipPackage, 1: int}|null  [paket terbesar yang terpakai, total game] */
    public static function matchesFor(int $amount, Collection $packages): ?array
    {
        $plan = self::plan($amount, $packages);

        return $plan ? [$plan['package'], $plan['matches']] : null;
    }

    /** Teks rincian untuk streamer, mis. "Rp 50.000 + Rp 25.000 = 7 game". */
    public static function describe(?array $plan): string
    {
        if ($plan === null) {
            return 'Di bawah harga paket termurah';
        }

        return collect($plan['parts'])
            ->map(fn (array $part) => ($part['count'] > 1 ? $part['count'].'× ' : '').'Rp '.number_format((int) $part['package']->price, 0, ',', '.'))
            ->join(' + ').' = '.$plan['matches'].' game';
    }

    /** ID MLBB dari pesan donasi: "1185010926", "1185010926 (2345)", "ID: 1185010926(2345)". */
    public static function mlbbIdFrom(?string $message): ?string
    {
        if (! preg_match('/(?<!\d)(\d{6,12})(?!\d)(?:\s*[(\[]\s*(\d{3,6})\s*[)\]])?/', (string) $message, $match)) {
            return null;
        }

        return isset($match[2]) && $match[2] !== '' ? "{$match[1]} ({$match[2]})" : $match[1];
    }

    /** Pesan donasi menandakan pembelian mabar: berisi ID MLBB atau kata "mabar"/"vip". */
    public static function wantsMabar(?string $message): bool
    {
        return self::mlbbIdFrom($message) !== null || preg_match('/\b(mabar|vip)\b/i', (string) $message) === 1;
    }

    /**
     * Paket reward untuk sebuah nominal: hanya bila paket TERMAHAL yang muat (dari semua paket aktif)
     * adalah paket reward. Contoh mabar 25rb & 50rb + Follback IG 600rb: 625rb → Follback IG; 550rb → mabar.
     *
     * @param  Collection<int, VipPackage>  $packages  semua paket aktif (mabar & reward)
     */
    public static function rewardFor(int $amount, Collection $packages): ?VipPackage
    {
        $best = $packages->filter(fn (VipPackage $p) => (int) $p->price > 0 && (int) $p->price <= $amount)
            ->sortBy([['price', 'desc'], ['kind', 'desc']])
            ->first();

        return $best?->isReward() ? $best : null;
    }

    /** Usulan untuk donasi yang dimasukkan manual: jumlah game dari kelipatan paket + ID dari pesan. */
    public function suggestion(Donation $donation, User $owner): array
    {
        $plan = self::plan((int) $donation->amount, $this->activePackages($owner));

        return [
            'matches' => $plan['matches'] ?? 1,
            'description' => self::describe($plan),
            'mlbb_id' => self::mlbbIdFrom($donation->message),
            'reward' => self::rewardFor((int) $donation->amount, $this->allActivePackages($owner)),
        ];
    }

    /**
     * Catat donasi sebagai reward (mis. Follback IG) — dipanggil otomatis atau oleh streamer dari "Perlu cek".
     *
     * @throws InvalidArgumentException donasi sudah diproses atau paket bukan reward milik streamer
     */
    public function markReward(Donation $donation, VipPackage $package): Donation
    {
        return DB::transaction(function () use ($donation, $package): Donation {
            $donation = Donation::query()->with('platformConnection')->lockForUpdate()->findOrFail($donation->id);

            if (! in_array($donation->status, ['received', 'review'], true) || $donation->queue_entry_id !== null) {
                throw new InvalidArgumentException('Donasi ini sudah diproses.');
            }

            if (! $package->isReward() || (int) $package->user_id !== (int) $donation->platformConnection?->user_id) {
                throw new InvalidArgumentException('Paket reward tidak valid.');
            }

            return $this->applyReward($donation, $package);
        });
    }

    /** Tandai reward sudah diberikan (atau batalkan tanda itu). */
    public function setRewardDone(Donation $donation, bool $done = true): Donation
    {
        if ($donation->status !== 'reward') {
            throw new InvalidArgumentException('Donasi ini bukan reward.');
        }

        $donation->forceFill(['reward_done_at' => $done ? now() : null])->save();

        return $donation->fresh();
    }

    /**
     * Masukkan donasi ke antrean secara manual dengan jumlah game pilihan streamer (biasanya usulan
     * otomatis). Boleh tanpa sesi live: pemain masuk daftar sisa game dan ikut ke sesi berikutnya.
     *
     * @throws InvalidArgumentException donasi sudah diproses, sesi bukan milik streamer, atau data tidak valid
     */
    public function queueManually(Donation $donation, LiveSession $session, int $matches, ?string $mlbbId = null): Donation
    {
        $mlbbId = trim((string) preg_replace('/\s+/', ' ', (string) $mlbbId));

        if ($matches < 1 || $matches > QueueService::MAX_MATCHES) {
            throw new InvalidArgumentException('Jumlah game harus 1–'.QueueService::MAX_MATCHES.'.');
        }

        if ($mlbbId !== '' && ! preg_match('/^\d{4,20}( ?\(?\d{1,8}\)?)?$/', $mlbbId)) {
            throw new InvalidArgumentException('ID MLBB harus angka, boleh dengan server, mis. 1185010926 (2345).');
        }

        return DB::transaction(function () use ($donation, $session, $matches, $mlbbId): Donation {
            $donation = Donation::query()->with('platformConnection')->lockForUpdate()->findOrFail($donation->id);
            $ownerId = (int) $donation->platformConnection?->user_id;

            if (! in_array($donation->status, ['received', 'review'], true) || $donation->queue_entry_id !== null) {
                throw new InvalidArgumentException('Donasi ini sudah diproses.');
            }

            if ($ownerId !== (int) $session->user_id) {
                throw new InvalidArgumentException('Sesi tidak valid untuk donasi ini.');
            }

            $plan = self::plan((int) $donation->amount, $this->activePackages($session->user));

            return $this->queue($donation, $session, $matches, $mlbbId !== '' ? $mlbbId : null, $plan['package'] ?? null);
        });
    }

    /** Tandai donasi tidak masuk antrean (mis. donasi biasa, bukan VIP). */
    public function ignore(Donation $donation): Donation
    {
        if (! in_array($donation->status, ['received', 'review'], true)) {
            throw new InvalidArgumentException('Donasi ini sudah diproses.');
        }

        $donation->forceFill(['status' => 'ignored', 'processed_at' => now()])->save();

        return $donation->fresh();
    }

    /** Proses donasi baru dari webhook sesuai mode streamer. */
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

            // Sesi aktif, atau sesi penampung daftar sisa game saat streamer belum live.
            $session = $donation->liveSession()->with('user')->lockForUpdate()->first();
            if ($session === null) {
                return $donation->fresh();
            }

            $packages = $this->activePackages($session->user);
            $mode = array_key_exists((string) $session->user?->donation_queue_mode, self::MODES) ? $session->user->donation_queue_mode : 'smart';

            // Paket reward (mis. Follback IG) cocok: dicatat sebagai reward, tidak pernah jadi game.
            // Mode manual tetap minta streamer mengecek dulu.
            if ($reward = self::rewardFor((int) $donation->amount, $this->allActivePackages($session->user))) {
                return $mode === 'manual' ? $this->markReview($donation) : $this->applyReward($donation, $reward);
            }

            // Belum ada paket aktif: tidak bisa dihitung → streamer yang memutuskan.
            if ($packages->isEmpty()) {
                return $this->markReview($donation);
            }

            $plan = self::plan((int) $donation->amount, $packages);

            // Di bawah harga paket termurah: bukan donasi VIP, tetap tercatat tanpa masuk antrean.
            if ($plan === null) {
                $donation->forceFill(['status' => 'ignored', 'processed_at' => now()])->save();

                return $donation->fresh();
            }

            if ($mode === 'manual' || ($mode === 'smart' && ! self::wantsMabar($donation->message))) {
                return $this->markReview($donation);
            }

            return $this->queue($donation, $session, $plan['matches'], self::mlbbIdFrom($donation->message), $plan['package']);
        });
    }

    private function queue(Donation $donation, LiveSession $session, int $matches, ?string $mlbbId, ?VipPackage $package): Donation
    {
        // reorder(): relasi queueEntries punya ORDER BY, yang ditolak MySQL (ONLY_FULL_GROUP_BY) di query agregat.
        $entry = QueueEntry::query()->create([
            'live_session_id' => $session->id,
            'vip_package_id' => $package?->id,
            'player_name' => $donation->donor_name,
            'mlbb_id' => $mlbbId,
            'provider' => $donation->provider,
            'donation_amount' => $donation->amount,
            'matches_total' => $matches,
            'matches_played' => 0,
            'status' => 'ready',
            'position' => ((int) $session->queueEntries()->reorder()->max('position')) + 1,
            'joined_at' => now(),
        ]);

        $donation->forceFill([
            'live_session_id' => $session->id,
            'vip_package_id' => $package?->id,
            'queue_entry_id' => $entry->id,
            'status' => 'queued',
            'processed_at' => now(),
        ])->save();

        $session->increment('total_players');
        $session->increment('total_donation', $donation->amount);

        return $donation->fresh();
    }

    private function applyReward(Donation $donation, VipPackage $package): Donation
    {
        $donation->forceFill(['status' => 'reward', 'vip_package_id' => $package->id, 'processed_at' => now(), 'reward_done_at' => null])->save();

        return $donation->fresh();
    }

    private function markReview(Donation $donation): Donation
    {
        $donation->forceFill(['status' => 'review'])->save();

        return $donation->fresh();
    }

    /** Paket mabar aktif (dasar hitungan game). @return Collection<int, VipPackage> */
    private function activePackages(?User $owner): Collection
    {
        return $this->allActivePackages($owner)->reject(fn (VipPackage $p) => $p->isReward())->values();
    }

    /** Semua paket aktif, termasuk reward. @return Collection<int, VipPackage> */
    private function allActivePackages(?User $owner): Collection
    {
        return $owner === null ? collect() : $owner->vipPackages()->where('is_active', true)->where('price', '>', 0)->get();
    }
}
