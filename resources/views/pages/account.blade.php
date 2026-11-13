<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" /><meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="MabarQueue — akun streamer dan ringkasan workspace." />
    <title>MabarQueue — Akun Streamer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" /><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&family=Bricolage+Grotesque:opsz,wght@12..96,400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}" /><link rel="stylesheet" href="{{ asset('css/account.css') }}" />
  </head>
  <body>
    @php
        $betaLabel = ['active' => 'Akses aktif', 'pending' => 'Menunggu persetujuan', 'inactive' => 'Dinonaktifkan'][$user->beta_status] ?? $user->beta_status;
        $isActive = $user->hasActiveBeta();
    @endphp
    <div class="app-shell account-shell">
      @include('partials.sidebar', ['active' => 'account'])
      <main class="main-content account-content">
        <header class="account-header">
          <div>
            <p class="eyebrow">Masuk &amp; akun streamer</p>
            <h1>Workspace kamu</h1>
            <p class="session-meta">Kelola identitas streamer, koneksi, dan akses workspace MabarQueue.</p>
          </div>
          <button class="button button-outline" id="logout-button" type="button">Keluar</button>
        </header>

        @if (session('beta_notice') || ! $isActive)
          <p class="form-error beta-notice" role="status">
            {{ session('beta_notice') ?? ($user->beta_status === 'inactive' ? 'Akun ini sedang dinonaktifkan. Hubungi admin MabarQueue.' : 'Akses private alpha kamu masih menunggu persetujuan admin. Sambil menunggu, kamu bisa melengkapi profil channel.') }}
          </p>
        @endif

        <section class="profile-hero">
          <div class="profile-avatar" style="background: {{ $user->profile_accent }}">{{ $user->avatar_initials_label }}</div>
          <div class="profile-identity">
            <span class="profile-status"><i></i>{{ $betaLabel }}</span>
            <h2>{{ $user->channel_name }}</h2>
            <p>{{ '@'.$user->slug }} <span>•</span> {{ $user->email }}</p>
            <small>Bergabung {{ $user->created_at?->translatedFormat('j F Y') }}</small>
          </div>
          <a class="button button-light" id="edit-profile" href="/profile">Edit profil</a>
        </section>

        <section class="account-grid">
          <article class="account-panel workspace-panel">
            <div class="panel-heading"><div><p class="eyebrow">Workspace</p><h2>Ringkasan live</h2></div></div>
            <div class="account-stat-list">
              <div>
                <span>Sesi aktif</span>
                <strong>{{ $activeSession?->title ?? 'Belum ada sesi aktif' }}</strong>
                <small>{{ $activeSession?->started_at ? 'Berjalan sejak '.$activeSession->started_at->format('H:i').' WIB' : 'Mulai sesi dari halaman Dashboard.' }}</small>
              </div>
              <div>
                <span>Antrean aktif</span>
                <strong>{{ $queueTotal }} pemain</strong>
                <small>{{ $queueReady }} siap bermain · {{ $queueOffline }} offline</small>
              </div>
              <div>
                <span>Match bulan ini</span>
                <strong>{{ $matchesThisMonth }} match</strong>
                <small>Sejak {{ now()->startOfMonth()->translatedFormat('j F') }}</small>
              </div>
            </div>
            @if ($isActive)<a class="account-link" href="/live-session">Buka dashboard sesi →</a>@endif
          </article>

          <article class="account-panel plan-panel">
            <div class="panel-heading">
              <div><p class="eyebrow">Akses akun</p><h2>{{ $user->isSuperAdmin() ? 'Super Admin' : 'Private alpha' }}</h2></div>
              <span @class(['plan-badge', 'is-pending' => ! $isActive])>{{ $isActive ? 'Aktif' : ($user->beta_status === 'inactive' ? 'Nonaktif' : 'Pending') }}</span>
            </div>
            <p class="plan-copy">
              @if ($isActive)
                Semua fitur fase awal tersedia untuk workspace ini.
              @elseif ($user->beta_status === 'inactive')
                Akses workspace dihentikan sementara oleh admin.
              @else
                Kamu akan menerima email begitu akses disetujui.
              @endif
            </p>
            @if ($isActive && $user->isStreamer())
              <p class="plan-copy">Halaman publik: <a class="account-link" href="/{{ $user->slug }}">mabarqueue.id/{{ $user->slug }}</a></p>
            @endif
          </article>
        </section>

        <section class="account-panel connections-panel">
          <div class="panel-heading"><div><p class="eyebrow">Koneksi</p><h2>Provider donasi</h2></div>@if ($isActive)<a class="account-link" href="/donations">Kelola koneksi →</a>@endif</div>
          <div class="connection-summary">
            @foreach ($providers as $key => $provider)
              @php
                  $connection = $provider['connection'];
                  $state = $connection === null ? 'Belum terhubung'
                      : ['active' => 'Aktif', 'paused' => 'Dijeda', 'error' => 'Error'][$connection->status].($connection->last_received_at ? ' · diterima '.$connection->last_received_at->diffForHumans() : '');
              @endphp
              <div>
                <span class="provider-brand provider-brand-{{ $key }}">{{ $provider['short'] }}</span>
                <div><strong>{{ $provider['label'] }}</strong><small>@if ($connection?->status === 'active')<i></i>@endif {{ $state }}</small></div>
              </div>
            @endforeach
          </div>
        </section>

        <section class="account-panel activity-panel">
          <div class="panel-heading"><div><p class="eyebrow">Riwayat</p><h2>Sesi terakhir</h2></div></div>
          <div class="account-activity">
            @forelse ($recentSessions as $session)
              <div>
                <span class="activity-icon is-green">✓</span>
                <p><strong>{{ $session->title }}</strong><small>{{ $session->ended_at?->translatedFormat('j M Y, H:i') }} · {{ $session->total_players }} pemain · {{ $session->total_matches }} match</small></p>
              </div>
            @empty
              <div><span class="activity-icon is-orange">↗</span><p><strong>Belum ada sesi selesai</strong><small>Riwayat sesi live akan muncul di sini.</small></p></div>
            @endforelse
          </div>
        </section>
      </main>
    </div>
    <div class="toast" id="account-toast" role="status" aria-live="polite"></div>
    <script src="{{ asset('js/api.js') }}" defer></script><script src="{{ asset('js/account.js') }}" defer></script>
  </body>
</html>
