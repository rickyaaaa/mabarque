<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\BetaAccessService;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Panel Super Admin (/admin): antrean persetujuan akses beta, daftar & pencarian streamer,
 * serta setujui / tunda / nonaktifkan akun. Logika status ada di BetaAccessService
 * (email persetujuan, pencabutan sesi saat dinonaktifkan).
 */
class AdminPanel extends Component
{
    use WithPagination;

    private const FILTERS = ['pending', 'active', 'inactive', 'all'];

    #[Url(as: 'status')]
    public string $status = 'pending';

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 404);

        if (! in_array($this->status, self::FILTERS, true)) {
            $this->status = 'pending';
        }
    }

    public function setFilter(string $status): void
    {
        $this->status = in_array($status, self::FILTERS, true) ? $status : 'all';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setBetaStatus(int $userId, string $status, BetaAccessService $service): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 404);

        $user = User::query()->streamers()->findOrFail($userId);

        try {
            $changed = $service->setStatus($user, $status);
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('notify', message: $exception->getMessage());

            return;
        }

        $label = ['active' => 'disetujui — email pemberitahuan dikirim', 'pending' => 'dikembalikan ke antrean persetujuan', 'inactive' => 'dinonaktifkan — sesi login dicabut'][$status];
        $this->dispatch('notify', message: $changed ? "@{$user->slug} {$label}." : "@{$user->slug} sudah berstatus itu.");
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/login?notice=logout');
    }

    public function render(BetaAccessService $service)
    {
        $counts = User::query()->streamers()
            ->selectRaw('beta_status, count(*) as total')
            ->groupBy('beta_status')
            ->pluck('total', 'beta_status');

        return view('livewire.admin-panel', [
            'streamers' => $service->listStreamers($this->status === 'all' ? null : $this->status, trim($this->search) ?: null, 20),
            'counts' => [
                'pending' => (int) ($counts['pending'] ?? 0),
                'active' => (int) ($counts['active'] ?? 0),
                'inactive' => (int) ($counts['inactive'] ?? 0),
                'all' => (int) $counts->sum(),
            ],
        ]);
    }
}
