<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" /><meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="MabarQueue — pantau donasi VIP yang masuk dari berbagai provider." />
    <title>MabarQueue — Donasi Masuk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&family=Bricolage+Grotesque:opsz,wght@12..96,400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/donations.css') }}" />
  </head>
  <body>
    <div class="app-shell donation-shell">
      <aside class="sidebar" aria-label="Navigasi utama">
        <div class="brand-lockup">
          <div class="brand-mark" aria-hidden="true">MQ</div>
          <div><p class="brand-name">MabarQueue</p><p class="brand-subtitle">Dashboard streamer<br />VIP mabar Mobile Legends</p></div>
        </div>
        <nav class="main-nav">
          <a class="nav-link" href="/live-session"><span class="nav-icon" aria-hidden="true">⌂</span><span>Dashboard</span></a>
          <a class="nav-link" href="/dashboard#queue"><span class="nav-icon" aria-hidden="true">☷</span><span>Antrean</span></a>
          <a class="nav-link" href="/dashboard#queue"><span class="nav-icon" aria-hidden="true">♟</span><span>Pemain</span></a>
          <a class="nav-link is-active" href="/donations" aria-current="page"><span class="nav-icon" aria-hidden="true">★</span><span>VIP &amp; Donasi</span></a>
          <a class="nav-link" href="/match-count"><span class="nav-icon" aria-hidden="true">▥</span><span>Statistik</span></a>
          <a class="nav-link" href="/overlay-settings"><span class="nav-icon" aria-hidden="true">⚙</span><span>Pengaturan</span></a>
        </nav>
        <div class="sidebar-divider"></div>
        <section class="stream-status" aria-label="Status stream"><div class="status-line"><span class="status-dot is-live"></span><strong>Stream: Online</strong></div><p>Mode: Mabar VIP</p><p>Antrean: <strong>11 pemain</strong></p></section>
      </aside>

      <main class="main-content donation-content">
        <header class="donation-header">
          <div>
            <p class="eyebrow">Donasi masuk otomatis</p>
            <h1>VIP &amp; donasi</h1>
            <p class="session-meta">Cek donasi baru, validasi paket, lalu masukkan pemain ke antrean.</p>
          </div>
          <div class="donation-header-actions">
            <button class="button button-outline" id="refresh-donations" type="button">↻ Refresh</button>
            <a class="button button-primary" href="/dashboard#queue">Buka antrean</a>
          </div>
          <div class="sync-line" role="status" aria-live="polite"><span class="status-dot is-live"></span><span>Webhook aktif</span><span class="meta-separator">•</span><span id="last-synced">Terakhir dicek barusan</span></div>
        </header>

        <section class="donation-summary" aria-label="Ringkasan donasi">
          <article class="donation-stat donation-stat-red"><span>Donasi hari ini</span><strong id="total-donations">18</strong><small>transaksi masuk</small></article>
          <article class="donation-stat donation-stat-orange"><span>Siap masuk antrean</span><strong id="ready-donations">4</strong><small>perlu satu klik</small></article>
          <article class="donation-stat donation-stat-purple"><span>Nilai donasi</span><strong id="donation-total">Rp720K</strong><small>sejak sesi dimulai</small></article>
          <article class="donation-stat donation-stat-cream"><span>Provider aktif</span><strong>3/3</strong><small>koneksi sehat</small></article>
        </section>

        <section class="donation-toolbar" aria-label="Filter donasi">
          <div>
            <p class="eyebrow">Kotak masuk</p>
            <h2>Donasi terbaru</h2>
          </div>
          <div class="donation-filters" role="group" aria-label="Filter transaksi">
            <button class="filter-button is-active" data-status-filter="all" type="button">Semua <span>(6)</span></button>
            <button class="filter-button" data-status-filter="new" type="button">Baru <span>(2)</span></button>
            <button class="filter-button" data-status-filter="queued" type="button">Masuk antrean <span>(2)</span></button>
            <button class="filter-button" data-status-filter="review" type="button">Perlu cek <span>(1)</span></button>
          </div>
          <label class="provider-filter">Provider
            <select id="provider-filter" aria-label="Filter berdasarkan provider">
              <option value="all">Semua provider</option>
              <option value="Saweria">Saweria</option>
              <option value="SociaBuzz">SociaBuzz</option>
              <option value="Trakteer">Trakteer</option>
            </select>
          </label>
        </section>

        <section class="donation-table-wrap" aria-labelledby="donations-table-title">
          <h2 class="sr-only" id="donations-table-title">Daftar donasi terbaru</h2>
          <table class="donation-table">
            <caption class="sr-only">Daftar donasi sintetis dari provider yang terhubung</caption>
            <thead><tr><th scope="col">Waktu</th><th scope="col">Donatur</th><th scope="col">Sumber</th><th scope="col">Nominal</th><th scope="col">Paket</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
            <tbody id="donation-body"></tbody>
          </table>
          <div class="donation-card-list" id="donation-card-list"></div>
          <div class="donation-empty" id="donation-empty" hidden><strong>Tidak ada donasi di filter ini</strong><p>Coba pilih status atau provider lain.</p></div>
        </section>

        <section class="auto-queue-panel" aria-labelledby="auto-queue-title">
          <div class="auto-queue-heading"><div><p class="eyebrow">Otomatisasi antrean</p><h2 id="auto-queue-title">Penanda masuk antrean</h2><p class="auto-queue-subtitle">Donasi tervalidasi mendapat posisi berikutnya tanpa pencatatan ulang.</p></div><div class="auto-queue-actions"><span class="auto-queue-badge"><i></i>Simulasi aktif</span><button class="button button-light button-small" id="simulate-donation" type="button">+ Simulasikan donasi baru</button></div></div>
          <div class="queue-preview" id="queue-preview" aria-live="polite"></div>
        </section>

        <section class="donation-lower-grid">
          <article class="provider-panel" aria-labelledby="provider-title">
            <div class="panel-heading"><div><p class="eyebrow">Koneksi otomatis</p><h2 id="provider-title">Sumber donasi</h2></div><button class="filter-button" id="open-connection" type="button">+ Tambah koneksi</button></div>
            <div class="provider-list">
              <div class="provider-card"><div class="provider-brand provider-brand-saweria">S</div><div><strong>Saweria</strong><span>Webhook diterima 2 menit lalu</span></div><span class="connection-badge"><i></i>Aktif</span></div>
              <div class="provider-card"><div class="provider-brand provider-brand-sociabuzz">SB</div><div><strong>SociaBuzz</strong><span>Webhook diterima 5 menit lalu</span></div><span class="connection-badge"><i></i>Aktif</span></div>
              <div class="provider-card"><div class="provider-brand provider-brand-trakteer">T</div><div><strong>Trakteer</strong><span>Webhook diterima 8 menit lalu</span></div><span class="connection-badge"><i></i>Aktif</span></div>
            </div>
            <p class="provider-note">Status di atas adalah data tiruan. Form koneksi siap disambungkan ke webhook provider.</p>
            <div class="connection-history"><div class="connection-history-heading"><strong>Riwayat uji koneksi</strong><span>3 terakhir</span></div><div id="connection-history-list"></div></div>
          </article>

          <article class="recent-panel" aria-labelledby="recent-title">
            <div class="panel-heading"><div><p class="eyebrow">Realtime</p><h2 id="recent-title">Notifikasi masuk</h2></div><button class="filter-button" id="mark-all-read" type="button">Tandai dibaca</button></div>
            <div class="activity-list" id="activity-list" aria-live="polite"></div>
            <p class="notification-footnote"><span class="notification-pulse"></span><span id="notification-count">0 notifikasi baru</span> · diperbarui dari data tiruan</p>
          </article>
        </section>
      </main>
    </div>
    <div class="toast" id="donation-toast" role="status" aria-live="polite"></div>
    <dialog class="modal connection-modal" id="connection-modal" aria-labelledby="connection-title">
      <form method="dialog" class="modal-card" id="connection-form">
        <div class="modal-header"><div><p class="eyebrow">Konfigurasi sumber</p><h2 id="connection-title">Hubungkan platform</h2></div><button class="icon-button" value="cancel" aria-label="Tutup modal">×</button></div>
        <label class="field-label" for="connection-provider">Platform donasi</label>
        <select class="field-input" id="connection-provider" name="connection-provider"><option value="Saweria">Saweria</option><option value="SociaBuzz">SociaBuzz</option><option value="Trakteer">Trakteer</option></select>
        <label class="field-label" for="connection-url">URL webhook</label>
        <input class="field-input" id="connection-url" name="connection-url" placeholder="https://contoh.provider/webhook" inputmode="url" required />
        <label class="field-label" for="connection-secret">Signing secret <span>(tiruan)</span></label>
        <input class="field-input" id="connection-secret" name="connection-secret" placeholder="Masukkan secret provider" type="password" minlength="6" required />
        <p class="connection-help">Secret hanya dipakai untuk validasi signature webhook. Data pada prototipe ini tidak dikirim ke provider.</p>
        <p class="form-error" id="connection-error" role="alert" hidden></p>
        <div class="connection-test" id="connection-test" hidden><span class="status-dot"></span><strong id="connection-test-label">Koneksi siap</strong></div>
        <div class="modal-actions"><button class="button button-outline" value="cancel" type="button" id="close-connection">Batal</button><button class="button button-light" id="test-connection" type="button">Tes koneksi</button><button class="button button-primary" value="default" type="submit">Simpan koneksi</button></div>
      </form>
    </dialog>
    <script src="{{ asset('js/api.js') }}" defer></script><script src="{{ asset('js/donations.js') }}" defer></script>
  </body>
</html>
