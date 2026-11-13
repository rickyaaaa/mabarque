@php
    $statusLabels = ['ready' => 'Siap', 'offline' => 'Offline', 'playing' => 'Sedang bermain'];
    $providerLabels = ['manual' => 'Manual', 'saweria' => 'Saweria', 'sociabuzz' => 'SociaBuzz', 'trakteer' => 'Trakteer'];
@endphp

<div wire:poll.10s>
    <header class="live-header">
        <div>
            <p class="eyebrow">Kelola sesi live</p>
            <h1>Sesi mabar VIP</h1>
            <p class="session-meta">Buka sesi sebelum live supaya donasi VIP otomatis masuk ke antrean sesi tersebut.</p>
        </div>
        <div class="live-header-actions">
            <span @class(['session-state-badge', 'is-idle' => ! $session])><i></i>{{ $session ? 'Sedang live' : 'Belum live' }}</span>
        </div>
    </header>

    @if ($session)
        {{-- ── Sesi berjalan ──────────────────────────────────────── --}}
        <section class="live-hero" aria-labelledby="active-session-title">
            <div class="live-hero-main">
                <div class="live-hero-label"><span class="status-dot is-live"></span> Sesi aktif</div>
                <h2 id="active-session-title">{{ $session->title }}</h2>
                <p x-data="{ start: {{ $session->started_at?->getTimestamp() ?? 'null' }}, now: Date.now() / 1000 }"
                   x-init="setInterval(() => now = Date.now() / 1000, 1000)">
                    Dimulai {{ $session->started_at?->format('H:i') }} WIB <span>•</span>
                    <strong x-text="start ? new Date(Math.max(0, now - start) * 1000).toISOString().substring(11, 19) : '—'"></strong> berjalan
                </p>
                <div class="live-hero-actions">
                    <a class="button button-primary" href="/dashboard">Buka papan antrean</a>
                    <button class="button button-outline" type="button" wire:click="endSession"
                            wire:confirm="Akhiri sesi &quot;{{ $session->title }}&quot;? Donasi baru tidak akan masuk ke antrean ini lagi.">Akhiri sesi</button>
                </div>
            </div>
            <div class="live-hero-side">
                <span>Pemain siap</span><strong>{{ $readyCount }}</strong>
                <span>Donasi perlu dicek</span><strong>{{ $pendingDonations }}</strong>
                <span>Total pemain sesi</span><strong>{{ $session->total_players }}</strong>
            </div>
        </section>

        <section class="live-stat-grid" aria-label="Statistik sesi berjalan">
            <article class="live-stat live-stat-red"><span>Pemain di antrean</span><strong>{{ $queue->count() }}</strong><small>{{ $readyCount }} siap bermain</small></article>
            <article class="live-stat live-stat-orange"><span>Nilai donasi</span><strong>Rp {{ number_format($session->total_donation, 0, ',', '.') }}</strong><small>{{ $donationCount }} donasi sesi ini</small></article>
            <article class="live-stat live-stat-purple"><span>Match selesai</span><strong>{{ $session->total_matches }}</strong><small>sejak sesi dimulai</small></article>
            <article class="live-stat live-stat-cream"><span>Durasi live</span><strong>{{ $session->started_at ? $session->started_at->diff(now())->format('%hj %Im') : '—' }}</strong><small>dimulai {{ $session->started_at?->format('H:i') }} WIB</small></article>
        </section>

        <section class="live-queue-panel" aria-labelledby="live-queue-title">
            <div class="panel-heading">
                <div><p class="eyebrow">Snapshot sesi</p><h2 id="live-queue-title">Antrean berikutnya</h2></div>
                <button class="button button-outline button-small" type="button" wire:click="resetQueue" @disabled($queue->isEmpty())
                        wire:confirm="Kosongkan antrean? Semua pemain yang tersisa ditandai selesai.">Reset antrean</button>
            </div>
            @if ($nextUp->isEmpty())
                <div class="live-queue-empty">
                    <strong>Antrean sesi kosong</strong>
                    <p>Donasi VIP yang cocok dengan paket akan muncul di sini, atau tambahkan pemain dari papan antrean.</p>
                </div>
            @else
                <div class="live-queue-list">
                    @foreach ($nextUp as $entry)
                        <div class="live-queue-item" wire:key="next-{{ $entry->id }}">
                            <span class="live-queue-position">{{ $entry->position }}</span>
                            <div>
                                <strong>{{ $entry->player_name }}</strong>
                                <span>{{ $statusLabels[$entry->status] ?? $entry->status }} · {{ $providerLabels[$entry->provider] ?? $entry->provider }} · sisa {{ max(0, $entry->matches_total - $entry->matches_played) }} match</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @else
        {{-- ── Mulai sesi baru ────────────────────────────────────── --}}
        <section class="live-hero start-session-hero" aria-labelledby="start-session-title">
            <form class="live-hero-main" wire:submit="startSession">
                <div class="live-hero-label"><span class="status-dot"></span> Belum ada sesi aktif</div>
                <h2 id="start-session-title">Mulai sesi live</h2>
                <label class="field-label" for="session-title">Nama sesi <span>(tampil di halaman publik & arsip)</span></label>
                <input class="field-input @error('title') is-invalid @enderror" id="session-title" wire:model="title" maxlength="120" />
                @error('title') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <div class="live-hero-actions">
                    <button class="button button-primary" type="submit" wire:loading.attr="disabled">▶ Mulai sesi sekarang</button>
                </div>
            </form>
            <div class="live-hero-side">
                <span>Setelah dimulai</span><strong>Donasi masuk antrean</strong>
                <span>Halaman publik</span><strong>Status: live</strong>
                <span>Arsip sesi</span><strong>{{ $archiveTotal }}</strong>
            </div>
        </section>
    @endif

    <section class="live-grid">
        <article class="session-control-panel" aria-labelledby="quick-control-title">
            <div class="panel-heading"><div><p class="eyebrow">Tautan cepat</p><h2 id="quick-control-title">Bagikan & tampilkan</h2></div></div>
            <div class="control-list">
                <a class="control-row" href="/overlay-settings">
                    <span class="control-icon control-icon-purple">▣</span>
                    <span><strong>Overlay OBS</strong><small>Atur tampilan & salin link browser source</small></span>
                    <span class="control-arrow" aria-hidden="true">→</span>
                </a>
                @if ($isPublic)
                    <button class="control-row" type="button"
                            x-data
                            x-on:click="navigator.clipboard.writeText(@js($publicUrl)).then(() => $dispatch('notify', { message: 'Link antrean publik disalin.' }))">
                        <span class="control-icon control-icon-orange">↗</span>
                        <span><strong>Salin link antrean publik</strong><small>{{ $publicUrl }}</small></span>
                        <span class="control-arrow" aria-hidden="true">⧉</span>
                    </button>
                @endif
                <a class="control-row" href="/vip-packages">
                    <span class="control-icon control-icon-green">★</span>
                    <span><strong>Paket VIP</strong><small>Nominal donasi menentukan jatah match</small></span>
                    <span class="control-arrow" aria-hidden="true">→</span>
                </a>
            </div>
        </article>

        <article class="recent-sessions-panel" aria-labelledby="recent-sessions-title">
            <div class="panel-heading">
                <div><p class="eyebrow">Riwayat</p><h2 id="recent-sessions-title">Arsip sesi selesai</h2></div>
                <span class="panel-count">{{ $archiveTotal }} sesi</span>
            </div>
            <div class="session-history">
                @forelse ($archives as $archive)
                    <div class="session-history-row" wire:key="archive-{{ $archive->id }}">
                        <span class="session-history-icon">✓</span>
                        <div>
                            <strong>{{ $archive->title }}</strong>
                            <small>{{ $archive->started_at?->translatedFormat('j M Y') }} · {{ $archive->started_at?->format('H:i') }}–{{ $archive->ended_at?->format('H:i') }} · {{ $archive->total_players }} pemain · Rp {{ number_format($archive->total_donation, 0, ',', '.') }}</small>
                        </div>
                        <span class="session-history-value">{{ $archive->total_matches }} match</span>
                    </div>
                @empty
                    <p class="session-meta">Belum ada sesi yang selesai.</p>
                @endforelse
            </div>
            @if ($archiveTotal > $archives->count())
                <div class="session-history-actions">
                    <button class="button button-outline button-small" type="button" wire:click="loadMoreArchives">Muat arsip lain</button>
                </div>
            @endif
        </article>
    </section>
</div>
