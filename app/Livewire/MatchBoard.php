<?php

namespace App\Livewire;

use App\Models\LiveSession;
use App\Models\MatchRecord;
use App\Models\QueueEntry;
use App\Services\MatchService;
use App\Services\QueueService;
use InvalidArgumentException;
use Livewire\Component;
use RuntimeException;

/** Hitungan match (/match-count): catat & koreksi match per pemain, sisa jatah, dan riwayat hari ini. */
class MatchBoard extends Component
{
    public LiveSession $session;

    public function mount(LiveSession $session): void
    {
        abort_unless($session->isOwnedBy(auth()->user()), 404);

        $this->session = $session;
    }

    /** +1 match; bila jatah habis pemain otomatis selesai dan giliran dioper (sama seperti papan antrean). */
    public function addMatch(int $entryId, MatchService $matches, QueueService $queue): void
    {
        $this->attempt(function () use ($entryId, $matches, $queue): string {
            $entry = $this->entry($entryId);
            $matches->record($entry);

            if ($matches->remaining($entry) > 0) {
                return "Match ke-{$entry->matches_played} {$entry->player_name} dicatat.";
            }

            if ($entry->status !== 'done') {
                $queue->complete($this->session, $entry);
            }

            return "Jatah {$entry->player_name} habis — ditandai selesai.";
        });
    }

    /** −1 match (koreksi salah catat). Pemain yang sudah selesai dikembalikan ke antrean. */
    public function reduceMatch(int $entryId, MatchService $matches): void
    {
        $this->attempt(function () use ($entryId, $matches): string {
            $entry = $matches->undoLatest($this->entry($entryId));

            if ($entry->status === 'done' && $matches->remaining($entry) > 0) {
                $entry->update(['status' => 'ready']);

                return "Match dikoreksi. {$entry->player_name} kembali ke antrean.";
            }

            return "Match {$entry->player_name} dikoreksi. Sisa {$matches->remaining($entry)} match.";
        });
    }

    public function render()
    {
        $matches = app(MatchService::class);
        $entries = $this->session->queueEntries()->whereIn('status', ['playing', 'ready', 'offline', 'skipped', 'done'])->get();
        $active = $entries->where('status', '!=', 'done');

        return view('livewire.match-board', [
            'entries' => $entries->sortBy(fn (QueueEntry $e) => [$e->status === 'done' ? 1 : 0, $e->position])->values(),
            'current' => app(QueueService::class)->current($this->session),
            'activeWithQuota' => $active->filter(fn (QueueEntry $e) => $matches->remaining($e) > 0)->count(),
            'lowQuota' => $active->filter(fn (QueueEntry $e) => $matches->quotaStatus($e) === 'low')->count(),
            'history' => MatchRecord::query()
                ->with('queueEntry:id,player_name')
                ->where('live_session_id', $this->session->id)
                ->whereDate('played_at', today())
                ->latest('played_at')->latest('id')
                ->limit(20)
                ->get(),
            'matches' => $matches,
        ]);
    }

    private function attempt(callable $action): void
    {
        $this->resetErrorBag('match');

        if ($this->session->refresh()->status !== 'active') {
            $this->addError('match', 'Sesi ini sudah diakhiri.');

            return;
        }

        try {
            $message = $action();
            $this->session->refresh(); // total_matches berubah lewat query langsung
            $this->dispatch('notify', message: $message);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->addError('match', $exception->getMessage());
        }
    }

    private function entry(int $entryId): QueueEntry
    {
        return $this->session->queueEntries()->whereKey($entryId)->firstOrFail();
    }
}
