<?php

namespace App\Livewire;

use App\Models\Donation;
use App\Models\VipPackage;
use App\Services\DonationQueueService;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Kotak masuk donasi (/donations): donasi dari webhook, filter, tindakan untuk donasi yang perlu dicek
 * (masukkan ke antrean dengan paket pilihan / abaikan), dan notifikasi donasi masuk.
 */
class DonationInbox extends Component
{
    private const STATUSES = ['all', 'received', 'review', 'queued', 'ignored'];

    private const PROVIDERS = ['all', 'saweria', 'sociabuzz', 'trakteer'];

    public string $status = 'all';

    public string $provider = 'all';

    public ?int $queueingId = null;

    public ?int $packageId = null;

    public function setStatus(string $status): void
    {
        $this->status = in_array($status, self::STATUSES, true) ? $status : 'all';
    }

    public function updatedProvider(string $value): void
    {
        $this->provider = in_array($value, self::PROVIDERS, true) ? $value : 'all';
    }

    /** Buka modal pilih paket; paket yang disarankan = harga tertinggi yang ≤ nominal donasi. */
    public function startQueue(int $donationId): void
    {
        $donation = $this->donation($donationId);
        $packages = $this->activePackages();

        $this->queueingId = $donation->id;
        $this->packageId = $packages->where('price', '<=', $donation->amount)->sortByDesc('price')->first()?->id
            ?? $packages->first()?->id;
        $this->resetValidation();
    }

    public function cancelQueue(): void
    {
        $this->queueingId = null;
    }

    public function confirmQueue(DonationQueueService $service): void
    {
        $this->resetErrorBag(); // jangan tampilkan error percobaan sebelumnya (mis. sesi baru saja dimulai)
        $session = auth()->user()->liveSessions()->active()->latest('started_at')->first();
        $package = $this->packageId ? auth()->user()->vipPackages()->whereKey($this->packageId)->first() : null;

        if ($session === null) {
            $this->addError('packageId', 'Mulai sesi live dulu sebelum memasukkan pemain ke antrean.');

            return;
        }

        if ($package === null) {
            $this->addError('packageId', 'Pilih paket VIP.');

            return;
        }

        try {
            $donation = $service->queueWithPackage($this->donation($this->queueingId), $package, $session);
            $this->queueingId = null;
            $this->dispatch('notify', message: "{$donation->donor_name} masuk antrean dengan paket {$package->name}.");
        } catch (InvalidArgumentException $exception) {
            $this->addError('packageId', $exception->getMessage());
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
        $base = $user->donations()->with(['vipPackage:id,name', 'queueEntry:id,position,status']);
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
            'activeProviders' => $connections->where('status', 'active')->count(),
            'connectedProviders' => $connections->count(),
            'packages' => $this->activePackages(),
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
