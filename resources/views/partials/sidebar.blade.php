{{-- Sidebar dashboard streamer. Pakai: @include('partials.sidebar', ['active' => 'account']) --}}
@php
    $sidebarUser = auth()->user();
    $sidebarSession = $sidebarUser?->liveSessions()->active()->latest('started_at')->first();
    $sidebarQueue = $sidebarSession
        ? $sidebarSession->queueEntries()->reorder()->whereIn('status', ['ready', 'offline', 'playing'])->count()
        : 0;
    $links = [
        'live-session' => ['/live-session', 'layout', 'Dashboard'],
        'dashboard' => ['/dashboard', 'list', 'Antrean'],
        'vip-packages' => ['/vip-packages', 'tag', 'Paket VIP'],
        'donations' => ['/donations', 'wallet', 'VIP &amp; Donasi'],
        'overlay-settings' => ['/overlay-settings', 'monitor', 'Overlay OBS'],
        'account' => ['/account', 'user', 'Akun'],
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
                <span class="nav-icon" aria-hidden="true">@include('partials.icon', ['name' => $icon, 'size' => 19])</span><span>{!! $label !!}</span>
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

    <button class="theme-toggle" type="button" data-theme-toggle aria-pressed="false">
        <span class="theme-toggle-icon" data-theme-icon="moon" aria-hidden="true">@include('partials.icon', ['name' => 'moon', 'size' => 16])</span>
        <span class="theme-toggle-icon" data-theme-icon="sun" aria-hidden="true">@include('partials.icon', ['name' => 'sun', 'size' => 16])</span>
        <span data-theme-label>Mode gelap</span>
    </button>
</aside>
