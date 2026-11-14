@php
    $isLive = $queue['status'] === 'live';
    $entries = collect($queue['queue'] ?? []);
    $playing = $entries->firstWhere('status', 'playing');
    $waiting = $entries->where('status', '!=', 'playing')->values();
    $providerLabels = ['manual' => 'Manual', 'saweria' => 'Saweria', 'sociabuzz' => 'SociaBuzz', 'trakteer' => 'Trakteer'];
    $statusLabels = ['ready' => 'Siap', 'offline' => 'Menunggu', 'skipped' => 'Dilewati', 'playing' => 'Bermain'];
    $entryMeta = fn (array $entry) => collect([
        ($entry['service_type'] ?? 'vip') === 'joki' ? 'Mainin akun' : 'Mabar VIP',
        ! empty($entry['mlbb_id']) && ! str_starts_with($entry['player_name'], 'ID ') ? 'ID '.$entry['mlbb_id'] : null,
    ])->filter()->join(' · ');
    $rupiah = fn (int $value) => 'Rp '.number_format($value, 0, ',', '.');
    $initials = fn (string $name) => mb_strtoupper(collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('')) ?: '?';
    $startedAt = $isLive && ! empty($queue['session']['started_at']) ? \Illuminate\Support\Carbon::parse($queue['session']['started_at']) : null;
@endphp
<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Antrean VIP mabar {{ $channel['channel_name'] }} — cek posisi dan sisa match kamu." />
    <title>{{ $channel['channel_name'] }} — Antrean VIP · MabarQueue</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&family=Bricolage+Grotesque:opsz,wght@12..96,400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/public-queue.css') }}" />
  </head>
  <body class="public-body" data-channel-id="{{ $channel['slug'] }}" style="--channel-accent: {{ $channel['accent'] }}">
    <main class="public-shell">
      <header class="public-header">
        <a class="public-brand" href="/{{ $channel['slug'] }}"><span class="brand-mark" aria-hidden="true">{{ $channel['avatar_initials'] }}</span><span>{{ $channel['channel_name'] }}</span></a>
        <div class="public-header-actions">
          <span @class(['public-live-badge', 'is-offline' => ! $isLive]) id="live-badge" role="status" aria-live="polite"><i></i><span id="live-label">{{ $isLive ? 'Live sekarang' : 'Belum live' }}</span></span>
          <button class="button button-light button-small" id="share-queue" type="button">↗ Bagikan antrean</button>
        </div>
      </header>

      <section class="public-intro">
        <div>
          <p class="eyebrow">Antrean VIP publik</p>
          <h1>Sesi mabar<br /><span>{{ $channel['channel_name'] }}</span></h1>
          <p class="public-meta" id="session-meta">
            @if ($isLive)
              {{ $queue['session']['title'] }} <span>•</span> Live sejak {{ $startedAt?->timezone(config('app.timezone'))->format('H:i') }} WIB
            @else
              Sesi mabar VIP belum dibuka{{ $channel['live_schedule'] ? ' · Jadwal: '.$channel['live_schedule'] : '' }}
            @endif
          </p>
          @if ($channel['tagline'])<p class="public-meta">{{ $channel['tagline'] }}</p>@endif
        </div>
        <div class="public-update" role="status" aria-live="polite">
          <span @class(['status-dot', 'is-live' => $isLive])></span>
          <div><strong>Diperbarui otomatis</strong><span id="last-update">Baru saja</span></div>
        </div>
      </section>

      <section class="public-current" aria-labelledby="public-current-title" id="current-section" @if (! $playing) hidden @endif>
        <div class="public-current-label"><span class="status-dot is-live"></span>Sedang bermain</div>
        <div class="public-current-body">
          <div>
            <p class="eyebrow">Giliran sekarang</p>
            <h2 id="public-current-title">{{ $playing['player_name'] ?? '' }}</h2>
            <p id="current-meta">{{ $playing ? $entryMeta($playing).' · sisa '.$playing['matches_remaining'].' game' : '' }}</p>
          </div>
          <div class="public-current-avatar" id="current-avatar">{{ $playing ? $initials($playing['player_name']) : '' }}</div>
        </div>
      </section>

      <section class="public-summary" aria-label="Ringkasan antrean">
        <article><span>Antrean</span><strong id="queue-total">{{ $waiting->count() }}</strong><small>pemain punya sisa game</small></article>
        <article><span>Sesi</span><strong id="session-state">{{ $isLive ? 'Live' : 'Offline' }}</strong><small>{{ $isLive ? 'donasi VIP dibuka' : 'tunggu streamer mulai' }}</small></article>
        <article><span>Match selesai</span><strong id="matches-done">{{ $queue['session']['total_matches'] ?? 0 }}</strong><small>sejak live dimulai</small></article>
      </section>

      <section class="public-packages" aria-labelledby="public-packages-title">
        <div class="public-section-heading">
          <div><p class="eyebrow">Pilihan VIP</p><h2 id="public-packages-title">Paket aktif</h2></div>
          <span class="public-package-note">Donasi kelipatan harga paket otomatis masuk antrean · di bawah harga termurah tidak dihitung</span>
        </div>
        <div class="public-package-list">
          @forelse ($packages as $package)
            <article @class(['is-featured' => $loop->index === 1])><strong>{{ $package->name }}</strong><span>{{ $rupiah($package->price) }}</span><small>{{ $package->match_count }} game</small></article>
          @empty
            <article><strong>Belum ada paket</strong><span>—</span><small>Streamer belum membuka paket VIP</small></article>
          @endforelse
        </div>
      </section>

      <section class="public-queue-panel" aria-labelledby="public-queue-title">
        <div class="public-section-heading">
          <div><p class="eyebrow">{{ $isLive ? 'Urutan berikutnya' : 'Sisa game' }}</p><h2 id="public-queue-title">Daftar antrean</h2></div>
          <div class="public-queue-tools">
            <label for="queue-search">Cari nama / ID</label>
            <input id="queue-search" type="search" placeholder="Nama atau ID MLBB kamu" autocomplete="off" />
            <label for="queue-sort">Urutkan</label>
            <select id="queue-sort" class="public-sort">
              <option value="queue">Urutan antrean</option>
              <option value="most">Sisa terbanyak</option>
              <option value="least">Sisa paling sedikit</option>
              <option value="newest">Terbaru</option>
            </select>
            <button class="filter-button" id="refresh-public-queue" type="button">↻ Refresh</button>
          </div>
        </div>
        <div class="public-position-note" id="public-position-note" role="status" aria-live="polite">Cari nama atau ID kamu untuk melihat posisi dan sisa game.</div>
        <div class="public-queue-list" id="public-queue-list" aria-live="polite">
          @foreach ($waiting as $entry)
            <article class="public-queue-item"><span class="public-queue-number" aria-hidden="true">{{ $loop->iteration }}</span><div><strong>{{ $entry['player_name'] }}</strong><span>{{ $entryMeta($entry) }}</span></div><span class="public-match-left">{{ $entry['matches_remaining'] }} <small>game</small></span><span @class(['public-ready', 'is-'.$entry['status']])>{{ $statusLabels[$entry['status']] ?? 'Siap' }}</span></article>
          @endforeach
        </div>
        <div class="public-empty" id="public-empty" role="status" aria-live="polite" @if ($waiting->isNotEmpty()) hidden @endif>
          <strong>Antrean masih kosong</strong>
          <p>Donasi sesuai paket VIP untuk masuk antrean.</p>
        </div>
      </section>

      <footer class="public-footer">
        <span>Data antrean diperbarui otomatis setiap 5 detik</span>
        <span>Powered by <strong>MabarQueue</strong></span>
      </footer>
    </main>
    <div class="toast" id="public-toast" role="status" aria-live="polite"></div>
    <script src="{{ asset('js/public-queue.js') }}" defer></script>
  </body>
</html>
