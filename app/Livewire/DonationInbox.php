<?php

namespace App\Livewire;

use App\Models\Donation;
use App\Services\DonationQueueService;
use App\Services\LiveSessionService;
use App\Services\QueueService;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Kotak masuk donasi (/donations): donasi dari webhook, filter, tindakan untuk donasi yang perlu dicek
 * (masukkan ke antrean dengan paket pilihan / abaikan), dan notifikasi donasi masuk.
 */
class DonationInbox extends Component
{
    private const STATUSES = ['all', 'received', 'review', 'queued', 'reward', 'ignored'];

    private const PROVIDERS = ['all', 'saweria', 'sociabuzz', 'trakteer'];

    public string $status = 'all';

    public string $provider = 'all';

    public ?int $queueingId = null;

    /** Jumlah game untuk donasi yang dimasukkan manual (terisi otomatis dari kelipatan paket). */
    public int $queueMatches = 1;

    public string $queueMlbbId = '';

    public string $queuePlan = '';

    /** Paket reward yang cocok dengan nominal donasi yang sedang dicek (diusulkan di modal). */
    public ?int $queueRewardId = null;

    public function setStatus(string $status): void
    {
        $this->status = in_array($status, self::STATUSES, true) ? $status : 'all';
    }

    public function updatedProvider(string $value): void
    {
        $this->provider = in_array($value, self::PROVIDERS, true) ? $value : 'all';
    }

    /** Buka modal masukkan manual; jumlah game & ID MLBB diusulkan dari nominal dan pesan donasi. */
    public function startQueue(int $donationId, DonationQueueService $service): void
    {
        $donation = $this->donation($donationId);
        $suggestion = $service->suggestion($donation, auth()->user());

        $this->queueingId = $donation->id;
        $this->queueMatches = $suggestion['matches'];
        $this->queueMlbbId = (string) $suggestion['mlbb_id'];
        $this->queuePlan = $suggestion['description'];
        $this->queueRewardId = $suggestion['reward']?->id;
        $this->resetValidation();
    }

    public function cancelQueue(): void
    {
        $this->queueingId = null;
    }

    public function confirmQueue(DonationQueueService $service, LiveSessionService $sessions): void
    {
        $this->resetErrorBag();
        $this->validate([
            'queueMatches' => ['required', 'integer', 'min:1', 'max:'.QueueService::MAX_MATCHES],
            'queueMlbbId' => ['nullable', 'string', 'max:40'],
        ], [], ['queueMatches' => 'jumlah game', 'queueMlbbId' => 'ID MLBB']);

        try {
            // Tanpa sesi live pun boleh: pemain masuk daftar sisa game dan ikut ke sesi berikutnya.
            $donation = $service->queueManually($this->donation($this->queueingId), $sessions->queueSession(auth()->user()), $this->queueMatches, $this->queueMlbbId);
            $this->queueingId = null;
            $this->dispatch('notify', message: "{$donation->donor_name} masuk antrean dengan {$this->queueMatches} game.");
        } catch (InvalidArgumentException $exception) {
            $this->addError('queueMatches', $exception->getMessage());
        }
    }

    /** Catat donasi yang sedang dicek sebagai reward (mis. Follback IG), bukan game. */
    public function confirmReward(int $packageId, DonationQueueService $service): void
    {
        try {
            $package = auth()->user()->vipPackages()->whereKey($packageId)->firstOrFail();
            $donation = $service->markReward($this->donation($this->queueingId), $package);
            $this->queueingId = null;
            $this->dispatch('notify', message: "{$donation->donor_name} dicatat dapat reward: {$package->getsLabel()}.");
        } catch (InvalidArgumentException $exception) {
            $this->addError('queueMatches', $exception->getMessage());
        }
    }

    public function toggleRewardDone(int $donationId, DonationQueueService $service): void
    {
        try {
            $donation = $this->donation($donationId);
            $donation = $service->setRewardDone($donation, $donation->reward_done_at === null);
            $this->dispatch('notify', message: $donation->reward_done_at ? "Reward {$donation->donor_name} ditandai selesai." : "Reward {$donation->donor_name} dibuka lagi.");
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('notify', message: $exception->getMessage());
        }
    }

    public function ignore(int $donationId, DonationQueueService $service): void
    {
        try {
            $donation = $service->ignore($this->donation($donationId));
            $this->dispatch('notify', message: "Donasi {$donation->donor_name} ditandai tidak masuk antrean.");
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('notify', message: $exception->getMessage());
        }
    }

    public function markRead(int $notificationId): void
    {
        auth()->user()->donationNotifications()->whereKey($notificationId)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function markAllRead(): void
    {
        $count = auth()->user()->donationNotifications()->whereNull('read_at')->update(['read_at' => now()]);
        $this->dispatch('notify', message: $count ? "{$count} notifikasi ditandai dibaca." : 'Tidak ada notifikasi baru.');
    }

    public function render()
    {
        $user = auth()->user();
        $base = $user->donations()->with(['vipPackage:id,name,kind,reward_label,match_count', 'queueEntry:id,position,status']);
        $recent = (clone $base)->latest('received_at')->limit(200)->get();

        $visible = $recent
            ->when($this->status !== 'all', fn ($c) => $c->where('status', $this->status))
            ->when($this->provider !== 'all', fn ($c) => $c->where('provider', $this->provider))
            ->take(50)
            ->values();

        $session = $user->liveSessions()->active()->latest('started_at')->first();
        $connections = $user->platformConnections()->get();

        return view('livewire.donation-inbox', [
            'donations' => $visible,
            'counts' => collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => $s === 'all' ? $recent->count() : $recent->where('status', $s)->count()]),
            'todayCount' => $recent->filter(fn (Donation $d) => $d->received_at?->isToday())->count(),
            'needsCheck' => $recent->whereIn('status', ['received', 'review'])->count(),
            'session' => $session,
            // Semua uang donasi yang masuk (termasuk "Perlu cek"/"Diabaikan"): selama sesi live, atau hari ini.
            'donationValue' => (int) ($session
                ? $session->donations()->where('donations.status', '!=', 'refunded')->sum('amount')
                : $user->donations()->whereDate('received_at', today())->where('donations.status', '!=', 'refunded')->sum('amount')),
            'activeProviders' => $connections->where('status', 'active')->count(),
            'connectedProviders' => $connections->count(),
            'packages' => $this->activePackages(),
            'rewardPackages' => $this->activePackages()->filter(fn ($p) => $p->isReward())->values(),
            'queueing' => $this->queueingId ? $recent->firstWhere('id', $this->queueingId) : null,
            'notifications' => $user->donationNotifications()->latest()->limit(6)->get(),
            'unread' => $user->donationNotifications()->whereNull('read_at')->count(),
        ]);
    }

    private function activePackages()
    {
        return auth()->user()->vipPackages()->where('is_active', true)->orderBy('price')->get();
    }

    private function donation(?int $donationId): Donation
    {
        return auth()->user()->donations()->where('donations.id', $donationId)->firstOrFail();
    }
}
