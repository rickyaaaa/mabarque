<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" /><meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Pulihkan akses akun streamer MabarQueue." />
    <title>MabarQueue — Lupa Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&family=Bricolage+Grotesque:opsz,wght@12..96,400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/signup.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/login.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/forgot-password.css') }}" />
  </head>
  <body class="login-body">
    <main class="login-shell">
      <header class="signup-header">
        <a class="public-brand" href="/"><span class="brand-mark" aria-hidden="true">MQ</span><span>MabarQueue</span></a>
        <span>Ingat password? <a href="/login">Masuk</a></span>
      </header>

      <section class="login-grid">
        <div class="login-pitch">
          <p class="eyebrow">Pulihkan akses</p>
          <h1>Lupa password?<br /><span>Santai.</span></h1>
          <p>Kami kirim link reset ke email akun kamu. Antrean, paket VIP, dan pengaturan overlay tetap aman selama kamu memulihkan akses.</p>
          <ol class="reset-steps" aria-label="Tahapan pemulihan">
            <li data-step-indicator="request" class="is-current"><i>1</i><span>Masukkan email</span></li>
            <li data-step-indicator="sent"><i>2</i><span>Buka link dari email</span></li>
            <li data-step-indicator="reset"><i>3</i><span>Buat password baru</span></li>
          </ol>
        </div>

        <section class="login-card reset-card" aria-live="polite">
          <!-- Langkah 1: minta link reset -->
          <div class="reset-step" data-step="request">
            <div class="signup-card-heading">
              <p class="eyebrow">Langkah 1 dari 3</p>
              <h2 id="reset-title" tabindex="-1">Reset password</h2>
              <p>Masukkan email yang kamu pakai untuk masuk ke workspace.</p>
            </div>
            <form id="request-form" novalidate>
              <label class="field-label" for="reset-email">Email</label>
              <input class="field-input" id="reset-email" type="email" autocomplete="email" placeholder="kamu@email.com" required />
              <p class="form-error" id="request-error" role="alert" hidden></p>
              <button class="button button-primary full-width reset-submit" type="submit">Kirim link reset →</button>
            </form>
            <p class="login-footnote">Demi keamanan, kami tidak memberi tahu apakah sebuah email terdaftar.</p>
          </div>

          <!-- Langkah 2: cek email -->
          <div class="reset-step" data-step="sent" hidden>
            <div class="signup-card-heading">
              <p class="eyebrow">Langkah 2 dari 3</p>
              <h2 tabindex="-1">Cek email</h2>
              <p>Kalau <strong id="sent-email">email kamu</strong> terdaftar, link reset sudah dikirim. Link berlaku 60 menit.</p>
            </div>
            <article class="mock-inbox" aria-label="Petunjuk email reset">
              <header>
                <span class="mock-inbox-badge">Email terkirim</span>
                <small id="mock-inbox-time">baru saja</small>
              </header>
              <strong>Subjek: Reset password MabarQueue</strong>
              <p>Buka email itu lalu klik <b>Buat password baru</b>. Tidak ada di inbox? Cek folder Spam atau Promosi, atau kirim ulang setelah hitung mundur selesai.</p>
            </article>
            <div class="reset-secondary-actions">
              <button class="button button-outline button-small" id="resend-link" type="button" disabled>Kirim ulang (<span id="resend-countdown">30</span>)</button>
              <button class="text-link" id="change-email" type="button">Ganti email</button>
            </div>
          </div>

          <!-- Langkah 3: password baru -->
          <div class="reset-step" data-step="reset" hidden>
            <div class="signup-card-heading">
              <p class="eyebrow">Langkah 3 dari 3</p>
              <h2 tabindex="-1">Password baru</h2>
              <p>Untuk akun <strong id="reset-account">-</strong>. Pakai minimal 8 karakter.</p>
            </div>
            <form id="reset-form" novalidate>
              <div class="password-label">
                <label class="field-label" for="new-password">Password baru</label>
                <button type="button" data-toggle-password="new-password">Tampilkan</button>
              </div>
              <input class="field-input" id="new-password" type="password" autocomplete="new-password" minlength="8" placeholder="Minimal 8 karakter" required aria-describedby="strength-label" />
              <div class="strength" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
              <p class="strength-label" id="strength-label">Kekuatan: belum diisi</p>

              <div class="password-label">
                <label class="field-label" for="confirm-password">Ulangi password</label>
                <button type="button" data-toggle-password="confirm-password">Tampilkan</button>
              </div>
              <input class="field-input" id="confirm-password" type="password" autocomplete="new-password" placeholder="Ketik ulang password" required />

              <ul class="password-rules" aria-label="Syarat password">
                <li data-rule="length"><i aria-hidden="true">✕</i><span>Minimal 8 karakter</span></li>
                <li data-rule="mixed"><i aria-hidden="true">✕</i><span>Ada huruf dan angka</span></li>
                <li data-rule="match"><i aria-hidden="true">✕</i><span>Kedua password sama</span></li>
              </ul>
              <p class="form-error" id="reset-error" role="alert" hidden></p>
              <button class="button button-primary full-width reset-submit" type="submit">Simpan password baru →</button>
            </form>
          </div>

          <!-- Selesai -->
          <div class="reset-step reset-done" data-step="done" hidden>
            <span class="success-icon" aria-hidden="true">✓</span>
            <p class="eyebrow">Akses pulih</p>
            <h2 tabindex="-1">Password diganti.</h2>
            <p>Sesi di perangkat lain sudah dikeluarkan. Masuk lagi dengan password baru kamu.</p>
            <a class="button button-primary full-width" href="/login">Masuk ke workspace →</a>
          </div>
        </section>
      </section>

      <footer class="signup-footer"><span>© 2025 MabarQueue</span><span>Link reset berlaku 60 menit</span></footer>
    </main>
    <div class="toast" id="reset-toast" role="status" aria-live="polite"></div>
    <script src="{{ asset('js/api.js') }}" defer></script><script src="{{ asset('js/forgot-password.js') }}" defer></script>
  </body>
</html>
