{{-- Ditampilkan halaman yang butuh sesi aktif (papan antrean, hitungan match) saat belum ada sesi live. --}}
<header class="session-header">
    <div>
        <h1>{{ $heading }}</h1>
        <p class="session-meta">Belum ada sesi live yang berjalan.</p>
    </div>
</header>
<section class="no-session-card" aria-labelledby="no-session-title">
    <span class="empty-icon" aria-hidden="true">⌁</span>
    <h2 id="no-session-title">Mulai sesi live dulu</h2>
    <p>Donasi VIP baru masuk ke antrean setelah sesi dimulai. Kamu bisa menambah pemain manual begitu sesi berjalan.</p>
    <a class="button button-primary" href="/live-session">Mulai sesi live →</a>
</section>
