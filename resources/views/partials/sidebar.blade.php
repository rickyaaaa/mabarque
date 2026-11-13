{{-- Sidebar dashboard streamer. Pakai: @include('partials.sidebar', ['active' => 'account']) --}}
@php
    $sidebarUser = auth()->user();
    $sidebarSession = $sidebarUser?->liveSessions()->active()->latest('started_at')->first();
    $sidebarQueue = $sidebarSession
        ? $sidebarSession->queueEntries()->reorder()->whereIn('status', ['ready', 'offline', 'playing'])->count()
        : 0;
    $links = [
        'live-session' => ['/live-session', '⌂', 'Dashboard'],
        'dashboard' => ['/dashboard', '☷', 'Antrean'],
        'vip-packages' => ['/vip-packages', '♟', 'Paket VIP'],
        'donations' => ['/donations', '★', 'VIP &amp; Donasi'],
        'match-count' => ['/match-count', '▥', 'Statistik'],
        'overlay-settings' => ['/overlay-settings', '▣', 'Overlay OBS'],
        'account' => ['/account', '◎', 'Akun'],
    ];
@endphp
<aside class="sidebar" aria-label="Navigasi utama">
    <div class="brand-lockup">
        <div class="brand-mark" aria-hidden="true">MQ</div>
        <div><p class="brand-name">MabarQueue</p><p class="brand-subtitle">Dashboard streamer<br />VIP mabar Mobile Legends</p></div>
    </div>
    <nav class="main-nav">
        @foreach ($links as $key => [$href, $icon, $label])
            <a @class(['nav-link', 'is-active' => ($active ?? '') === $key]) href="{{ $href }}" @if (($active ?? '') === $key) aria-current="page" @endif>
                <span class="nav-icon" aria-hidden="true">{{ $icon }}</span><span>{!! $label !!}</span>
            </a>
        @endforeach
    </nav>
    <div class="sidebar-divider"></div>
    <section class="stream-status" aria-label="Status stream">
        <div class="status-line">
            <span @class(['status-dot', 'is-live' => $sidebarSession])></span>
            <strong>{{ $sidebarSession ? 'Sesi: Live' : 'Sesi: Belum dimulai' }}</strong>
        </div>
        @if ($sidebarSession)
            <p>{{ $sidebarSession->title }}</p>
            <p>Antrean: <strong>{{ $sidebarQueue }} pemain</strong></p>
        @else
            <p><a href="/live-session">Mulai sesi live →</a></p>
        @endif
    </section>
</aside>
