@php
    $statusLabels = ['ready' => 'Siap', 'offline' => 'Offline', 'playing' => 'Sedang bermain', 'skipped' => 'Dilewati', 'done' => 'Selesai'];
    $providerLabels = ['manual' => 'Manual', 'saweria' => 'Saweria', 'sociabuzz' => 'SociaBuzz', 'trakteer' => 'Trakteer'];
    $initials = fn (string $name) => mb_strtoupper(collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('')) ?: '?';
    $avatarColors = ['#3b1945', '#d82a1c', '#1f6faf', '#684780', '#238b39', '#a91b16'];
    $isPlaying = $currentEntry?->status === 'playing';
@endphp

<div wire:poll.5s="refreshQueue">
    {{-- ── Header sesi ─────────────────────────────────────────────── --}}
    <header class="session-header region-session-header">
        <div>
            <h1>{{ $session->title }}</h1>
            <p class="session-meta"
               x-data="{ start: {{ $session->started_at?->getTimestamp() ?? 'null' }}, now: Date.now() / 1000 }"
               x-init="setInterval(() => now = Date.now() / 1000, 1000)">
                {{ $session->started_at?->translatedFormat('l, j M Y') }}
                <span class="meta-separator">•</span>
                Live <span x-text="start ? new Date(Math.max(0, now - start) * 1000).toISOString().substring(11, 19) : '—'"></span>
            </p>
        </div>
        <div class="session-stats" aria-label="Ringkasan sesi">
            <div class="session-stat"><span>Di antrean</span><strong>{{ $counts['all'] }}</strong></div>
            <div class="session-stat"><span>Match selesai</span><strong>{{ $session->total_matches }}</strong></div>
            <div class="session-stat"><span>Donasi masuk</span><strong>Rp {{ number_format($session->total_donation, 0, ',', '.') }}</strong></div>
        </div>
        <button class="button button-outline" type="button"
                wire:click="endSession"
                wire:confirm="Akhiri sesi &quot;{{ $session->title }}&quot;? Donasi baru tidak akan masuk ke antrean ini lagi.">
            Akhiri sesi
        </button>
    </header>

    @error('queue')
        <p class="form-error queue-error" role="alert">{{ $message }}</p>
    @enderror

    {{-- ── Giliran sekarang & aksi cepat ───────────────────────────── --}}
    <section class="top-grid" aria-label="Kontrol sesi">
        <article class="current-player region-current-player" aria-labelledby="current-player-title">
            <div class="current-player-label" id="current-player-title">{{ $isPlaying ? 'Pemain saat ini' : 'Giliran berikutnya' }}</div>
            <div class="current-player-body">
                @if ($currentEntry)
                    <div>
                        <div class="badge badge-ready"><span class="badge-dot"></span>{{ $isPlaying ? 'Sedang bermain' : 'Siap — belum mulai' }}</div>
                        <h2>{{ $currentEntry->player_name }}</h2>
                        <p class="current-player-meta">
                            Sumber: <strong>{{ $providerLabels[$currentEntry->provider] ?? $currentEntry->provider }}</strong>
                            <span>•</span> Match: <strong>{{ $currentEntry->matches_played }}/{{ $currentEntry->matches_total }}</strong>
                            <span>•</span> Sisa: <strong>{{ $matches->remaining($currentEntry) }}</strong>
                        </p>
                    </div>
                    <div class="current-player-avatar avatar avatar-large" aria-hidden="true">{{ $initials($currentEntry->player_name) }}</div>
                @else
                    <div>
                        <h2>Belum ada pemain</h2>
                        <p class="current-player-meta">Tambahkan pemain manual atau tunggu donasi VIP masuk.</p>
                    </div>
                @endif
            </div>
        </article>

        <article class="quick-actions region-quick-actions" aria-labelledby="quick-actions-title">
            <h2 id="quick-actions-title">Kontrol antrean cepat</h2>
            <div class="quick-action-row">
                @if ($currentEntry && ! $isPlaying)
                    <button class="button button-small button-light" type="button" wire:click="markPlaying({{ $currentEntry->id }})"><span aria-hidden="true">▶</span> Mulai main</button>
                @else
                    <button class="button button-small button-light" type="button" @disabled(! $currentEntry) wire:click="finishMatch({{ $currentEntry?->id ?? 0 }})"><span aria-hidden="true">✓</span> Selesai match</button>
                @endif
                <button class="button button-small button-light" type="button" @disabled(! $currentEntry) wire:click="skip({{ $currentEntry?->id ?? 0 }})"><span aria-hidden="true">▶|</span> Lewati</button>
                <button class="button button-small button-light" type="button" @disabled(! $currentEntry || $isPlaying) wire:click="toggleStatus({{ $currentEntry?->id ?? 0 }})"><span aria-hidden="true">↺</span> Offline</button>
                <button class="button button-small button-light" type="button" @disabled(! $currentEntry)
                        wire:click="remove({{ $currentEntry?->id ?? 0 }})"
                        wire:confirm="Hapus {{ $currentEntry?->player_name }} dari antrean?"><span aria-hidden="true">⌫</span> Hapus</button>
            </div>
            <button class="button button-primary button-add" type="button" wire:click="openAddPlayer"><span class="add-icon" aria-hidden="true">+</span> Tambah pemain</button>
        </article>
    </section>

    {{-- ── Daftar antrean ──────────────────────────────────────────── --}}
    <section class="queue-panel" aria-labelledby="queue-title">
        <div class="queue-heading-row">
            <h2 class="queue-title region-queue-title" id="queue-title">Daftar antrean <span>({{ $counts['all'] }} pemain)</span></h2>
            <div class="filter-group" role="group" aria-label="Filter status pemain">
                @foreach (['all' => 'Semua', 'ready' => 'Siap', 'offline' => 'Offline', 'skipped' => 'Dilewati'] as $key => $label)
                    <button type="button" wire:click="setFilter('{{ $key }}')" @class(['filter-button', 'is-active' => $filter === $key]) aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">
                        {{ $label }} <span>({{ $counts[$key] }})</span>
                    </button>
                @endforeach
            </div>
        </div>

        @if ($isPlaying)
            <div class="current-turn-marker" role="status" aria-live="polite">
                <span class="current-turn-pulse" aria-hidden="true"></span>
                <span><strong>Giliran sekarang</strong> {{ $currentEntry->player_name }} sedang bermain</span>
            </div>
        @endif

        <div class="queue-table-wrap">
            <table class="queue-table">
                <caption class="sr-only">Daftar pemain dalam antrean mabar VIP</caption>
                <thead class="region-queue-columns">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Nama pemain</th>
                        <th scope="col">Sumber donasi</th>
                        <th scope="col">Status</th>
                        <th scope="col">Match</th>
                        <th scope="col">Sisa</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody class="region-queue-rows">
                    @foreach ($entries as $entry)
                        <tr wire:key="row-{{ $entry->id }}" @class(['is-playing' => $entry->status === 'playing', 'is-offline' => $entry->status === 'offline'])>
                            <td>{{ $entry->position }}</td>
                            <td><div class="player-cell"><span class="avatar" style="background: {{ $avatarColors[$entry->id % 6] }}">{{ $initials($entry->player_name) }}</span><span class="player-name">{{ $entry->player_name }}</span></div></td>
                            <td><span class="provider-pill {{ $entry->provider }}">{{ $providerLabels[$entry->provider] ?? $entry->provider }}</span></td>
                            <td><span class="status-pill {{ $entry->status }}"><span class="status-dot"></span>{{ $statusLabels[$entry->status] ?? $entry->status }}</span></td>
                            <td>{{ $entry->matches_played }}/{{ $entry->matches_total }}</td>
                            <td>@include('livewire.partials.quota', ['remaining' => $matches->remaining($entry)])</td>
                            <td>@include('livewire.partials.row-actions', ['entry' => $entry])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($entries->isEmpty())
                <div class="empty-state">
                    <span class="empty-icon" aria-hidden="true">⌁</span>
                    <strong>{{ $counts['all'] === 0 ? 'Antrean masih kosong' : 'Tidak ada pemain di filter ini' }}</strong>
                    <p>{{ $counts['all'] === 0 ? 'Donasi VIP yang cocok dengan paket akan masuk otomatis, atau tambahkan pemain manual.' : 'Pilih filter lain untuk melihat pemain.' }}</p>
                </div>
            @endif
        </div>

        <div class="player-card-list" aria-label="Daftar kartu pemain">
            @foreach ($entries as $entry)
                <article wire:key="card-{{ $entry->id }}" @class(['player-card', 'is-playing' => $entry->status === 'playing', 'is-offline' => $entry->status === 'offline'])>
                    <div class="player-card-head">
                        <div class="player-card-name"><span class="player-card-position">#{{ $entry->position }}</span><strong>{{ $entry->player_name }}</strong></div>
                        <span class="status-pill {{ $entry->status }}"><span class="status-dot"></span>{{ $statusLabels[$entry->status] ?? $entry->status }}</span>
                    </div>
                    <div class="player-card-meta">
                        <div><span>Sumber donasi</span><strong><span class="provider-pill {{ $entry->provider }}">{{ $providerLabels[$entry->provider] ?? $entry->provider }}</span></strong></div>
                        <div><span>Match</span><strong>{{ $entry->matches_played }}/{{ $entry->matches_total }} · @include('livewire.partials.quota', ['remaining' => $matches->remaining($entry)])</strong></div>
                    </div>
                    <div class="player-card-foot"><span class="player-card-note"></span>@include('livewire.partials.row-actions', ['entry' => $entry])</div>
                </article>
            @endforeach
        </div>
    </section>

    {{-- ── Modal tambah pemain ─────────────────────────────────────── --}}
    @if ($showAddPlayer)
        <dialog class="modal" aria-labelledby="add-player-title"
                x-data x-init="$el.showModal(); $nextTick(() => $el.querySelector('input')?.focus())"
                x-on:close="$wire.closeAddPlayer()">
            <form class="modal-card" wire:submit="addPlayer">
                <div class="modal-header">
                    <div><p class="eyebrow">Donasi di luar platform</p><h2 id="add-player-title">Tambah pemain</h2></div>
                    <button class="icon-button" type="button" aria-label="Tutup" wire:click="closeAddPlayer">×</button>
                </div>
                <label class="field-label" for="add-player-name">Nama pemain</label>
                <input class="field-input @error('playerName') is-invalid @enderror" id="add-player-name" wire:model="playerName" placeholder="Contoh: Kiki ML" maxlength="120" autocomplete="off" />
                @error('playerName') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <label class="field-label" for="add-player-matches">Jatah mabar <span>(1–99 match)</span></label>
                <input class="field-input @error('matchesTotal') is-invalid @enderror" id="add-player-matches" type="number" min="1" max="99" wire:model="matchesTotal" />
                @error('matchesTotal') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <div class="modal-actions">
                    <button class="button button-outline" type="button" wire:click="closeAddPlayer">Batal</button>
                    <button class="button button-primary" type="submit" wire:loading.attr="disabled">Masukkan ke antrean</button>
                </div>
            </form>
        </dialog>
    @endif
</div>
