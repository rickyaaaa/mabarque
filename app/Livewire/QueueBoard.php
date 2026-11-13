<?php

namespace App\Livewire;

use App\Models\LiveSession;
use App\Models\QueueEntry;
use App\Services\QueueService;
use App\Services\QueueStatusService;
use InvalidArgumentException;
use Livewire\Component;
use RuntimeException;

class QueueBoard extends Component
{
    public LiveSession $session;

    public string $filter = 'all';

    public string $playerName = '';

    public int $matchesTotal = 1;

    public bool $showAddPlayer = false;

    public function mount(LiveSession $session): void
    {
        // Papan antrean hanya boleh dibuka pemilik sesi.
        abort_unless($session->isOwnedBy(auth()->user()), 404);

        $this->session = $session;
    }

    public function refreshQueue(): void
    {
        $this->session->refresh();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'ready', 'offline'], true) ? $filter : 'all';
    }

    public function addPlayer(QueueService $queueService): void
    {
        $this->validate([
            'playerName' => ['required', 'string', 'min:2', 'max:120'],
            'matchesTotal' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $this->attempt(function () use ($queueService): void {
            $queueService->addManual($this->session, $this->playerName, $this->matchesTotal);
            $this->reset(['playerName', 'showAddPlayer']);
            $this->matchesTotal = 1;
        });
    }

    public function toggleStatus(int $entryId, QueueStatusService $statusService): void
    {
        $this->attempt(fn () => $statusService->toggle($this->session, $this->entry($entryId)));
    }

    public function move(int $entryId, string $direction, QueueService $queueService): void
    {
        $direction = in_array($direction, ['up', 'down'], true) ? $direction : 'up';
        $this->attempt(fn () => $queueService->move($this->session, $this->entry($entryId), $direction));
    }

    public function skip(int $entryId, QueueService $queueService): void
    {
        $this->attempt(fn () => $queueService->skip($this->session, $this->entry($entryId)));
    }

    public function remove(int $entryId, QueueService $queueService): void
    {
        $this->attempt(fn () => $queueService->remove($this->session, $this->entry($entryId)));
    }

    public function render()
    {
        $query = $this->session->queueEntries();

        if ($this->filter !== 'all') {
            $query->where('status', $this->filter);
        }

        return view('livewire.queue-board', [
            'entries' => $query->get(),
            'currentEntry' => app(QueueService::class)->current($this->session),
        ]);
    }

    /**
     * Jalankan aksi antrean; pelanggaran aturan (mis. mengubah status pemain yang sedang bermain)
     * ditampilkan sebagai pesan di papan, bukan halaman error saat streamer sedang live.
     */
    private function attempt(callable $action): void
    {
        $this->resetErrorBag('queue');

        try {
            $action();
            $this->dispatch('queue-updated');
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->addError('queue', $exception->getMessage());
        }
    }

    private function entry(int $entryId): QueueEntry
    {
        return $this->session->queueEntries()->whereKey($entryId)->firstOrFail();
    }
}
