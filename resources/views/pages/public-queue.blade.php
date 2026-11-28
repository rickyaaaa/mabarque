@php
    $isLive = $queue['status'] === 'live';
    $entries = collect($queue['queue'] ?? []);
    $playing = $entries->firstWhere('status', 'playing');
    $waiting = $entries->where('status', '!=', 'playing')->values();
    $providerLabels = ['manual' => 'Manual', 'saweria' => 'Saweria', 'sociabuzz' => 'SociaBuzz', 'trakteer' => 'Trakteer'];
    $entryMeta = fn (array $entry) => collect([
        ($entry['service_type'] ?? 'vip') === 'joki' ? 'Mainin akun' : 'Mabar VIP',
        ! empty($entry['mlbb_id']) && ! str_starts_with($entry['player_name'], 'ID ') ? 'ID '.$entry['mlbb_id'] : null,
    ])->filter()->join(' · ');
    $rupiah = fn (int $value) => 'Rp '.number_format($value, 0, ',', '.');
    $initials = function (string $name): string {
        preg_match_all('/(?:^|\s)[^\s\p{L}]*(\p{L})/u', $name, $matches);

        return mb_substr(mb_strtoupper(implode('', array_slice($matches[1], 0, 2))), 0, 2) ?: '?';
    };
    $partyNames = $queue['party'] ?? [];
    $nextNames = $queue['next'] ?? [];
    $jokiOrders = $queue['joki'] ?? [];
    $jokiLabels = ['playing' => 'Sedang dimainkan', 'waiting' => 'Menunggu', 'done' => 'Selesai ✓'];
    $sessionCap = (int) ($queue['rules']['session_cap'] ?? 0);
    $startedAt = $isLive && ! empty($queue['session']['started_at']) ? \Illuminate\Support\Carbon::parse($queue['session']['started_at']) : null;
@endphp
<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Antrean VIP mabar {{ $channel['channel_name'] }} — cek posisi dan sisa match kamu." />
    <title>{{ $channel['channel_name'] }} — Antrean VIP · Mabarku</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/styles.css') }}" />
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/public-queue.css') }}" />
    {{-- Tampilan v2 senada dengan dashboard (dimuat terakhir). --}}
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/public-v2.css') }}" />
  </head>
  <body class="public-body ui-v2" data-channel-id="{{ $channel['slug'] }}" style="--channel-accent: {{ $channel['accent'] }}">
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

      <section class="public-current" aria-labelledby="public-current-title" id="current-section" @if (! $isLive || ($partyNames === [] && $nextNames === [])) hidden @endif>
        <div class="public-current-label"><span class="status-dot is-live"></span><span id="public-current-title">Sedang main</span><span class="public-party-size" id="party-size">{{ $isLive ? count($partyNames).'/'.($queue['rules']['party_size'] ?? 4).' pemain + streamer' : '' }}</span></div>
        <div class="public-party">
          <div>
            <p class="eyebrow">Sedang main</p>
            <ol class="public-party-list" id="party-list">
              @forelse ($partyNames as $member)<li><span>{{ $member['player_name'] }}</span><em>sisa {{ $member['matches_remaining'] }} game</em></li>@empty<li class="is-empty">Belum ada game berjalan</li>@endforelse
            </ol>
          </div>
          <div>
            <p class="eyebrow">Next game</p>
            <ol class="public-party-list is-next" id="next-list">
              @forelse ($nextNames as $member)<li><span>{{ $member['player_name'] }}</span><em>sisa {{ $member['matches_remaining'] }} game</em></li>@empty<li class="is-empty">Belum ada yang menunggu</li>@endforelse
            </ol>
          </div>
        </div>
      </section>

      <section class="public-joki" aria-labelledby="public-joki-title" id="joki-section" @if ($jokiOrders === []) hidden @endif>
        <div class="public-section-heading">
          <div><p class="eyebrow">Cek pesanan kamu</p><h2 id="public-joki-title">VIP Mainin akun</h2></div>
          <span class="public-package-note">Status akun yang dimainkan streamer · yang selesai tampil 3 hari</span>
        </div>
        <ul class="public-joki-list" id="joki-list">
          @foreach ($jokiOrders as $order)
            <li><span>{{ $order['player_name'] }}</span><em class="joki-badge is-{{ $order['state'] }}">{{ $jokiLabels[$order['state']] }}</em></li>
          @endforeach
        </ul>
      </section>

      <section class="public-summary" aria-label="Ringkasan antrean">
        <article><span>Antrean</span><strong id="queue-total">{{ $waiting->count() }}</strong><small>pemain punya sisa game</small></article>
        <article><span>Sesi</span><strong id="session-state">{{ $isLive ? 'Live' : 'Offline' }}</strong><small>{{ $isLive ? 'donasi VIP dibuka' : 'tunggu streamer mulai' }}</small></article>
        <article><span>Match selesai</span><strong id="matches-done">{{ $queue['session']['total_matches'] ?? 0 }}</strong><small>sejak live dimulai</small></article>
      </section>

      <section class="public-packages" aria-labelledby="public-packages-title">
        <div class="public-section-heading">
          <div><p class="eyebrow">Pilihan VIP</p><h2 id="public-packages-title">Paket aktif</h2></div>
          <span class="public-package-note">
            @if ($donationMode === 'smart')
              <strong>Tulis ID MLBB kamu di pesan donasi</strong> supaya langsung masuk antrean ·
            @elseif ($donationMode === 'manual')
              Donasi dicek streamer dulu sebelum masuk antrean · tulis ID MLBB di pesan donasi ·
            @endif
            Kelipatan harga paket dihitung otomatis (mis. 2× paket = 2× game) · di bawah harga termurah tidak dihitung
          </span>
        </div>
        <div class="public-package-list">
          @forelse ($packages as $package)
            <button type="button" @class(['public-package-card', 'is-featured' => $loop->index === 1])
                    data-package-name="{{ $package->name }}" data-package-price="{{ $package->price }}" data-package-price-label="{{ $rupiah($package->price) }}" data-package-games="{{ $package->getsLabel() }}"
                    aria-haspopup="dialog">
              <strong>{{ $package->name }}</strong><span>{{ $rupiah($package->price) }}</span><small>{{ $package->getsLabel() }}</small>
              <em class="public-package-cta">{{ $donationLinks ? 'Donasi sekarang →' : 'Lihat cara donasi →' }}</em>
            </button>
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
        <p class="public-rules-note" id="public-rules-note" @if ($sessionCap < 1) hidden @endif>Maks {{ $sessionCap }} game per orang tiap sesi live. Sisa game tetap tersimpan untuk sesi berikutnya.</p>
        <div class="public-position-note" id="public-position-note" role="status" aria-live="polite">Cari nama atau ID kamu untuk melihat posisi dan sisa game.</div>
        <div class="public-queue-list" id="public-queue-list" aria-live="polite">
          @foreach ($waiting as $entry)
            <article class="public-queue-item"><span class="public-queue-initials" aria-hidden="true">{{ $initials($entry['player_name']) }}</span><div><strong>{{ $entry['player_name'] }}</strong><span>{{ $entryMeta($entry) }}</span></div><span class="public-match-left"><strong>{{ $entry['matches_remaining'] }}</strong><small>game</small></span></article>
          @endforeach
        </div>
        <div class="public-empty" id="public-empty" role="status" aria-live="polite" @if ($waiting->isNotEmpty()) hidden @endif>
          <strong>Antrean masih kosong</strong>
          <p>Donasi sesuai paket VIP untuk masuk antrean.</p>
        </div>
      </section>

      <dialog class="package-dialog" id="package-dialog" aria-labelledby="package-dialog-title">
        <div class="package-dialog-card">
          <button class="package-dialog-close" type="button" data-close-dialog aria-label="Tutup">×</button>
          <p class="eyebrow">Paket VIP</p>
          <h2 id="package-dialog-title"></h2>
          <div class="package-dialog-price">
            <div><span>Donasi tepat</span><strong id="package-dialog-price"></strong></div>
            <div><span>Dapat</span><strong id="package-dialog-games"></strong></div>
            <button class="button button-small button-light" type="button" id="package-dialog-copy">Salin nominal</button>
          </div>
          <ol class="package-dialog-steps">
            <li>Klik tombol donasi di bawah, isi nominal <strong data-price-inline></strong> (atau kelipatannya).</li>
            @if ($donationMode !== 'auto')
              <li><strong>Tulis ID MLBB kamu di pesan donasi</strong>, mis. <code>mabar 12345678 (1234)</code>.</li>
            @else
              <li>Tulis nama atau ID MLBB kamu di pesan donasi supaya streamer gampang invite.</li>
            @endif
            <li>Kembali ke halaman ini — nama kamu muncul di daftar antrean{{ $donationMode === 'manual' ? ' setelah dicek streamer' : '' }}.</li>
          </ol>
          @if ($donationLinks)
            <div class="package-dialog-links">
              @foreach ($donationLinks as $link)
                <a @class(['button', 'button-primary' => $loop->first, 'button-outline' => ! $loop->first]) href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer">Donasi di {{ $link['label'] }} ↗</a>
              @endforeach
            </div>
          @else
            <p class="package-dialog-empty">Streamer belum memasang link donasi. Cari link donasi di deskripsi live atau tanya di chat.</p>
          @endif
        </div>
      </dialog>

      <footer class="public-footer">
        <span>Data antrean diperbarui otomatis setiap 5 detik</span>
        <span>Powered by <strong>Mabarku</strong></span>
      </footer>
    </main>
    <div class="toast" id="public-toast" role="status" aria-live="polite"></div>
    <script src="{{ \App\Support\Asset::url('js/public-queue.js') }}" defer></script>
  </body>
</html>
