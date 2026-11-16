<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex" />
    <title>Overlay antrean — {{ $initial['channel_name'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/overlay.css') }}" />
  </head>
  {{-- Browser source OBS: data awal dirender server, lalu diperbarui tiap 3 detik dari /api/overlay/{token}. --}}
  <body class="overlay-body{{ $widget ? ' is-widget widget-'.$widget : '' }}" data-token="{{ $token }}" data-widget="{{ $widget }}">
    <main class="overlay-card" id="overlay" aria-live="polite">
      <div class="overlay-head">
        <div class="overlay-brand"><span id="overlay-channel">{{ $initial['channel_name'] }}</span> <em>VIP</em></div>
        <div class="overlay-score" id="overlay-score" hidden aria-label="Skor sesi ini">
          <span class="overlay-score-win"><small>W</small><strong id="overlay-wins">0</strong></span>
          <span class="overlay-score-lose"><small>L</small><strong id="overlay-losses">0</strong></span>
        </div>
      </div>
      <section class="overlay-now" id="overlay-now" hidden>
        <span class="overlay-now-label">NOW PLAYING</span>
        <strong id="overlay-now-name"></strong>
        <small id="overlay-now-meta"></small>
        <ol class="overlay-party" id="overlay-party" hidden></ol>
      </section>
      <ol class="overlay-queue" id="overlay-queue"></ol>
      <p class="overlay-empty" id="overlay-empty" hidden></p>
      <ul class="overlay-packages" id="overlay-packages" hidden aria-label="Harga paket VIP"></ul>
    </main>
    <script>window.__OVERLAY__ = @json($initial);</script>
    <script src="{{ \App\Support\Asset::url('js/overlay.js') }}" defer></script>
  </body>
</html>
