<?php

namespace App\Livewire;

use App\Models\LiveSession;
use App\Models\QueueEntry;
use App\Services\LiveSessionService;
use App\Services\MatchService;
use App\Services\QueueService;
use App\Services\QueueStatusService;
use InvalidArgumentException;
use Livewire\Component;
use RuntimeException;

/**
 * Papan antrean sesi live (/dashboard): giliran sekarang, aksi cepat, daftar pemain, tambah pemain manual.
 * Polling tiap 5 detik supaya donasi yang masuk lewat webhook langsung terlihat.
 */
class QueueBoard extends Component
{
    /** Status yang tampil di papan; pemain "done" (jatah habis) disembunyikan. */
    private const VISIBLE = ['playing', 'ready', 'offline', 'skipped'];

    private const FILTERS = ['all', 'ready', 'offline', 'skipped'];

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
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
    }

    public function openAddPlayer(): void
    {
        $this->resetValidation();
        $this->showAddPlayer = true;
    }

    public function closeAddPlayer(): void
    {
        $this->showAddPlayer = false;
    }

    public function addPlayer(QueueService $queueService): void
    {
        $this->validate([
            'playerName' => ['required', 'string', 'min:2', 'max:120'],
            'matchesTotal' => ['required', 'integer', 'min:1', 'max:99'],
        ], [], ['playerName' => 'nama pemain', 'matchesTotal' => 'jatah mabar']);

        $name = trim($this->playerName);
        $this->attempt(function () use ($queueService, $name): string {
            $queueService->addManual($this->session, $name, $this->matchesTotal);
            $this->reset(['playerName', 'showAddPlayer']);
            $this->matchesTotal = 1;
            $this->filter = 'all';

            return "{$name} masuk ke antrean.";
        });
    }

    public function markPlaying(int $entryId, QueueService $queueService): void
    {
        $this->attempt(function () use ($entryId, $queueService): string {
            $entry = $queueService->markPlaying($this->session, $this->entry($entryId));

            return "{$entry->player_name} sekarang bermain.";
        });
    }

    /** Catat 1 match selesai; bila jatah habis, pemain otomatis selesai dan giliran dioper. */
    public function finishMatch(int $entryId, MatchService $matches, QueueService $queueService): void
    {
        $this->attempt(function () use ($entryId, $matches, $queueService): string {
            $entry = $this->entry($entryId);
            $matches->record($entry);

            if ($matches->remaining($entry) > 0) {
                return "Match dicatat. {$entry->player_name} sisa {$matches->remaining($entry)} match.";
            }

            $next = $queueService->complete($this->session, $entry);

            return "Jatah {$entry->player_name} habis."
                .($next && $next->status === 'playing' ? " Giliran berikutnya: {$next->player_name}." : '');
        });
    }

    public function toggleStatus(int $entryId, QueueStatusService $statusService): void
    {
        $this->attempt(function () use ($entryId, $statusService): string {
            $entry = $statusService->toggle($this->session, $this->entry($entryId));

            return $entry->status === 'offline' ? "{$entry->player_name} ditandai offline." : "{$entry->player_name} siap bermain.";
        });
    }

    public function move(int $entryId, string $direction, QueueService $queueService): void
    {
        $direction = in_array($direction, ['up', 'down'], true) ? $direction : 'up';
        $this->attempt(function () use ($entryId, $direction, $queueService): ?string {
            $queueService->move($this->session, $this->entry($entryId), $direction);

            return null;
        });
    }

    public function skip(int $entryId, QueueService $queueService): void
    {
        $this->attempt(function () use ($entryId, $queueService): string {
            $entry = $this->entry($entryId);
            $next = $queueService->skip($this->session, $entry);

            return "{$entry->player_name} dilewati."
                .($next && $next->status === 'playing' && $next->isNot($entry) ? " Giliran: {$next->player_name}." : '');
        });
    }

    public function remove(int $entryId, QueueService $queueService): void
    {
        $this->attempt(function () use ($entryId, $queueService): string {
            $entry = $this->entry($entryId);
            $queueService->remove($this->session, $entry);

            return "{$entry->player_name} dihapus dari antrean.";
        });
    }

    public function endSession(LiveSessionService $sessions): void
    {
        $sessions->end(auth()->user(), $this->session);
        session()->flash('notice', 'Sesi diakhiri. Ringkasannya tersimpan di arsip.');
        $this->redirect('/live-session', navigate: false);
    }

    public function render()
    {
        $all = $this->session->queueEntries()->whereIn('status', self::VISIBLE)->get();
        $queue = app(QueueService::class);

        return view('livewire.queue-board', [
            'entries' => $this->filter === 'all' ? $all : $all->where('status', $this->filter)->values(),
            'counts' => [
                'all' => $all->count(),
                'ready' => $all->where('status', 'ready')->count(),
                'offline' => $all->where('status', 'offline')->count(),
                'skipped' => $all->where('status', 'skipped')->count(),
            ],
            'currentEntry' => $queue->current($this->session),
            'matches' => app(MatchService::class),
        ]);
    }

    /**
     * Jalankan aksi antrean; pelanggaran aturan (mis. mengubah status pemain yang sedang bermain)
     * ditampilkan sebagai pesan, bukan halaman error saat streamer sedang live.
     */
    private function attempt(callable $action): void
    {
        $this->resetErrorBag('queue');

        if ($this->session->refresh()->status !== 'active') {
            $this->addError('queue', 'Sesi ini sudah diakhiri. Mulai sesi baru dari halaman Sesi Live.');

            return;
        }

        try {
            $message = $action();
            $this->session->refresh(); // total_matches/total_players berubah lewat query langsung
            $this->dispatch('queue-updated');

            if ($message) {
                $this->dispatch('notify', message: $message);
            }
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->addError('queue', $exception->getMessage());
        }
    }

    private function entry(int $entryId): QueueEntry
    {
        return $this->session->queueEntries()->whereKey($entryId)->firstOrFail();
    }
}
