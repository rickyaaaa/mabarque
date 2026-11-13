<?php

namespace App\Livewire;

use App\Models\LiveSession;
use App\Services\LiveSessionService;
use Livewire\Component;

/**
 * Halaman Sesi Live (/live-session): mulai, akhiri, arsipkan, dan reset antrean sesi,
 * plus ringkasan sesi berjalan dan arsip sesi selesai.
 */
class LiveSessionPanel extends Component
{
    public string $title = '';

    public int $archiveLimit = 5;

    public function mount(): void
    {
        $this->title = 'Mabar VIP '.now()->translatedFormat('l, j F');
    }

    public function startSession(LiveSessionService $sessions): void
    {
        $this->validate(['title' => ['required', 'string', 'min:3', 'max:120']], [], ['title' => 'nama sesi']);

        $hadActive = $this->activeSession() !== null;
        $session = $sessions->start(auth()->user(), $this->title);

        $this->dispatch('notify', message: $hadActive
            ? "Sesi lama diakhiri. \"{$session->title}\" sekarang live."
            : "\"{$session->title}\" sekarang live. Donasi VIP akan masuk ke antrean.");
    }

    public function endSession(LiveSessionService $sessions): void
    {
        if ($session = $this->activeSession()) {
            $sessions->end(auth()->user(), $session);
            $this->dispatch('notify', message: 'Sesi diakhiri. Ringkasannya tersimpan di arsip.');
        }
    }

    public function archiveSession(LiveSessionService $sessions): void
    {
        if ($session = $this->activeSession()) {
            $sessions->archive(auth()->user(), $session);
            $this->dispatch('notify', message: 'Sesi diarsipkan dan sisa antrean ditandai selesai.');
        }
    }

    public function resetQueue(LiveSessionService $sessions): void
    {
        if ($session = $this->activeSession()) {
            $cleared = $sessions->resetQueue(auth()->user(), $session);
            $this->dispatch('notify', message: "{$cleared} pemain dikeluarkan dari antrean.");
        }
    }

    public function loadMoreArchives(): void
    {
        $this->archiveLimit = min($this->archiveLimit + 5, 50);
    }

    public function render()
    {
        $user = auth()->user();
        $session = $this->activeSession();

        $queue = $session?->queueEntries()->whereIn('status', ['playing', 'ready', 'offline', 'skipped'])->get() ?? collect();
        $donations = $session?->donations() ?? null;

        $archives = $user->liveSessions()->where('status', 'ended')->latest('ended_at');

        return view('livewire.live-session-panel', [
            'session' => $session,
            'queue' => $queue,
            'nextUp' => $queue->whereIn('status', ['playing', 'ready', 'offline'])->take(5),
            'readyCount' => $queue->where('status', 'ready')->count(),
            'donationCount' => $donations?->count() ?? 0,
            'pendingDonations' => $session?->donations()->whereIn('status', ['received', 'review'])->count() ?? 0,
            'archives' => (clone $archives)->limit($this->archiveLimit)->get(),
            'archiveTotal' => $archives->count(),
            'publicUrl' => url('/'.$user->slug),
            'isPublic' => $user->isStreamer() && $user->hasActiveBeta(),
        ]);
    }

    private function activeSession(): ?LiveSession
    {
        return auth()->user()->liveSessions()->active()->latest('started_at')->first();
    }
}
