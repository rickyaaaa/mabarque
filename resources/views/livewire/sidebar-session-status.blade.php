<section class="stream-status" aria-label="Status stream" wire:poll.5s.keep-alive>
    <div class="status-line">
        <span @class(['status-dot', 'is-live' => $session])></span>
        <strong>{{ $session ? 'Sesi: Live' : 'Sesi: Belum dimulai' }}</strong>
    </div>
    @if ($session)
        <p>{{ $session->title }}</p>
        <p>Antrean: <strong>{{ $queueCount }} pemain</strong></p>
    @else
        <p><a href="/live-session">Mulai sesi live →</a></p>
    @endif
</section>
