<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" /><meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Pengaturan tampilan overlay OBS MabarQueue." />
    <title>MabarQueue — Pengaturan Overlay OBS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,600;12..96,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/overlay-settings.css') }}" />
  </head>
  <body data-overlay-token="">
    <div class="app-shell overlay-shell">
      <aside class="sidebar" aria-label="Navigasi utama">
        <div class="brand-lockup"><div class="brand-mark" aria-hidden="true">MQ</div><div><p class="brand-name">MabarQueue</p><p class="brand-subtitle">Dashboard streamer<br />VIP mabar Mobile Legends</p></div></div>
        <nav class="main-nav">
          <a class="nav-link" href="/live-session"><span class="nav-icon" aria-hidden="true">⌂</span><span>Dashboard</span></a>
          <a class="nav-link" href="/dashboard#queue"><span class="nav-icon" aria-hidden="true">☷</span><span>Antrean</span></a>
          <a class="nav-link" href="/dashboard#queue"><span class="nav-icon" aria-hidden="true">♟</span><span>Pemain</span></a>
          <a class="nav-link" href="/donations"><span class="nav-icon" aria-hidden="true">★</span><span>VIP &amp; Donasi</span></a>
          <a class="nav-link" href="/match-count"><span class="nav-icon" aria-hidden="true">▥</span><span>Statistik</span></a>
          <a class="nav-link is-active" href="/overlay-settings" aria-current="page"><span class="nav-icon" aria-hidden="true">⚙</span><span>Pengaturan</span></a>
        </nav>
        <div class="sidebar-divider"></div>
        <section class="stream-status" aria-label="Status stream"><div class="status-line"><span class="status-dot is-live"></span><strong>Stream: Online</strong></div><p>Mode: Mabar VIP</p><p>Antrean: <strong>11 pemain</strong></p></section>
      </aside>

      <main class="main-content overlay-content">
        <header class="overlay-header"><div><p class="eyebrow">Siap ditempel ke OBS</p><h1>Tampilan overlay</h1><p class="session-meta">Atur tampilan antrean yang muncul di layar live kamu.</p></div><button class="button button-primary" id="copy-overlay-link" type="button">Salin link overlay</button></header>

        <section class="overlay-layout">
          <form class="overlay-controls" id="overlay-form">
            <div class="panel-heading"><h2>Pengaturan</h2><span class="save-status" id="save-status">Tersimpan otomatis</span></div>
            <label class="control-label" for="accent-color">Warna aksen</label>
            <div class="color-control"><input id="accent-color" type="color" value="#d82a1c" /><input id="accent-hex" class="field-input" value="#D82A1C" maxlength="7" aria-label="Kode warna aksen" /></div>
            <label class="control-label" for="font-scale">Ukuran huruf <output id="font-scale-value">100%</output></label>
            <input id="font-scale" type="range" min="80" max="130" value="100" />
            <label class="control-label" for="max-players">Jumlah pemain ditampilkan</label>
            <select id="max-players" class="field-input"><option value="3">3 pemain</option><option value="5" selected>5 pemain</option><option value="8">8 pemain</option><option value="10">10 pemain</option></select>
            <label class="switch-control"><input id="show-now-playing" type="checkbox" checked /><span class="switch-track"></span><span><strong>Tampilkan now playing</strong><small>Penonton tahu siapa yang sedang bermain.</small></span></label>
            <label class="switch-control"><input id="transparent-mode" type="checkbox" checked /><span class="switch-track"></span><span><strong>Mode transparan</strong><small>Hilangkan latar belakang untuk browser source OBS.</small></span></label>
            <div class="control-actions"><button class="button button-outline" id="reset-overlay" type="button">Reset</button><button class="button button-primary" id="save-overlay" type="button">Simpan pengaturan</button></div>
          </form>

          <section class="overlay-preview-panel" aria-labelledby="preview-title"><div class="panel-heading"><h2 id="preview-title">Pratinjau langsung</h2><span class="preview-live"><span class="status-dot is-live"></span>LIVE</span></div><div class="overlay-stage" id="overlay-stage"><div class="overlay-preview" id="overlay-preview"><div class="preview-brand">MABAR<span>QUEUE</span></div><div class="preview-now" id="preview-now"><span>NOW PLAYING</span><strong>Raka ML</strong><small>Saweria · 3 match tersisa</small></div><div class="preview-queue" id="preview-queue"></div></div></div><div class="overlay-url"><span>Link browser source</span><code>mabarqueue.id/o/andrarizky</code><button class="icon-button" id="copy-overlay-link-small" type="button" aria-label="Salin link overlay">⧉</button></div></section>
        </section>
      </main>
    </div>
    <div class="toast" id="overlay-toast" role="status" aria-live="polite"></div>
    <script src="{{ asset('js/api.js') }}" defer></script><script src="{{ asset('js/overlay-settings.js') }}" defer></script>
  </body>
</html>
