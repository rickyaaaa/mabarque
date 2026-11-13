<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" /><meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MabarQueue — Paket VIP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,600;12..96,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/vip-packages.css') }}" />
  </head>
  <body>
    <div class="app-shell packages-shell">
      <aside class="sidebar" aria-label="Navigasi utama">
        <div class="brand-lockup"><div class="brand-mark" aria-hidden="true">MQ</div><div><p class="brand-name">MabarQueue</p><p class="brand-subtitle">Dashboard streamer<br />VIP mabar Mobile Legends</p></div></div>
        <nav class="main-nav">
          <a class="nav-link" href="/live-session"><span class="nav-icon" aria-hidden="true">⌂</span><span>Dashboard</span></a><a class="nav-link" href="/dashboard#queue"><span class="nav-icon" aria-hidden="true">☷</span><span>Antrean</span></a><a class="nav-link" href="/dashboard#queue"><span class="nav-icon" aria-hidden="true">♟</span><span>Pemain</span></a><a class="nav-link is-active" href="/vip-packages" aria-current="page"><span class="nav-icon" aria-hidden="true">★</span><span>VIP &amp; Donasi</span></a><a class="nav-link" href="/match-count"><span class="nav-icon" aria-hidden="true">▥</span><span>Statistik</span></a><a class="nav-link" href="/overlay-settings"><span class="nav-icon" aria-hidden="true">⚙</span><span>Pengaturan</span></a>
        </nav><div class="sidebar-divider"></div><section class="stream-status" aria-label="Status stream"><div class="status-line"><span class="status-dot is-live"></span><strong>Stream: Online</strong></div><p>Mode: Mabar VIP</p><p>Antrean: <strong>11 pemain</strong></p></section>
      </aside>
      <main class="main-content packages-content">
        <header class="packages-header"><div><p class="eyebrow">Harga fleksibel untuk komunitas</p><h1>Paket VIP</h1><p class="session-meta">Atur nominal donasi dan jatah match yang tampil ke penonton.</p></div><button class="button button-primary" id="add-package" type="button">+ Buat paket baru</button></header>
        <section class="package-summary"><span><strong id="active-count">3</strong> paket aktif</span><span>Total donasi hari ini <strong>Rp1.240.000</strong></span><span class="summary-note">Data tiruan</span></section>
        <section class="package-grid" id="package-grid" aria-label="Daftar paket VIP"></section><div class="package-empty" id="package-empty" role="status" hidden><strong>Belum ada paket aktif</strong><p>Aktifkan kembali paket atau buat paket baru untuk ditampilkan ke penonton.</p><button class="button button-primary" type="button" id="empty-add-package">Buat paket pertama</button></div>
        <section class="package-preview"><div><p class="eyebrow">Tampilan penonton</p><h2>Pratinjau paket di halaman publik</h2><p>Penonton melihat paket aktif sebelum memilih nominal donasi.</p></div><a class="button button-outline" href="/dashboard#queue">Lihat halaman publik</a><div class="public-package-preview" id="public-package-preview"></div></section>
      </main>
    </div>
    <div class="toast" id="package-toast" role="status" aria-live="polite"></div>
    <dialog class="modal" id="package-modal" aria-labelledby="package-modal-title"><form class="modal-card" id="package-form"><div class="modal-header"><div><p class="eyebrow">Paket donasi</p><h2 id="package-modal-title">Buat paket VIP</h2></div><button class="icon-button" type="button" id="close-package-modal" aria-label="Tutup modal">×</button></div><label class="field-label" for="package-name">Nama paket</label><input class="field-input" id="package-name" name="package-name" placeholder="Contoh: Squad" required /><p class="form-error" id="package-error" role="alert" hidden></p><div class="field-grid"><div><label class="field-label" for="package-price">Harga donasi</label><input class="field-input" id="package-price" name="package-price" type="number" min="1000" step="1000" value="50000" required /></div><div><label class="field-label" for="package-matches">Jatah match</label><input class="field-input" id="package-matches" name="package-matches" type="number" min="1" max="99" value="5" required /></div></div><div class="modal-actions"><button class="button button-outline" type="button" id="cancel-package-modal">Batal</button><button class="button button-primary" type="submit">Simpan paket</button></div></form></dialog>
    <script src="{{ asset('js/api.js') }}" defer></script><script src="{{ asset('js/vip-packages.js') }}" defer></script>
  </body>
</html>

