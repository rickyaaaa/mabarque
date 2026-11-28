<?php

namespace App\Livewire;

use App\Models\Donation;
use App\Models\WebhookDelivery;
use App\Services\DonationQueueService;
use App\Services\DonationStatsService;
use App\Services\DonationWebhookService;
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

    /** Donasi per halaman — sama dengan daftar antrean (QueueBoard::PER_PAGE). */
    public const PER_PAGE = 10;

    public string $status = 'all';

    public string $provider = 'all';

    public int $page = 1;

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
        $this->page = 1;
    }

    public function updatedProvider(string $value): void
    {
        $this->provider = in_array($value, self::PROVIDERS, true) ? $value : 'all';
        $this->page = 1;
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page); // batas atas dijaga di render()
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

    /** Proses ulang webhook yang gagal dibaca (mis. setelah format provider didukung). */
    public function replayDelivery(int $deliveryId, DonationWebhookService $service): void
    {
        $delivery = auth()->user()->webhookDeliveries()->where('webhook_deliveries.id', $deliveryId)->firstOrFail();

        try {
            $result = $service->replayDelivery($delivery);
            $this->dispatch('notify', message: in_array($result->result(), WebhookDelivery::PROBLEMS, true)
                ? 'Webhook masih belum bisa dibaca. Masukkan donasinya manual dari papan antrean, lalu tandai selesai.'
                : 'Webhook diproses ulang — donasi '.($result->donation?->donor_name ?? '').' kini tercatat.');
        } catch (\Throwable $exception) {
            report($exception);
            $this->dispatch('notify', message: 'Gagal memproses ulang webhook. Coba lagi sebentar.');
        }
    }

    /** Tandai webhook gagal sudah ditangani (mis. donasinya sudah dimasukkan manual). */
    public function resolveDelivery(int $deliveryId): void
    {
        auth()->user()->webhookDeliveries()->where('webhook_deliveries.id', $deliveryId)->firstOrFail()
            ->forceFill(['resolved_at' => now()])->save();
        $this->dispatch('notify', message: 'Webhook ditandai sudah ditangani.');
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

        $filtered = (clone $base)
            ->when($this->status !== 'all', fn ($q) => $q->where('donations.status', $this->status))
            ->when($this->provider !== 'all', fn ($q) => $q->where('donations.provider', $this->provider));
        $filteredCount = (clone $filtered)->reorder()->count();
        $lastPage = max(1, (int) ceil($filteredCount / self::PER_PAGE));
        // Halaman tidak boleh melewati yang ada (mis. donasi berkurang setelah filter/abaikan).
        $this->page = min(max(1, $this->page), $lastPage);

        $visible = $filtered
            ->latest('received_at')->latest('donations.id')
            ->forPage($this->page, self::PER_PAGE)
            ->get();

        // Hitungan filter dari seluruh donasi (bukan hanya 200 baris terakhir).
        $statusCounts = $user->donations()->reorder()
            ->when($this->provider !== 'all', fn ($q) => $q->where('donations.provider', $this->provider))
            ->toBase()->selectRaw('donations.status as status, count(*) as aggregate')->groupBy('donations.status')
            ->pluck('aggregate', 'status');

        $session = $user->liveSessions()->active()->latest('started_at')->first();
        $connections = $user->platformConnections()->get();
        $stats = app(DonationStatsService::class);

        return view('livewire.donation-inbox', [
            'donations' => $visible,
            'filteredCount' => $filteredCount,
            'lastPage' => $lastPage,
            'perPage' => self::PER_PAGE,
            'counts' => collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($s === 'all' ? $statusCounts->sum() : ($statusCounts[$s] ?? 0))]),
            'needsCheck' => (int) $user->donations()->whereIn('donations.status', ['received', 'review'])->count(),
            'session' => $session,
            // Selama live: angka sesi (tidak ter-reset jam 00:00 di tengah live). Tanpa live: hari ini.
            'donationStats' => $session ? $stats->forSession($session) : $stats->forToday($user),
            'activeProviders' => $connections->where('status', 'active')->count(),
            'connectedProviders' => $connections->count(),
            'syncStatus' => $this->syncStatus($connections),
            'failedDeliveries' => $user->webhookDeliveries()->unresolvedProblems()->with('platformConnection:id,provider')
                ->latest('webhook_deliveries.created_at')->limit(10)->get(),
            'failedDeliveryCount' => $user->webhookDeliveries()->unresolvedProblems()->count(),
            'packages' => $this->activePackages(),
            'rewardPackages' => $this->activePackages()->filter(fn ($p) => $p->isReward())->values(),
            'queueing' => $this->queueingId ? $user->donations()->where('donations.id', $this->queueingId)->first() : null,
            'notifications' => $user->donationNotifications()->latest()->limit(6)->get(),
            'unread' => $user->donationNotifications()->whereNull('read_at')->count(),
        ]);
    }

    /**
     * Teks status webhook di bawah judul. "Menunggu webhook pertama" hanya bila belum pernah ada kiriman;
     * bila ada error, tampilkan pesan provider + waktunya.
     *
     * @return array{tone: string, text: string}
     */
    private function syncStatus($connections): array
    {
        $labels = ['saweria' => 'Saweria', 'sociabuzz' => 'SociaBuzz', 'trakteer' => 'Trakteer'];

        if ($connections->isEmpty()) {
            return ['tone' => 'idle', 'text' => 'Belum ada koneksi provider'];
        }

        if ($problem = $connections->filter(fn ($c) => filled($c->last_error))->sortByDesc('updated_at')->first()) {
            return ['tone' => 'error', 'text' => ($labels[$problem->provider] ?? $problem->provider).': '.$problem->last_error];
        }

        $last = $connections->filter(fn ($c) => $c->last_received_at)->sortByDesc('last_received_at')->first();

        return $last
            ? ['tone' => 'live', 'text' => 'Webhook aktif · terakhir diterima '.$last->last_received_at->format('H:i').' WIB ('.$last->last_received_at->diffForHumans().')']
            : ['tone' => 'idle', 'text' => 'Menunggu webhook pertama'];
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
