@php
    $links = ['#cara-kerja' => 'Cara Kerja', '#fitur' => 'Fitur', '#faq' => 'FAQ'];
    $party = [['Raka Rift', 'RR', 3], ['Naya Roam', 'NR', 2]];
    $waiting = [['Dina Dash', 'DD', 7], ['Fikri Flick', 'FF', 5], ['Alma Core', 'AC', 2]];
    $score = ['wins' => 3, 'losses' => 1];
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>MabarQueue | Antrean mabar VIP untuk streamer Mobile Legends</title>
    <meta name="description" content="Atur paket VIP, kelola party dan Next game, lalu bagikan link agar viewer bisa cek sisa game. Daftar akses MabarQueue untuk streamer Mobile Legends." />
    <meta name="theme-color" content="#EEEDE6" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400..900&amp;display=swap" />
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/landing.css') }}" />
</head>
<body class="landing">
    <a class="lp-skip" href="#konten">Lewati ke konten</a>
    <header class="lp-header">
        <div class="lp-shell lp-header-inner">
            <a class="lp-brand" href="/" aria-label="MabarQueue, beranda">
                <span class="lp-brand-mark" aria-hidden="true">MQ</span> MabarQueue
            </a>
            <nav class="lp-nav lp-nav-desktop" aria-label="Navigasi utama">
                @foreach ($links as $href => $label)
                    <a href="{{ $href }}">{{ $label }}</a>
                @endforeach
            </nav>
            <div class="lp-header-actions">
                <a class="lp-text-link" href="{{ route('login', absolute: false) }}">Masuk</a>
                <a class="lp-button lp-primary" href="{{ route('signup', absolute: false) }}">Daftar akses</a>
            </div>
            <nav class="lp-nav lp-nav-mobile" aria-label="Navigasi utama">
                @foreach ($links as $href => $label)
                    <a href="{{ $href }}">{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main id="konten" tabindex="-1">
        <section class="lp-shell lp-hero" aria-labelledby="hero-title">
            <div class="lp-hero-copy">
                <h1 id="hero-title">Kelola giliran <mark>mabar VIP</mark> dan sisa game dalam satu dashboard</h1>
                <p>Atur paket mabar Mobile Legends, pilih siapa yang main berikutnya, dan bagikan satu link supaya viewer bisa cek antreannya sendiri.</p>
                <div class="lp-actions">
                    <a class="lp-button lp-primary" href="{{ route('signup', absolute: false) }}">Daftar akses @include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</a>
                    <a class="lp-button" href="#cara-kerja">Lihat cara kerja</a>
                </div>
                <p class="lp-access-note">Akses private alpha aktif setelah disetujui admin.</p>
            </div>

            <figure class="lp-preview lp-board">
                <figcaption class="lp-preview-bar"><span>Papan antrean</span><span>Contoh tampilan · Data fiktif</span></figcaption>
                <div class="lp-board-inner">
                    <div class="lp-board-heading">
                        <div><span class="lp-live">Live</span><strong>Mabar VIP Jumat</strong></div>
                        <div class="lp-board-score"><span>Win / Lose</span><strong>{{ $score['wins'] }} &ndash; {{ $score['losses'] }}</strong></div>
                    </div>
                    <div class="lp-board-party">
                        <div><h2>Sedang main</h2><span>2 pemain + streamer</span></div>
                        <ul class="lp-members">
                            @foreach ($party as [$name, $initials, $remaining])
                                <li><span class="lp-avatar" aria-hidden="true">{{ $initials }}</span><span><strong>{{ $name }}</strong><small>Sisa {{ $remaining }} game</small></span></li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="lp-board-next">
                        <h2>Next game</h2>
                        <span>{{ $waiting[0][0] }}</span><span class="lp-manual">Manual</span>
                    </div>
                    <table class="lp-board-table">
                        <caption>Daftar antrean</caption>
                        <thead><tr><th scope="col">Pemain</th><th scope="col">Sisa game</th></tr></thead>
                        <tbody>
                            @foreach ($waiting as [$name, $initials, $remaining])
                                <tr><td>{{ $name }}</td><td><span class="lp-quota">{{ $remaining }}</span></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </figure>
        </section>

        <section class="lp-process lp-section" id="cara-kerja" aria-labelledby="process-title">
            <div class="lp-shell lp-process-grid">
                <div>
                    <h2 id="process-title">Dari donasi sampai pemain masuk party</h2>
                    <ol class="lp-steps">
                        <li><h3>Atur paket dan sambungkan donasi</h3><p>Tentukan harga dan jumlah game tiap paket. Hubungkan SociaBuzz, Saweria, atau Trakteer untuk menerima notifikasi donasi.</p></li>
                        <li><h3>Tentukan cara pemain masuk antrean</h3><p>Konfirmasi donasi satu per satu atau aktifkan mode otomatis sesuai aturanmu. Kamu juga bisa tambah pemain dan tempel daftar dari Notepad.</p></li>
                        <li><h3>Pilih party dan catat game selesai</h3><p>Susun Next game dari papan antrean. Saat kamu klik Selesai game, MabarQueue memperbarui sisa game pemain di party.</p></li>
                    </ol>
                </div>
                <figure class="lp-calculation">
                    <figcaption>Contoh paket streamer</figcaption>
                    <dl>
                        <div><dt>Rp25.000</dt><dd>2 game</dd></div>
                        <div><dt>Rp50.000</dt><dd>5 game</dd></div>
                    </dl>
                    <div class="lp-calculation-result"><span>Donasi Rp75.000</span>@include('partials.icon', ['name' => 'arrow-right', 'size' => 22])<strong>7 game</strong></div>
                    <p>Jumlah game mengikuti paket aktif yang kamu atur.</p>
                </figure>
            </div>
        </section>

        <section class="lp-shell lp-section" id="fitur" aria-labelledby="features-title">
            <h2 id="features-title">Sisa game ikut ke sesi berikutnya</h2>
            <div class="lp-features">
                <article>
                    @include('partials.icon', ['name' => 'clipboard', 'size' => 25])
                    <h3>Lanjut dari antrean terakhir</h3>
                    <p>Sisa game tersimpan setelah live selesai. Kelola daftar sebelum live, lalu buka arsip untuk melihat rekap sesi sebelumnya.</p>
                </article>
                <article>
                    @include('partials.icon', ['name' => 'users', 'size' => 25])
                    <h3>Atur party sesuai cara kamu main</h3>
                    <p>Tentukan jumlah pemain dan batas game per orang tiap sesi. Pilih Next game secara manual. Kalau aturan MVP aktif, pemain yang kamu tandai MVP bisa main lagi tanpa mengurangi jatahnya.</p>
                </article>
                <article>
                    @include('partials.icon', ['name' => 'tag', 'size' => 25])
                    <h3>Catat jasa mainin akun dan reward</h3>
                    <p>Pantau pesanan VIP Mainin akun dari Menunggu sampai Selesai. Buat paket reward seperti follow back Instagram dengan keterangan hadiah sendiri.</p>
                </article>
            </div>
        </section>

        <section class="lp-section lp-viewer-section" aria-labelledby="viewer-title">
            <div class="lp-shell lp-showcase lp-viewer-showcase">
                <div class="lp-showcase-copy">
                    <h2 id="viewer-title">Viewer bisa cek sisa game sendiri</h2>
                    <p>Bagikan link antrean di deskripsi live atau chat. Viewer bisa cari nama atau ID MLBB, melihat siapa yang sedang main, dan cek sisa game tanpa bertanya ke streamer.</p>
                    <p>Streamer bisa menyamarkan sebagian nama dan ID MLBB. Paket di halaman viewer bisa ditautkan ke platform donasi pilihanmu.</p>
                </div>
                <figure class="lp-preview lp-viewer">
                    <figcaption class="lp-preview-bar"><span>Halaman viewer</span><span>Contoh tampilan · Data fiktif</span></figcaption>
                    <div class="lp-viewer-inner">
                        <div class="lp-viewer-head"><strong>Daftar antrean</strong><span class="lp-live">Live sekarang</span></div>
                        <div class="lp-search-preview">
                            @include('partials.icon', ['name' => 'search', 'size' => 16])
                            <span>Cari nama atau ID MLBB</span><small>Contoh</small>
                        </div>
                        <ul class="lp-viewer-list">
                            @foreach ($waiting as [$name, $initials, $remaining])
                                <li>
                                    <span class="lp-avatar" aria-hidden="true">{{ $initials }}</span>
                                    <span><strong>{{ $name }}</strong><small>Mabar VIP</small></span>
                                    <span class="lp-viewer-quota"><strong>{{ $remaining }}</strong><small>game</small></span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </figure>
            </div>
        </section>

        <section class="lp-shell lp-section lp-showcase lp-obs-showcase" aria-labelledby="obs-title">
            <div class="lp-showcase-copy">
                <h2 id="obs-title">Tampilkan yang perlu dilihat di OBS</h2>
                <p>Pasang skor Win / Lose, party yang sedang main, antrean berikutnya, atau harga paket sebagai Browser Source terpisah. Atur ukuran dan warna latarnya, lalu tempatkan tiap widget di scene OBS.</p>
            </div>
            <figure class="lp-preview lp-obs">
                <figcaption class="lp-preview-bar"><span>Scene OBS</span><span>Contoh tampilan · Data fiktif</span></figcaption>
                <div class="lp-scene">
                    <img src="{{ \App\Support\Asset::url('images/landing-obs-scene.webp') }}" width="960" height="540" alt="" loading="lazy" decoding="async" />
                    <div class="lp-widget lp-widget-score" aria-label="Skor dari dashboard">
                        <span>Win <strong>{{ $score['wins'] }}</strong></span>
                        <span>Lose <strong>{{ $score['losses'] }}</strong></span>
                    </div>
                    <div class="lp-widget lp-widget-party">
                        <strong>Sedang main</strong>
                        <ul>
                            @foreach ($party as [$name, $initials, $remaining])
                                <li><span>{{ $name }}</span><span>{{ $remaining }} game</span></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </figure>
        </section>

        <section class="lp-faq-section lp-section" id="faq" aria-labelledby="faq-title">
            <div class="lp-shell lp-faq">
                <h2 id="faq-title">Tanya jawab</h2>
                <div class="lp-questions">
                    <details>
                        <summary>Apakah semua donasi masuk antrean?</summary>
                        <p>Kamu bisa pilih mode otomatis, konfirmasi manual, atau otomatis hanya jika pesan berisi ID MLBB atau kata mabar atau VIP. Donasi di bawah harga paket termurah tetap tercatat tanpa menambah pemain ke antrean.</p>
                    </details>
                    <details>
                        <summary>Bisa pindahin antrean lama dari Notepad?</summary>
                        <p>Bisa. Tempel banyak pemain sekaligus dengan nama, ID MLBB jika ada, dan sisa gamenya.</p>
                    </details>
                    <details>
                        <summary>Bisa mulai dan akhiri sesi dari OBS?</summary>
                        <p>Bisa lewat Browser Source MabarQueue. Aktifkan opsi mulai atau akhiri sesi otomatis di pengaturan Overlay OBS sesuai kebutuhanmu.</p>
                    </details>
                    <details>
                        <summary>Apakah akun langsung aktif setelah daftar?</summary>
                        <p>Admin meninjau pengajuan akses private alpha sebelum dashboard streamer bisa digunakan.</p>
                    </details>
                </div>
            </div>
        </section>

        <section class="lp-shell lp-close" aria-labelledby="close-title">
            <div class="lp-close-panel">
                <div><h2 id="close-title">Siapkan antrean untuk live berikutnya</h2><p>Daftar akses MabarQueue untuk mengatur paket, giliran main, dan sisa game dari satu dashboard.</p></div>
                <div class="lp-close-actions">
                    <a class="lp-button lp-dark" href="{{ route('signup', absolute: false) }}">Daftar akses @include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</a>
                    <a class="lp-text-link" href="{{ route('login', absolute: false) }}">Sudah punya akun? Masuk</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="lp-footer">
        <div class="lp-shell lp-footer-inner">
            <div>
                <a class="lp-brand" href="/"><span class="lp-brand-mark" aria-hidden="true">MQ</span> MabarQueue</a>
                <p>Dashboard mabar VIP untuk streamer Mobile Legends</p>
            </div>
            <nav class="lp-nav" aria-label="Navigasi footer">
                @foreach ($links as $href => $label)
                    <a href="{{ $href }}">{{ $label }}</a>
                @endforeach
                <a href="{{ route('login', absolute: false) }}">Masuk</a>
                <a href="{{ route('signup', absolute: false) }}">Daftar akses</a>
            </nav>
        </div>
    </footer>
</body>
</html>
