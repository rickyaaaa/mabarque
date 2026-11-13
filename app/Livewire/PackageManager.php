<?php

namespace App\Livewire;

use App\Models\VipPackage;
use App\Services\VipPackageService;
use Livewire\Component;

/** Paket VIP (/vip-packages): buat, ubah, aktif/nonaktifkan paket, dan pratinjau untuk penonton. */
class PackageManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public int|string $price = 25000;

    public int|string $matchCount = 1;

    public function create(): void
    {
        $this->resetValidation();
        $this->fill(['editingId' => null, 'name' => '', 'price' => 25000, 'matchCount' => 1, 'showForm' => true]);
    }

    public function edit(int $packageId): void
    {
        $package = $this->package($packageId);
        $this->resetValidation();
        $this->fill(['editingId' => $package->id, 'name' => $package->name, 'price' => $package->price, 'matchCount' => $package->match_count, 'showForm' => true]);
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    public function save(VipPackageService $service): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'price' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'matchCount' => ['required', 'integer', 'between:1,99'],
        ], [
            'price.min' => 'Harga donasi minimal Rp 1.000.',
        ], ['name' => 'nama paket', 'price' => 'harga donasi', 'matchCount' => 'jatah match']);

        $attributes = ['name' => trim($validated['name']), 'price' => (int) $validated['price'], 'match_count' => (int) $validated['matchCount']];

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

    public function render(VipPackageService $service)
    {
        $user = auth()->user();
        $packages = $user->vipPackages()->withCount('queueEntries')->orderBy('sort_order')->orderBy('id')->get();

        return view('livewire.package-manager', [
            'packages' => $packages,
            'activePackages' => $packages->where('is_active', true)->values(),
            'donationsToday' => (int) $user->donations()->whereDate('received_at', today())->whereIn('donations.status', ['queued', 'received', 'review'])->sum('amount'),
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
