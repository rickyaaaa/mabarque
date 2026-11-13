<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex" />
    <title>Overlay antrean — {{ $initial['channel_name'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/overlay.css') }}" />
  </head>
  {{-- Browser source OBS: data awal dirender server, lalu diperbarui tiap 3 detik dari /api/overlay/{token}. --}}
  <body class="overlay-body" data-token="{{ $token }}">
    <main class="overlay-card" id="overlay" aria-live="polite">
      <div class="overlay-brand"><span id="overlay-channel">{{ $initial['channel_name'] }}</span> <em>VIP</em></div>
      <section class="overlay-now" id="overlay-now" hidden>
        <span class="overlay-now-label">NOW PLAYING</span>
        <strong id="overlay-now-name"></strong>
        <small id="overlay-now-meta"></small>
      </section>
      <ol class="overlay-queue" id="overlay-queue"></ol>
      <p class="overlay-empty" id="overlay-empty" hidden></p>
    </main>
    <script>window.__OVERLAY__ = @json($initial);</script>
    <script src="{{ asset('js/overlay.js') }}" defer></script>
  </body>
</html>
