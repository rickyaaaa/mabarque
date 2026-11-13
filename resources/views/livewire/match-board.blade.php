@php
    $providerLabels = ['manual' => 'Manual', 'saweria' => 'Saweria', 'sociabuzz' => 'SociaBuzz', 'trakteer' => 'Trakteer'];
    $initials = fn (string $name) => mb_strtoupper(collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('')) ?: '?';
@endphp

<div wire:poll.10s>
    <header class="match-header">
        <div>
            <p class="eyebrow">Sesi berjalan</p>
            <h1>Hitungan match</h1>
            <p class="session-meta">Pantau jatah pemain selama sesi <strong>{{ $session->title }}</strong>.</p>
        </div>
        <a class="button button-outline" href="/dashboard">Kembali ke antrean</a>
    </header>

    @error('match') <p class="form-error queue-error" role="alert">{{ $message }}</p> @enderror

    <section class="match-summary" aria-label="Ringkasan match">
        <article class="match-stat match-stat-red"><span>Match selesai</span><strong>{{ $session->total_matches }}</strong><small>di sesi ini</small></article>
        <article class="match-stat match-stat-orange"><span>Pemain aktif</span><strong>{{ $activeWithQuota }}</strong><small>masih punya jatah</small></article>
        <article class="match-stat match-stat-purple"><span>Jatah menipis</span><strong>{{ $lowQuota }}</strong><small>tersisa ≤ 2 match</small></article>
    </section>

    <section class="match-focus" aria-labelledby="match-focus-title">
        @if ($current)
            <div>
                <p class="eyebrow">{{ $current->status === 'playing' ? 'Pemain saat ini' : 'Giliran berikutnya' }}</p>
                <h2 id="match-focus-title">{{ $current->player_name }}</h2>
                <p>{{ $providerLabels[$current->provider] ?? $current->provider }} <span>•</span> {{ $current->matches_played }}/{{ $current->matches_total }} match <span>•</span> {{ $matches->remaining($current) }} match tersisa</p>
            </div>
            <div class="match-focus-actions">
                <button class="button button-primary" type="button" wire:click="addMatch({{ $current->id }})" wire:loading.attr="disabled">✓ Tandai match selesai</button>
                <button class="button button-outline" type="button" wire:click="reduceMatch({{ $current->id }})" @disabled($current->matches_played === 0)>− Koreksi 1 match</button>
            </div>
        @else
            <div>
                <p class="eyebrow">Pemain saat ini</p>
                <h2 id="match-focus-title">Belum ada pemain</h2>
                <p>Tambahkan pemain dari papan antrean atau tunggu donasi VIP masuk.</p>
            </div>
        @endif
    </section>

    <section class="match-list-panel" aria-labelledby="match-list-title">
        <div class="match-list-head">
            <div><h2 id="match-list-title">Jatah pemain</h2><p>+1 mencatat match selesai, −1 mengoreksi salah catat.</p></div>
        </div>
        <div class="match-list">
            @forelse ($entries as $entry)
                @php
                    $remaining = $matches->remaining($entry);
                    $progress = $entry->matches_total > 0 ? (int) round($entry->matches_played / $entry->matches_total * 100) : 0;
                @endphp
                <article wire:key="match-{{ $entry->id }}" @class(['match-row', 'is-low' => $remaining <= 2, 'is-done' => $entry->status === 'done'])>
                    <div class="match-row-name"><span class="avatar" style="background: {{ $entry->id % 2 ? '#3b1945' : '#d82a1c' }}">{{ $initials($entry->player_name) }}</span><strong>{{ $entry->player_name }}</strong></div>
                    <div class="match-row-meta"><span>Sumber</span><strong>{{ $providerLabels[$entry->provider] ?? $entry->provider }}</strong></div>
                    <div><span class="sr-only">Progres {{ $entry->player_name }}: {{ $entry->matches_played }} dari {{ $entry->matches_total }} match</span><div class="match-progress"><span style="width: {{ $progress }}%"></span></div></div>
                    <div class="match-row-count">
                        {{ $entry->matches_played }}/{{ $entry->matches_total }}
                        @if ($remaining === 0)<span class="quota-badge is-empty">Jatah habis</span>@elseif ($remaining <= 2)<span class="quota-badge">Sisa {{ $remaining }}</span>@endif
                    </div>
                    <div class="match-row-actions">
                        <button class="match-row-action" type="button" wire:click="addMatch({{ $entry->id }})" @disabled($remaining === 0) aria-label="Tambah 1 match untuk {{ $entry->player_name }}">+1 match</button>
                        <button class="match-row-action" type="button" wire:click="reduceMatch({{ $entry->id }})" @disabled($entry->matches_played === 0) aria-label="Koreksi 1 match {{ $entry->player_name }}">−1</button>
                    </div>
                </article>
            @empty
                <p class="session-meta">Belum ada pemain di sesi ini.</p>
            @endforelse
        </div>
    </section>

    <section class="history-panel" aria-labelledby="history-title">
        <div class="match-list-head">
            <div><h2 id="history-title">Riwayat hari ini</h2><p>Match yang sudah dicatat dalam sesi berjalan.</p></div>
            <span class="history-date">{{ now()->translatedFormat('l, j M Y') }}</span>
        </div>
        <div class="history-list">
            @forelse ($history as $item)
                <div class="history-item" wire:key="history-{{ $item->id }}">
                    <span class="history-time">{{ $item->played_at?->format('H:i') }}</span>
                    <span class="history-player">{{ $item->queueEntry?->player_name ?? 'Pemain dihapus' }}</span>
                    <span class="history-match">Match ke-{{ $item->match_number }}</span>
                    <span class="history-note">{{ $item->note ?? '—' }}</span>
                </div>
            @empty
                <p class="session-meta">Belum ada match yang dicatat hari ini.</p>
            @endforelse
        </div>
    </section>
</div>
