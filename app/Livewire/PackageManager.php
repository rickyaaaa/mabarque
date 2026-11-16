<?php

namespace App\Livewire;

use App\Models\VipPackage;
use App\Services\DonationLinkService;
use App\Services\VipPackageService;
use InvalidArgumentException;
use Livewire\Component;

/** Paket VIP (/vip-packages): buat, ubah, aktif/nonaktifkan, dan hapus paket, plus link donasi untuk penonton. */
class PackageManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public int|string $price = 25000;

    public int|string $matchCount = 1;

    /** mabar = harga → jumlah game; reward = harga → hadiah lain (mis. Follback IG). */
    public string $kind = 'mabar';

    public string $rewardLabel = '';

    /** Link halaman donasi per platform (kosong = tidak dipakai) dan platform tombol utama. */
    public array $links = ['sociabuzz' => '', 'saweria' => '', 'trakteer' => ''];

    public string $primaryLink = 'sociabuzz';

    public function mount(DonationLinkService $service): void
    {
        $settings = $service->settings(auth()->user());
        $this->links = array_merge($this->links, $settings['urls']);
        $this->primaryLink = $settings['primary'] ?? 'sociabuzz';
    }

    public function saveLinks(DonationLinkService $service): void
    {
        $this->resetErrorBag('links');

        try {
            $saved = $service->save(auth()->user(), $this->links, $this->primaryLink);
        } catch (InvalidArgumentException $exception) {
            $this->addError('links', $exception->getMessage());

            return;
        }

        $this->links = array_merge(['sociabuzz' => '', 'saweria' => '', 'trakteer' => ''], $saved['urls']);
        $this->primaryLink = $saved['primary'] ?? 'sociabuzz';
        $this->dispatch('notify', message: $saved['urls'] === []
            ? 'Link donasi dikosongkan. Kartu paket di halaman viewer tidak mengarah ke mana pun.'
            : 'Link donasi disimpan. Viewer diarahkan ke '.DonationLinkService::PLATFORMS[$this->primaryLink]['label'].' saat memilih paket.');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->fill(['editingId' => null, 'name' => '', 'price' => 25000, 'matchCount' => 1, 'kind' => 'mabar', 'rewardLabel' => '', 'showForm' => true]);
    }

    public function edit(int $packageId): void
    {
        $package = $this->package($packageId);
        $this->resetValidation();
        $this->fill(['editingId' => $package->id, 'name' => $package->name, 'price' => $package->price, 'matchCount' => $package->match_count ?: 1, 'kind' => $package->isReward() ? 'reward' : 'mabar', 'rewardLabel' => (string) $package->reward_label, 'showForm' => true]);
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    public function save(VipPackageService $service): void
    {
        $this->kind = $this->kind === 'reward' ? 'reward' : 'mabar';
        $reward = $this->kind === 'reward';

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'price' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'matchCount' => $reward ? ['nullable'] : ['required', 'integer', 'between:1,99'],
            'rewardLabel' => $reward ? ['required', 'string', 'min:2', 'max:80'] : ['nullable'],
        ], [
            'price.min' => 'Harga donasi minimal Rp 1.000.',
        ], ['name' => 'nama paket', 'price' => 'harga donasi', 'matchCount' => 'jatah match', 'rewardLabel' => 'reward yang didapat']);

        $attributes = [
            'name' => trim($validated['name']),
            'kind' => $this->kind,
            'reward_label' => $reward ? trim((string) $validated['rewardLabel']) : null,
            'price' => (int) $validated['price'],
            'match_count' => $reward ? 0 : (int) $validated['matchCount'],
        ];

        if ($this->duplicatePrice($attributes['price'])) {
            $this->addError('price', 'Sudah ada paket aktif dengan harga yang sama — donasi tidak bisa dibedakan.');

            return;
        }

        if ($this->editingId) {
            $service->update(auth()->user(), $this->package($this->editingId), $attributes);
            $message = "Paket {$attributes['name']} diperbarui.";
        } else {
            $service->add(auth()->user(), $attributes);
            $message = "Paket {$attributes['name']} dibuat dan langsung aktif.";
        }

        $this->showForm = false;
        $this->dispatch('notify', message: $message);
    }

    public function toggle(int $packageId, VipPackageService $service): void
    {
        $package = $this->package($packageId);

        if (! $package->is_active && $this->duplicatePrice($package->price, $package->id)) {
            $this->dispatch('notify', message: "Tidak bisa diaktifkan: sudah ada paket aktif seharga Rp ".number_format($package->price, 0, ',', '.').'.');

            return;
        }

        $package = $service->setActive(auth()->user(), $package, ! $package->is_active);
        $this->dispatch('notify', message: $package->is_active ? "Paket {$package->name} ditampilkan ke penonton." : "Paket {$package->name} disembunyikan.");
    }

    public function delete(int $packageId, VipPackageService $service): void
    {
        $package = $this->package($packageId);
        $service->delete(auth()->user(), $package);

        if ($this->editingId === $package->id) {
            $this->showForm = false;
            $this->editingId = null;
        }

        $this->dispatch('notify', message: "Paket {$package->name} dihapus.");
    }

    public function render(VipPackageService $service)
    {
        $user = auth()->user();
        $packages = $user->vipPackages()->withCount(['queueEntries', 'donations as reward_count' => fn ($q) => $q->where('donations.status', 'reward')])->orderBy('sort_order')->orderBy('id')->get();

        return view('livewire.package-manager', [
            'packages' => $packages,
            'activePackages' => $packages->where('is_active', true)->values(),
            'donationsToday' => (int) $user->donations()->whereDate('received_at', today())->where('donations.status', '!=', 'refunded')->sum('amount'),
            'publicUrl' => url('/'.$user->slug),
        ]);
    }

    /**
     * Donasi otomatis dicocokkan dengan paket berdasarkan nominal, jadi dua paket aktif
     * dengan harga sama akan membuat pencocokan ambigu.
     */
    private function duplicatePrice(int $price, ?int $ignoreId = null): bool
    {
        return auth()->user()->vipPackages()
            ->where('is_active', true)
            ->where('price', $price)
            ->whereKeyNot($ignoreId ?? $this->editingId ?? 0)
            ->exists();
    }

    private function package(int $packageId): VipPackage
    {
        return auth()->user()->vipPackages()->whereKey($packageId)->firstOrFail();
    }
}
