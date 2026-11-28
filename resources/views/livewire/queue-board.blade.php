@php
    $providerLabels = ['manual' => 'Manual', 'saweria' => 'Saweria', 'sociabuzz' => 'SociaBuzz', 'trakteer' => 'Trakteer'];
    $donationStatus = ['received' => 'Baru', 'review' => 'Perlu cek', 'queued' => 'Masuk antrean', 'reward' => 'Reward', 'ignored' => 'Diabaikan', 'refunded' => 'Refund'];
    $initials = fn (string $name) => mb_strtoupper(collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('')) ?: '?';
    $serviceTypes = \App\Models\QueueEntry::SERVICE_TYPES;
    $maxMatches = \App\Services\QueueService::MAX_MATCHES;
    $donationModes = \App\Services\DonationQueueService::MODES;
    $donationModeShort = ['smart' => 'Otomatis bila pesan berisi ID / “mabar”', 'manual' => 'Selalu konfirmasi manual', 'auto' => 'Otomatis semua donasi'];
@endphp

{{-- Refresh otomatis dijeda selama ada popup terbuka supaya isian form tidak tertimpa. --}}
<div class="qb" @if (! $showAddPlayer && ! $editingId && ! $showImport && ! $showRules) wire:poll.5s.keep-alive="refreshQueue" @endif>
    {{-- ── Kepala halaman ──────────────────────────────────────────── --}}
    <header class="qb-head">
        <div class="qb-head-title">
            @if ($isLive)
                <span class="qb-live is-live"><i aria-hidden="true"></i>Live</span>
                <h1>{{ $session->title }}</h1>
                <p class="qb-meta"
                   x-data="{ start: {{ $session->started_at?->getTimestamp() ?? 'null' }}, now: Date.now() / 1000 }"
                   x-init="setInterval(() => now = Date.now() / 1000, 1000)">
                    {{ $session->started_at?->translatedFormat('l, j M Y') }}
                    <span aria-hidden="true">·</span>
                    Durasi <span class="qb-num" x-text="start ? new Date(Math.max(0, now - start) * 1000).toISOString().substring(11, 19) : '—'"></span>
                </p>
            @else
                <span class="qb-live"><i aria-hidden="true"></i>Belum live</span>
                <h1>Antrean</h1>
                <p class="qb-meta">Sisa game tersimpan dan tampil di halaman viewer. Tambah atau edit pemain kapan saja.</p>
            @endif
        </div>
        <div class="qb-head-actions">
            <button class="qb-btn" type="button" wire:click="openImport">@include('partials.icon', ['name' => 'clipboard']) Tempel banyak pemain</button>
            <button class="qb-btn is-accent" type="button" wire:click="openAddPlayer">@include('partials.icon', ['name' => 'plus']) Tambah pemain</button>
            @if ($isLive)
                <button class="qb-btn is-quiet" type="button" wire:click="endSession"
                        wire:confirm="Akhiri sesi &quot;{{ $session->title }}&quot;? Pemain yang sisa game-nya belum habis otomatis ikut ke sesi berikutnya.">
                    @include('partials.icon', ['name' => 'stop']) Akhiri sesi
                </button>
            @else
                <a class="qb-btn is-dark" href="/live-session">@include('partials.icon', ['name' => 'radio']) Mulai sesi live</a>
            @endif
        </div>
    </header>

    {{-- ── Angka utama ─────────────────────────────────────────────── --}}
    <section class="qb-stats" aria-label="Ringkasan">
        <article class="qb-stat is-dark"><span>Di antrean</span><strong>{{ $counts['all'] }}</strong><small>{{ $readyCount }} siap main</small></article>
        @if ($isLive)
            <article class="qb-stat"><span>Win – Lose</span><strong>{{ $session->wins }} – {{ $session->losses }}</strong><small>skor sesi ini</small></article>
            <article class="qb-stat"><span>Match selesai</span><strong>{{ $session->total_matches }}</strong><small>sejak live dimulai</small></article>
            <article class="qb-stat is-accent"><span>Masuk antrean</span><strong>Rp {{ number_format($donationStats['queued_amount'], 0, ',', '.') }}</strong><small>{{ \App\Services\DonationStatsService::caption($donationStats) }}</small></article>
        @else
            <article class="qb-stat"><span>Sisa game</span><strong>{{ $remainingTotal }}</strong><small>total Mabar VIP tersimpan</small></article>
            <article class="qb-stat"><span>Mainin akun</span><strong>{{ $counts['joki'] }}</strong><small>pesanan aktif</small></article>
            <article class="qb-stat is-accent"><span>Party</span><strong>{{ $partySizeLimit }}+1</strong><small>pemain + streamer per game</small></article>
        @endif
    </section>

    @error('queue')
        <p class="qb-alert" role="alert">{{ $message }}</p>
    @enderror

    <div class="qb-grid">
        <div class="qb-main">
            {{-- ── Sedang main (party) ─────────────────────────────── --}}
            <section @class(['qb-panel', 'qb-turn', 'is-active' => $party->isNotEmpty()]) aria-labelledby="qb-turn-title">
                <div class="qb-panel-head">
                    <h2 id="qb-turn-title">{{ ! $isLive ? 'Belum live' : ($party->isNotEmpty() ? 'Sedang main' : 'Belum ada game berjalan') }}</h2>
                    <span class="qb-count">{{ $party->count() }}/{{ $partySizeLimit }} pemain + streamer</span>
                </div>

                <ol class="qb-party" aria-label="Pemain di party">
                    @foreach ($party as $member)
                        <li @class(['qb-slot', 'is-filled', 'is-mvp' => $mvpId === $member->id]) wire:key="party-{{ $member->id }}">
                            <span class="qb-avatar" aria-hidden="true">{{ $initials($member->display_name) }}</span>
                            <span class="qb-slot-text">
                                <strong>{{ $member->display_name }}</strong>
                                <small>
                                    @if ($member->mlbb_id && trim($member->player_name) !== '')ID {{ $member->mlbb_id }} · @endif
                                    sisa {{ $matches->remaining($member) }}
                                    @if ($cap > 0) · sesi {{ $played[$member->id] ?? 0 }}/{{ $cap }}@endif
                                </small>
                            </span>
                            @if ($mvpEnabled && $isLive)
                                <button type="button" @class(['qb-mvp', 'is-active' => $mvpId === $member->id]) wire:click="toggleMvp({{ $member->id }})"
                                        title="{{ $mvpId === $member->id ? 'Batalkan MVP' : 'Tandai MVP — main lagi, sisa tidak berkurang' }}"
                                        aria-pressed="{{ $mvpId === $member->id ? 'true' : 'false' }}" aria-label="MVP: {{ $member->display_name }}">@include('partials.icon', ['name' => 'star', 'size' => 14]) MVP</button>
                            @endif
                        </li>
                    @endforeach
                    @for ($slot = $party->count(); $slot < $partySizeLimit; $slot++)
                        <li class="qb-slot is-empty"><span class="qb-slot-text"><strong>{{ $isLive ? 'Slot kosong' : 'Party diisi saat live' }}</strong></span></li>
                    @endfor
                </ol>

                <div class="qb-turn-actions">
                    @if (! $isLive)
                        <a class="qb-btn is-dark" href="/live-session">@include('partials.icon', ['name' => 'radio']) Mulai sesi live</a>
                    @elseif ($party->isEmpty())
                        <button class="qb-btn is-accent is-big" type="button" wire:click="startGame" @disabled($nextUp->isEmpty())>@include('partials.icon', ['name' => 'play']) Mulai game</button>
                    @else
                        <button class="qb-btn is-accent is-big" type="button" wire:click="finishGame" title="Semua pemain di party berkurang 1 game">@include('partials.icon', ['name' => 'check']) Selesai game</button>
                        @if ($party->count() < $partySizeLimit && $nextUp->isNotEmpty())
                            <button class="qb-btn" type="button" wire:click="startGame">@include('partials.icon', ['name' => 'plus']) Isi slot kosong</button>
                        @endif
                        <button class="qb-btn is-quiet" type="button" wire:click="benchAll"
                                wire:confirm="Keluarkan semua pemain dari party? Sisa game mereka tidak berubah.">@include('partials.icon', ['name' => 'eject']) Keluarkan semua</button>
                    @endif
                </div>

                {{-- Skor sesi di kotak yang sama dengan party: catat Menang/Kalah tanpa pindah panel. --}}
                @if ($isLive)
                    <div class="qb-score qb-turn-score" role="group" aria-labelledby="qb-score-title">
                        <span class="qb-turn-score-label" id="qb-score-title">Skor sesi <small>hanya skor, sisa game tidak berkurang</small></span>
                        <div class="qb-score-grid">
                            <div class="qb-score-cell is-win">
                                <button class="qb-score-add" type="button" wire:click="addScore('win')" title="Tambah menang (tidak mengurangi sisa game)">@include('partials.icon', ['name' => 'trophy']) Menang <strong>{{ $session->wins }}</strong></button>
                                <button class="qb-icon-btn" type="button" wire:click="undoScore('win')" @disabled($session->wins < 1) aria-label="Koreksi: kurangi menang">@include('partials.icon', ['name' => 'minus', 'size' => 14])</button>
                            </div>
                            <div class="qb-score-cell is-lose">
                                <button class="qb-score-add" type="button" wire:click="addScore('lose')" title="Tambah kalah (tidak mengurangi sisa game)">@include('partials.icon', ['name' => 'x']) Kalah <strong>{{ $session->losses }}</strong></button>
                                <button class="qb-icon-btn" type="button" wire:click="undoScore('lose')" @disabled($session->losses < 1) aria-label="Koreksi: kurangi kalah">@include('partials.icon', ['name' => 'minus', 'size' => 14])</button>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="qb-next">
                    <h3>Next game</h3>
                    <ol class="qb-next-list">
                        @forelse ($nextUp as $next)
                            <li @class(['qb-next-item', 'is-picked' => $next->isPickedForNext()]) wire:key="next-{{ $next->id }}">
                                <span class="qb-next-rank">{{ $loop->iteration }}</span>
                                <span class="qb-next-name">{{ $next->display_name }} <small>sisa {{ $matches->remaining($next) }}</small></span>
                                @if ($isLive && $next->isPickedForNext())
                                    <button class="qb-icon-btn" type="button" wire:click="unpickNext({{ $next->id }})" title="Batalkan dari Next game" aria-label="Batalkan {{ $next->display_name }} dari Next game">@include('partials.icon', ['name' => 'x', 'size' => 14])</button>
                                @else
                                    <small class="qb-next-auto">otomatis</small>
                                @endif
                            </li>
                        @empty
                            <li class="qb-next-empty">Tidak ada yang menunggu. Tambah pemain atau tunggu donasi VIP.</li>
                        @endforelse
                    </ol>
                    <p class="qb-hint">{{ $isLive ? 'Pilih manual dengan tombol “Next game” di daftar antrean.' : 'Perkiraan dari urutan antrean.' }}</p>
                </div>
            </section>

            {{-- ── Daftar antrean ─────────────────────────────────── --}}
            <section class="qb-panel qb-queue" aria-labelledby="queue-title">
                <div class="qb-panel-head qb-queue-head">
                    <h2 id="queue-title">Daftar antrean <span class="qb-count">{{ $counts['all'] }} pemain</span></h2>
                    <div class="qb-filters" role="group" aria-label="Filter pemain" title="Mabar VIP / Mainin akun = jenis layanan · Otomatis = masuk dari donasi · Manual = diinput sendiri">
                        @foreach (['all' => 'Semua', 'vip' => 'Mabar VIP', 'joki' => 'Mainin akun', 'auto' => 'Otomatis', 'manual' => 'Manual'] as $key => $label)
                            <button type="button" wire:click="setFilter('{{ $key }}')" @class(['qb-filter', 'is-active' => $filter === $key]) aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">
                                {{ $label }} <span>{{ $counts[$key] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="qb-queue-tools">
                    <label class="qb-search">
                        @include('partials.icon', ['name' => 'search', 'size' => 16])
                        <span class="sr-only">Cari pemain</span>
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau ID MLBB" maxlength="60" autocomplete="off" />
                        @if ($search !== '')
                            <button type="button" wire:click="clearSearch" aria-label="Hapus pencarian">@include('partials.icon', ['name' => 'x', 'size' => 14])</button>
                        @endif
                    </label>
                    <div class="qb-sort">
                        <label for="queue-sort">Urutkan</label>
                        <select id="queue-sort" wire:model.live="sort">
                            @foreach ($sorts as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    @if ($filteredCount > 0)
                        <span class="qb-range">{{ ($queuePage - 1) * $perPage + 1 }}–{{ min($queuePage * $perPage, $filteredCount) }} dari {{ $filteredCount }}{{ $search !== '' ? ' hasil' : ' pemain' }}</span>
                    @endif
                    @if ($sort !== 'queue')
                        {{-- Urutan ini hanya tampilan: giliran main (Next game, Mulai game) tetap menurut urutan antrean. --}}
                        <p class="qb-sort-note" role="status">@include('partials.icon', ['name' => 'alert', 'size' => 13]) Urutan main tetap mengikuti antrean.</p>
                    @endif
                </div>

                @php
                    $visibleIds = $entries->pluck('id')->map(fn ($id) => (string) $id)->all();
                    $selectedVisible = array_values(array_intersect($selected, $visibleIds));
                    $allSelected = $visibleIds !== [] && count($selectedVisible) === count($visibleIds);
                    $selectAllState = $allSelected ? 'checked' : 'unchecked';
                @endphp
                @if ($selectedVisible !== [])
                    <div class="qb-bulk" role="region" aria-label="Aksi untuk pemain yang dicentang">
                        <strong>{{ count($selectedVisible) }} dipilih</strong>
                        <button class="qb-btn is-danger" type="button" wire:click="bulkRemove"
                                wire:confirm="Hapus {{ count($selectedVisible) }} pemain dari antrean? Sisa game mereka ikut hilang.">@include('partials.icon', ['name' => 'trash']) Hapus</button>
                        <button class="qb-btn is-quiet" type="button" wire:click="clearSelection">Batal</button>
                    </div>
                @endif

                <div class="qb-table-wrap">
                    <table class="qb-table">
                        <caption class="sr-only">Daftar pemain dalam antrean mabar VIP</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="qb-col-check"><input type="checkbox" class="qb-check" wire:key="select-all-table-{{ $selectAllState }}" wire:click="toggleSelectAll" @checked($allSelected) @disabled($visibleIds === []) aria-label="Centang semua pemain" /></th>
                                <th scope="col" class="qb-col-pos">Inisial</th>
                                <th scope="col">Pemain</th>
                                <th scope="col">Layanan</th>
                                <th scope="col" class="qb-col-num">Sesi ini</th>
                                <th scope="col" class="qb-col-num">Sisa</th>
                                <th scope="col" class="qb-col-actions"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr wire:key="row-{{ $entry->id }}" @class(['is-playing' => $entry->status === 'playing', 'is-offline' => $entry->status === 'offline', 'is-selected' => in_array((string) $entry->id, $selected, true)])>
                                    <td class="qb-col-check"><input type="checkbox" class="qb-check" wire:model.live="selected" value="{{ $entry->id }}" aria-label="Centang {{ $entry->display_name }}" /></td>
                                    <td class="qb-col-pos"><span class="qb-pos {{ $entry->status === 'playing' ? 'is-playing' : '' }}" @if ($entry->status === 'playing') title="Sedang main" @endif>{{ $initials($entry->display_name) }}</span></td>
                                    <td><div class="qb-player">@include('livewire.partials.player-identity', ['entry' => $entry])@include('livewire.partials.entry-status', ['entry' => $entry])</div></td>
                                    <td>@include('livewire.partials.service-badge', ['entry' => $entry])</td>
                                    <td class="qb-col-num">{{ $entry->service_type === 'joki' ? '—' : ($played[$entry->id] ?? 0).($cap > 0 ? '/'.$cap : '') }}</td>
                                    <td class="qb-col-num">@if ($entry->service_type === 'joki')<span class="quota-chip">—</span>@else @include('livewire.partials.quota', ['remaining' => $matches->remaining($entry)]) @endif</td>
                                    <td class="qb-col-actions">@include('livewire.partials.row-actions', ['entry' => $entry])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($entries->isEmpty())
                        <div class="qb-empty">
                            @if ($counts['all'] === 0)
                                <strong>Antrean masih kosong</strong>
                                <p>Donasi VIP yang cocok dengan paket akan masuk otomatis, atau tambahkan pemain manual.</p>
                            @elseif ($search !== '')
                                <strong>Tidak ada pemain yang cocok dengan “{{ $search }}”</strong>
                                <p>Cek ejaan nama atau ID, atau pilih filter Semua.</p>
                            @else
                                <strong>Tidak ada pemain di filter ini</strong>
                                <p>Pilih filter lain untuk melihat pemain.</p>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="qb-cards" aria-label="Daftar kartu pemain">
                    @if ($entries->isNotEmpty())
                        <label class="qb-select-all"><input type="checkbox" class="qb-check" wire:key="select-all-cards-{{ $selectAllState }}" wire:click="toggleSelectAll" @checked($allSelected) /> Centang semua</label>
                    @endif
                    @foreach ($entries as $entry)
                        <article wire:key="card-{{ $entry->id }}" @class(['qb-card', 'is-playing' => $entry->status === 'playing', 'is-offline' => $entry->status === 'offline'])>
                            <div class="qb-card-head">
                                <input type="checkbox" class="qb-check" wire:model.live="selected" value="{{ $entry->id }}" aria-label="Centang {{ $entry->display_name }}" />
                                <span class="qb-pos {{ $entry->status === 'playing' ? 'is-playing' : '' }}">{{ $initials($entry->display_name) }}</span>
                                @include('livewire.partials.player-identity', ['entry' => $entry])
                                @include('livewire.partials.entry-status', ['entry' => $entry])
                            </div>
                            <div class="qb-card-meta">
                                @include('livewire.partials.service-badge', ['entry' => $entry])
                                @if ($entry->service_type !== 'joki')
                                    <span>Sesi {{ $played[$entry->id] ?? 0 }}{{ $cap > 0 ? '/'.$cap : '' }}</span>
                                    <span>Sisa @include('livewire.partials.quota', ['remaining' => $matches->remaining($entry)])</span>
                                @endif
                            </div>
                            <div class="qb-card-foot">@include('livewire.partials.row-actions', ['entry' => $entry])</div>
                        </article>
                    @endforeach
                </div>

                @include('partials.pager', ['page' => $queuePage, 'lastPage' => $lastPage, 'action' => 'goToPage', 'label' => 'Halaman daftar antrean'])
            </section>
        </div>

        <aside class="qb-side" aria-label="Panel samping">
            {{-- ── Donasi masuk ───────────────────────────────────── --}}
            <section class="qb-panel qb-donations" aria-labelledby="qb-don-title">
                <div class="qb-panel-head">
                    <h2 id="qb-don-title">Donasi terbaru</h2>
                    <a class="qb-link" href="/donations">Semua @include('partials.icon', ['name' => 'arrow-right', 'size' => 14])</a>
                </div>
                <ul class="qb-feed">
                    @forelse ($recentDonations as $donation)
                        <li class="qb-feed-item" wire:key="don-{{ $donation->id }}">
                            <div class="qb-feed-top">
                                <strong>{{ $donation->donor_name }}</strong>
                                <span class="qb-num">{{ $donation->amountLabel() }}</span>
                            </div>
                            <div class="qb-feed-meta">
                                <span class="qb-tag is-{{ $donation->status }}">{{ $donationStatus[$donation->status] ?? $donation->status }}</span>
                                <span>{{ $providerLabels[$donation->provider] ?? $donation->provider }} · {{ $donation->received_at?->diffForHumans(short: true) }}</span>
                            </div>
                            @if ($donation->message)
                                <p class="qb-feed-msg">“{{ \Illuminate\Support\Str::limit($donation->message, 70) }}”</p>
                            @endif
                        </li>
                    @empty
                        <li class="qb-feed-empty">Belum ada donasi. Donasi dari Saweria, SociaBuzz, atau Trakteer muncul di sini.</li>
                    @endforelse
                </ul>
            </section>

            {{-- ── Reward donasi yang belum diberikan (mis. Follback IG) ── --}}
            @if ($pendingRewards->isNotEmpty())
                <section class="qb-panel qb-rewards" aria-labelledby="qb-reward-title">
                    <div class="qb-panel-head">
                        <h2 id="qb-reward-title">Reward donasi</h2>
                        <span class="qb-count">{{ $pendingRewards->count() }} belum</span>
                    </div>
                    <ul class="qb-reward-list">
                        @foreach ($pendingRewards as $reward)
                            <li wire:key="reward-{{ $reward->id }}">
                                <div>
                                    <strong>{{ $reward->donor_name }}</strong>
                                    <span class="qb-tag is-reward">{{ $reward->vipPackage?->getsLabel() ?? 'Reward' }}</span>
                                    @if ($reward->message)<small>“{{ \Illuminate\Support\Str::limit($reward->message, 80) }}”</small>@endif
                                    <small>{{ $reward->amountLabel() }} · {{ $reward->received_at?->diffForHumans(short: true) }}</small>
                                </div>
                                <button class="qb-icon-btn" type="button" wire:click="finishReward({{ $reward->id }})" title="Tandai reward sudah diberikan" aria-label="Reward {{ $reward->donor_name }} selesai">@include('partials.icon', ['name' => 'check', 'size' => 14])</button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- ── Aksi cepat ─────────────────────────────────────── --}}
            <section class="qb-panel qb-quick" aria-labelledby="qb-quick-title">
                <div class="qb-panel-head"><h2 id="qb-quick-title">Aksi cepat</h2></div>
                <div class="qb-quick-list" x-data>
                    <button class="qb-quick-item" type="button" wire:click="openAddPlayer">@include('partials.icon', ['name' => 'plus']) Catat pemain manual</button>
                    <button class="qb-quick-item" type="button" wire:click="openImport">@include('partials.icon', ['name' => 'clipboard']) Tempel daftar dari notepad</button>
                    <button class="qb-quick-item" type="button"
                            x-on:click="navigator.clipboard.writeText(@js($publicUrl)).then(() => $dispatch('notify', { message: 'Link halaman viewer disalin.' }))">@include('partials.icon', ['name' => 'link']) Salin link halaman viewer</button>
                    <a class="qb-quick-item" href="/overlay-settings">@include('partials.icon', ['name' => 'monitor']) Atur overlay OBS</a>
                </div>
            </section>

            {{-- ── Aturan antrean ─────────────────────────────────── --}}
            <section class="qb-panel qb-rules" aria-labelledby="qb-rules-title">
                <div class="qb-panel-head">
                    <h2 id="qb-rules-title">Aturan antrean</h2>
                    <button class="qb-link" type="button" wire:click="openRules">@include('partials.icon', ['name' => 'sliders', 'size' => 14]) Ubah</button>
                </div>
                <dl class="qb-rule-list">
                    <div><dt>Party</dt><dd>{{ $partySizeLimit }} pemain per game</dd></div>
                    <div><dt>Batas sesi</dt><dd>{{ $cap > 0 ? "maks {$cap} game per orang tiap sesi" : 'tanpa batas game per sesi' }}</dd></div>
                    <div><dt>Donasi</dt><dd>{{ $donationModeShort[$donationMode] ?? '' }}</dd></div>
                    <div><dt>MVP</dt><dd>{{ $mvpEnabled ? 'MVP = main lagi' : 'Mati' }}</dd></div>
                </dl>
            </section>
        </aside>
    </div>

    {{-- ── Modal tambah pemain ─────────────────────────────────────── --}}
    @if ($showAddPlayer)
        <dialog wire:ignore.self class="modal" aria-labelledby="add-player-title"
                x-data x-init="$el.showModal(); $nextTick(() => $el.querySelector('input')?.focus())"
                x-on:close="$wire.closeAddPlayer()">
            <form class="modal-card" wire:submit="addPlayer">
                <div class="modal-header">
                    <h2 id="add-player-title">Tambah pemain</h2>
                    <button class="icon-button" type="button" aria-label="Tutup" wire:click="closeAddPlayer">@include('partials.icon', ['name' => 'x', 'size' => 18])</button>
                </div>
                @error('playerForm') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <label class="field-label" for="add-player-name">Nama pemain <span>(boleh kosong bila ID diisi)</span></label>
                <input class="field-input @error('playerName') is-invalid @enderror" id="add-player-name" wire:model="playerName" placeholder="Contoh: Kiki ML" maxlength="120" autocomplete="off" />
                @error('playerName') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <label class="field-label" for="add-player-id">ID MLBB <span>(boleh kosong, bisa diisi nanti)</span></label>
                <input class="field-input @error('mlbbId') is-invalid @enderror" id="add-player-id" wire:model="mlbbId" placeholder="Contoh: 1185010926 (2345)" maxlength="40" inputmode="numeric" autocomplete="off" />
                @error('mlbbId') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <div class="field-row">
                    <div>
                        @if ($serviceType === 'joki')
                            <label class="field-label">Jatah game</label>
                            <p class="field-hint">Mainin akun tidak dihitung per game — cukup tandai selesai.</p>
                        @else
                            <label class="field-label" for="add-player-matches">Jatah game <span>(1–{{ $maxMatches }})</span></label>
                            <input class="field-input @error('matchesTotal') is-invalid @enderror" id="add-player-matches" type="number" min="1" max="{{ $maxMatches }}" wire:model="matchesTotal" />
                        @endif
                    </div>
                    <div>
                        <label class="field-label" for="add-player-type">Jenis</label>
                        <select class="field-input" id="add-player-type" wire:model.live="serviceType">
                            @foreach ($serviceTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                </div>
                @error('matchesTotal') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <label class="field-label" for="add-player-note">Catatan <span>(hanya terlihat oleh kamu)</span></label>
                <textarea class="field-input" id="add-player-note" wire:model="note" rows="2" maxlength="500" placeholder="Contoh: ID dikirim lewat komentar"></textarea>
                <div class="modal-actions">
                    <button class="button button-outline" type="button" wire:click="closeAddPlayer">Batal</button>
                    <button class="button button-primary" type="submit" wire:loading.attr="disabled">Masukkan ke antrean</button>
                </div>
            </form>
        </dialog>
    @endif

    {{-- ── Modal edit pemain ───────────────────────────────────────── --}}
    @if ($editingId)
        <dialog wire:ignore.self class="modal" aria-labelledby="edit-player-title" wire:key="edit-modal-{{ $editingId }}"
                x-data x-init="$el.showModal(); $nextTick(() => $el.querySelector('input')?.focus())"
                x-on:close="$wire.closeEdit()">
            <form class="modal-card" wire:submit="savePlayer">
                <div class="modal-header">
                    <h2 id="edit-player-title">Edit pemain</h2>
                    <button class="icon-button" type="button" aria-label="Tutup" wire:click="closeEdit">@include('partials.icon', ['name' => 'x', 'size' => 18])</button>
                </div>
                @error('editForm') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <label class="field-label" for="edit-player-name">Nama pemain</label>
                <input class="field-input @error('editName') is-invalid @enderror" id="edit-player-name" wire:model="editName" maxlength="120" autocomplete="off" />
                @error('editName') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <label class="field-label" for="edit-player-id">ID MLBB</label>
                <input class="field-input @error('editMlbbId') is-invalid @enderror" id="edit-player-id" wire:model="editMlbbId" placeholder="Contoh: 1185010926 (2345)" maxlength="40" inputmode="numeric" autocomplete="off" />
                @error('editMlbbId') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <div class="field-row">
                    <div>
                        @if ($editType === 'joki')
                            <label class="field-label">Sisa game</label>
                            <p class="field-hint">Mainin akun tidak dihitung per game.</p>
                        @else
                            <label class="field-label" for="edit-player-remaining">Sisa game <span>(0 = selesai)</span></label>
                            <input class="field-input @error('editRemaining') is-invalid @enderror" id="edit-player-remaining" type="number" min="0" max="{{ $maxMatches }}" wire:model="editRemaining" />
                        @endif
                    </div>
                    <div>
                        <label class="field-label" for="edit-player-type">Jenis</label>
                        <select class="field-input" id="edit-player-type" wire:model.live="editType">
                            @foreach ($serviceTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                </div>
                @error('editRemaining') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <label class="field-label" for="edit-player-note">Catatan <span>(hanya terlihat oleh kamu)</span></label>
                <textarea class="field-input" id="edit-player-note" wire:model="editNote" rows="2" maxlength="500"></textarea>
                <div class="modal-actions">
                    <button class="button button-outline" type="button" wire:click="closeEdit">Batal</button>
                    <button class="button button-primary" type="submit" wire:loading.attr="disabled">Simpan</button>
                </div>
            </form>
        </dialog>
    @endif

    {{-- ── Modal tempel banyak pemain ──────────────────────────────── --}}
    @if ($showImport)
        <dialog wire:ignore.self class="modal" aria-labelledby="import-title"
                x-data x-init="$el.showModal(); $nextTick(() => $el.querySelector('textarea')?.focus())"
                x-on:close="$wire.closeImport()">
            <form class="modal-card modal-card-wide" wire:submit="importPlayers">
                <div class="modal-header">
                    <h2 id="import-title">Tempel banyak pemain</h2>
                    <button class="icon-button" type="button" aria-label="Tutup" wire:click="closeImport">@include('partials.icon', ['name' => 'x', 'size' => 18])</button>
                </div>
                <p class="modal-help">Satu baris satu pemain, dipisah garis tegak <code>|</code>:<br /><code>nama | ID MLBB | sisa game | jenis | catatan</code><br />ID, jenis, dan catatan boleh kosong. Jenis: <code>vip</code> (Mabar VIP) atau <code>joki</code> (VIP Mainin akun — sisa game boleh kosong). Pemain masuk di akhir antrean sesuai urutan baris.</p>
                @error('importForm') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <label class="field-label" for="import-text">Daftar pemain</label>
                <textarea class="field-input import-textarea" id="import-text" wire:model="importText" rows="12" spellcheck="false"
                          placeholder="Vampire |  | 8 | vip |&#10;CELLSEA | 1185010926 | 2 | vip |&#10;BANG ROTI |  | 8 | joki | mulai Minggu"></textarea>
                <div class="modal-actions">
                    <button class="button button-outline" type="button" wire:click="closeImport">Batal</button>
                    <button class="button button-primary" type="submit" wire:loading.attr="disabled">Masukkan ke antrean</button>
                </div>
            </form>
        </dialog>
    @endif

    {{-- ── Modal aturan party ──────────────────────────────────────── --}}
    @if ($showRules)
        <dialog wire:ignore.self class="modal" aria-labelledby="rules-title"
                x-data x-init="$el.showModal()"
                x-on:close="$wire.closeRules()">
            <form class="modal-card" wire:submit="saveRules">
                <div class="modal-header">
                    <h2 id="rules-title">Aturan party</h2>
                    <button class="icon-button" type="button" aria-label="Tutup" wire:click="closeRules">@include('partials.icon', ['name' => 'x', 'size' => 18])</button>
                </div>
                <label class="field-label" for="rules-party-size">Pemain per game <span>(di luar streamer)</span></label>
                <select class="field-input" id="rules-party-size" wire:model="partySize">
                    @for ($n = 1; $n <= \App\Services\QueueService::MAX_PARTY_SIZE; $n++)
                        <option value="{{ $n }}">{{ $n }} pemain + streamer{{ $n === 4 ? ' (5-stack)' : '' }}</option>
                    @endfor
                </select>
                @error('partySize') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <label class="field-label" for="rules-session-cap">Batas game per orang tiap sesi live</label>
                <select class="field-input" id="rules-session-cap" wire:model="sessionCap">
                    <option value="0">Tanpa batas</option>
                    @for ($n = 1; $n <= \App\Livewire\QueueBoard::MAX_SESSION_CAP; $n++)
                        <option value="{{ $n }}">Maks {{ $n }} game</option>
                    @endfor
                </select>
                @error('sessionCap') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <label class="field-label" for="rules-donation-mode">Donasi masuk antrean</label>
                <select class="field-input" id="rules-donation-mode" wire:model="donationModeInput">
                    @foreach ($donationModes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}{{ $value === 'smart' ? ' (disarankan)' : '' }}</option>
                    @endforeach
                </select>
                @error('donationModeInput') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <label class="switch-control rules-switch">
                    <input type="checkbox" wire:model="mvpFreeGame" />
                    <span class="switch-track"></span>
                    <span><strong>MVP = main lagi</strong><small>Muncul tombol MVP di kartu party. Pemain yang MVP tidak berkurang sisa game-nya dan tidak dihitung ke batas game per sesi. Tercatat di rekap sesi.</small></span>
                </label>
                <label class="switch-control rules-switch">
                    <input type="checkbox" wire:model="censorNames" />
                    <span class="switch-track"></span>
                    <span><strong>Sensor nama &amp; ID</strong><small>Separuh akhir nama dan ID disamarkan di halaman viewer &amp; overlay (sama dengan pengaturan di halaman Overlay).</small></span>
                </label>
                <p class="modal-help">Jumlah game dihitung dari kelipatan paket (75rb = 50rb + 25rb). Donasi yang tidak otomatis masuk tetap muncul di halaman Donasi sebagai "Perlu cek".</p>
                <p class="modal-help">Pemain yang sudah mencapai batas tetap ada di daftar dengan sisa game-nya, tapi dilewati saat mengisi party sampai sesi live berikutnya. Kamu tetap bisa memasukkannya manual dengan tombol “Masukkan ke party”. Pemain "Mainin akun" tidak masuk party dan tidak dibatasi.</p>
                <div class="modal-actions">
                    <button class="button button-outline" type="button" wire:click="closeRules">Batal</button>
                    <button class="button button-primary" type="submit" wire:loading.attr="disabled">Simpan aturan</button>
                </div>
            </form>
        </dialog>
    @endif
</div>

