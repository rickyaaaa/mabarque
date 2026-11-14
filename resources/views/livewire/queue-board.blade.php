@php
    $statusLabels = ['ready' => 'Siap', 'offline' => 'Offline', 'playing' => 'Sedang bermain', 'skipped' => 'Dilewati', 'done' => 'Selesai'];
    $providerLabels = ['manual' => 'Manual', 'saweria' => 'Saweria', 'sociabuzz' => 'SociaBuzz', 'trakteer' => 'Trakteer'];
    $initials = fn (string $name) => mb_strtoupper(collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('')) ?: '?';
    $avatarColors = ['#3b1945', '#d82a1c', '#1f6faf', '#684780', '#238b39', '#a91b16'];
    $serviceTypes = \App\Models\QueueEntry::SERVICE_TYPES;
    $maxMatches = \App\Services\QueueService::MAX_MATCHES;
    $donationModes = \App\Services\DonationQueueService::MODES;
@endphp

{{-- Refresh otomatis dijeda selama ada popup terbuka supaya isian form tidak tertimpa. --}}
<div @if (! $showAddPlayer && ! $editingId && ! $showImport && ! $showRules) wire:poll.5s="refreshQueue" @endif>
    {{-- ── Header sesi ─────────────────────────────────────────────── --}}
    <header class="session-header region-session-header">
        @if ($isLive)
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
                <div class="session-stat"><span>Win – Lose</span><strong>{{ $session->wins }}–{{ $session->losses }}</strong></div>
                <div class="session-stat"><span>Match selesai</span><strong>{{ $session->total_matches }}</strong></div>
                <div class="session-stat"><span>Donasi masuk</span><strong>Rp {{ number_format($session->total_donation, 0, ',', '.') }}</strong></div>
            </div>
            <button class="button button-outline" type="button"
                    wire:click="endSession"
                    wire:confirm="Akhiri sesi &quot;{{ $session->title }}&quot;? Pemain yang sisa game-nya belum habis otomatis ikut ke sesi berikutnya.">
                Akhiri sesi
            </button>
        @else
            <div>
                <h1>Antrean</h1>
                <p class="session-meta">Belum live <span class="meta-separator">•</span> Sisa game tersimpan dan tampil di halaman viewer. Tambah atau edit pemain kapan saja.</p>
            </div>
            <div class="session-stats" aria-label="Ringkasan antrean">
                <div class="session-stat"><span>Di antrean</span><strong>{{ $counts['all'] }}</strong></div>
            </div>
            <a class="button button-primary" href="/live-session">Mulai sesi live</a>
        @endif
    </header>

    @error('queue')
        <p class="form-error queue-error" role="alert">{{ $message }}</p>
    @enderror

    {{-- ── Party sekarang, next game & aksi cepat ──────────────────── --}}
    <section class="top-grid" aria-label="Kontrol sesi">
        <article class="current-player region-current-player" aria-labelledby="current-player-title">
            <div class="current-player-label" id="current-player-title">
                {{ ! $isLive ? 'Belum live' : ($party->isNotEmpty() ? 'Sedang main' : 'Belum ada game berjalan') }}
                <span class="party-count">{{ $party->count() }}/{{ $partySizeLimit }} pemain + streamer</span>
            </div>
            <div class="party-body">
                <ol class="party-list" aria-label="Pemain di party">
                    @foreach ($party as $member)
                        <li class="party-slot is-filled" wire:key="party-{{ $member->id }}">
                            <span class="avatar" style="background: {{ $avatarColors[$member->id % 6] }}" aria-hidden="true">{{ $initials($member->display_name) }}</span>
                            <span class="party-slot-text">
                                <strong>{{ $member->display_name }}</strong>
                                <small>
                                    @if ($member->mlbb_id && trim($member->player_name) !== '')ID {{ $member->mlbb_id }} · @endif
                                    sisa {{ $matches->remaining($member) }}
                                    @if ($cap > 0) · sesi {{ $played[$member->id] ?? 0 }}/{{ $cap }}@endif
                                </small>
                            </span>
                        </li>
                    @endforeach
                    @for ($slot = $party->count(); $slot < $partySizeLimit; $slot++)
                        <li class="party-slot is-empty"><span class="party-slot-text"><strong>{{ $isLive ? 'Slot kosong' : 'Party diisi saat live' }}</strong></span></li>
                    @endfor
                </ol>
                <div class="party-next">
                    <p class="party-next-title">Next game</p>
                    @forelse ($nextUp as $next)
                        <p @class(['party-next-name', 'is-picked' => $next->isPickedForNext()]) wire:key="next-{{ $next->id }}">
                            <span>{{ $loop->iteration }}. {{ $next->display_name }} <small>sisa {{ $matches->remaining($next) }}</small></span>
                            @if ($isLive && $next->isPickedForNext())
                                <button class="party-next-remove" type="button" wire:click="unpickNext({{ $next->id }})" title="Batalkan dari Next game" aria-label="Batalkan {{ $next->display_name }} dari Next game">×</button>
                            @else
                                <small class="party-next-auto">otomatis</small>
                            @endif
                        </p>
                    @empty
                        <p class="party-next-empty">Tidak ada yang menunggu. Tambah pemain atau tunggu donasi VIP.</p>
                    @endforelse
                    <p class="party-next-hint">{{ $isLive ? 'Pilih manual dengan tombol ⏭ di daftar.' : 'Perkiraan dari urutan antrean.' }}</p>
                </div>
            </div>
        </article>

        <article class="quick-actions region-quick-actions" aria-labelledby="quick-actions-title">
            <h2 id="quick-actions-title">Kontrol game</h2>
            <div class="quick-action-row">
                @if (! $isLive)
                    <a class="button button-small button-light" href="/live-session"><span aria-hidden="true">▶</span> Mulai sesi live</a>
                @elseif ($party->isEmpty())
                    <button class="button button-small button-light" type="button" wire:click="startGame" @disabled($nextUp->isEmpty())><span aria-hidden="true">▶</span> Mulai game</button>
                @else
                    <button class="button button-small button-win" type="button" wire:click="finishGame('win')"
                            title="Game menang — semua pemain di party berkurang 1 game"><span aria-hidden="true">🏆</span> Menang</button>
                    <button class="button button-small button-lose" type="button" wire:click="finishGame('lose')"
                            title="Game kalah — semua pemain di party berkurang 1 game"><span aria-hidden="true">✗</span> Kalah</button>
                    <button class="button button-small button-light" type="button" wire:click="finishGame"
                            title="Selesai tanpa hasil (mis. remake) — tetap mengurangi 1 game"><span aria-hidden="true">✓</span> Selesai</button>
                    @if ($party->count() < $partySizeLimit && $nextUp->isNotEmpty())
                        <button class="button button-small button-light" type="button" wire:click="startGame"><span aria-hidden="true">+</span> Isi slot kosong</button>
                    @endif
                @endif
                <button class="button button-small button-light" type="button" wire:click="openRules"><span aria-hidden="true">⚙</span> Aturan party</button>
            </div>
            <p class="party-rules-note">
                {{ $partySizeLimit }} pemain per game ·
                {{ $cap > 0 ? "maks {$cap} game per orang tiap sesi" : 'tanpa batas game per sesi' }}
                <br />Donasi: {{ ['smart' => 'otomatis bila pesan berisi ID/“mabar”', 'manual' => 'selalu konfirmasi manual', 'auto' => 'otomatis semua donasi'][$donationMode] ?? '' }}
            </p>
            <button class="button button-primary button-add" type="button" wire:click="openAddPlayer"><span class="add-icon" aria-hidden="true">+</span> Tambah pemain</button>
            <button class="button button-outline button-small button-import" type="button" wire:click="openImport">⇣ Tempel banyak pemain</button>
        </article>
    </section>

    {{-- ── Daftar antrean ──────────────────────────────────────────── --}}
    <section class="queue-panel" aria-labelledby="queue-title">
        <div class="queue-heading-row">
            <h2 class="queue-title region-queue-title" id="queue-title">Daftar antrean <span>({{ $counts['all'] }} pemain)</span></h2>
            <div class="filter-group" role="group" aria-label="Filter asal pemain" title="Otomatis = masuk dari donasi · Manual = diinput sendiri">
                @foreach (['all' => 'Semua', 'auto' => 'Otomatis', 'manual' => 'Manual', 'joki' => 'Mainin akun'] as $key => $label)
                    <button type="button" wire:click="setFilter('{{ $key }}')" @class(['filter-button', 'is-active' => $filter === $key]) aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">
                        {{ $label }} <span>({{ $counts[$key] }})</span>
                    </button>
                @endforeach
            </div>
        </div>

        @if ($party->isNotEmpty())
            <div class="current-turn-marker" role="status" aria-live="polite">
                <span class="current-turn-pulse" aria-hidden="true"></span>
                <span><strong>Sedang main</strong> {{ $party->pluck('display_name')->join(', ') }}</span>
            </div>
        @endif

        @php
            $visibleIds = $entries->pluck('id')->map(fn ($id) => (string) $id)->all();
            $selectedVisible = array_values(array_intersect($selected, $visibleIds));
            $allSelected = $visibleIds !== [] && count($selectedVisible) === count($visibleIds);
        @endphp
        @if ($selectedVisible !== [])
            <div class="bulk-bar" role="region" aria-label="Aksi untuk pemain yang dicentang">
                <strong>{{ count($selectedVisible) }} dipilih</strong>
                <button class="button button-small button-danger" type="button" wire:click="bulkRemove"
                        wire:confirm="Hapus {{ count($selectedVisible) }} pemain dari antrean? Sisa game mereka ikut hilang.">Hapus</button>
                <button class="button button-small button-outline" type="button" wire:click="clearSelection">Batal</button>
            </div>
        @endif

        <div class="queue-table-wrap">
            <table class="queue-table">
                <caption class="sr-only">Daftar pemain dalam antrean mabar VIP</caption>
                <thead class="region-queue-columns">
                    <tr>
                        <th scope="col" class="select-col"><input type="checkbox" class="row-check" wire:click="toggleSelectAll" @checked($allSelected) @disabled($visibleIds === []) aria-label="Centang semua pemain" /></th>
                        <th scope="col">#</th>
                        <th scope="col">Nama pemain</th>
                        <th scope="col">Jenis &amp; sumber</th>
                        <th scope="col">Sesi ini</th>
                        <th scope="col">Sisa</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody class="region-queue-rows">
                    @foreach ($entries as $entry)
                        <tr wire:key="row-{{ $entry->id }}" @class(['is-playing' => $entry->status === 'playing', 'is-offline' => $entry->status === 'offline', 'is-selected' => in_array((string) $entry->id, $selected, true)])>
                            <td class="select-col"><input type="checkbox" class="row-check" wire:model.live="selected" value="{{ $entry->id }}" aria-label="Centang {{ $entry->display_name }}" /></td>
                            <td>@if ($entry->status === 'playing')<span class="party-marker" title="Sedang main (posisi antrean #{{ $entry->position }})">▶</span>@else{{ $entry->position }}@endif</td>
                            <td><div class="player-cell"><span class="avatar" style="background: {{ $avatarColors[$entry->id % 6] }}">{{ $initials($entry->display_name) }}</span>@include('livewire.partials.player-identity', ['entry' => $entry])@include('livewire.partials.entry-status', ['entry' => $entry])</div></td>
                            <td><div class="pill-stack">@if ($entry->service_type === 'joki')<span class="service-pill joki">Mainin akun</span>@endif<span class="provider-pill {{ $entry->provider }}">{{ $providerLabels[$entry->provider] ?? $entry->provider }}</span></div></td>
                            <td>{{ $played[$entry->id] ?? 0 }}{{ $cap > 0 && $entry->service_type !== 'joki' ? '/'.$cap : '' }}</td>
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
            @if ($entries->isNotEmpty())
                <label class="bulk-select-all"><input type="checkbox" class="row-check" wire:click="toggleSelectAll" @checked($allSelected) /> Centang semua</label>
            @endif
            @foreach ($entries as $entry)
                <article wire:key="card-{{ $entry->id }}" @class(['player-card', 'is-playing' => $entry->status === 'playing', 'is-offline' => $entry->status === 'offline'])>
                    <div class="player-card-head">
                        <div class="player-card-name"><input type="checkbox" class="row-check" wire:model.live="selected" value="{{ $entry->id }}" aria-label="Centang {{ $entry->display_name }}" /><span class="player-card-position">#{{ $entry->position }}</span>@include('livewire.partials.player-identity', ['entry' => $entry])</div>
                        @include('livewire.partials.entry-status', ['entry' => $entry])
                    </div>
                    <div class="player-card-meta">
                        <div><span>Jenis &amp; sumber</span><strong class="pill-stack">@if ($entry->service_type === 'joki')<span class="service-pill joki">Mainin akun</span>@endif<span class="provider-pill {{ $entry->provider }}">{{ $providerLabels[$entry->provider] ?? $entry->provider }}</span></strong></div>
                        <div><span>Sesi ini · sisa</span><strong>{{ $played[$entry->id] ?? 0 }}{{ $cap > 0 && $entry->service_type !== 'joki' ? '/'.$cap : '' }} · @include('livewire.partials.quota', ['remaining' => $matches->remaining($entry)])</strong></div>
                    </div>
                    <div class="player-card-foot"><span class="player-card-note"></span>@include('livewire.partials.row-actions', ['entry' => $entry])</div>
                </article>
            @endforeach
        </div>
    </section>

    {{-- ── Modal tambah pemain ─────────────────────────────────────── --}}
    @if ($showAddPlayer)
        <dialog wire:ignore.self class="modal" aria-labelledby="add-player-title"
                x-data x-init="$el.showModal(); $nextTick(() => $el.querySelector('input')?.focus())"
                x-on:close="$wire.closeAddPlayer()">
            <form class="modal-card" wire:submit="addPlayer">
                <div class="modal-header">
                    <div><p class="eyebrow">Donasi di luar platform</p><h2 id="add-player-title">Tambah pemain</h2></div>
                    <button class="icon-button" type="button" aria-label="Tutup" wire:click="closeAddPlayer">×</button>
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
                        <label class="field-label" for="add-player-matches">Jatah game <span>(1–{{ $maxMatches }})</span></label>
                        <input class="field-input @error('matchesTotal') is-invalid @enderror" id="add-player-matches" type="number" min="1" max="{{ $maxMatches }}" wire:model="matchesTotal" />
                    </div>
                    <div>
                        <label class="field-label" for="add-player-type">Jenis</label>
                        <select class="field-input" id="add-player-type" wire:model="serviceType">
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
                    <div><p class="eyebrow">Ubah data pemain</p><h2 id="edit-player-title">Edit pemain</h2></div>
                    <button class="icon-button" type="button" aria-label="Tutup" wire:click="closeEdit">×</button>
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
                        <label class="field-label" for="edit-player-remaining">Sisa game <span>(0 = selesai)</span></label>
                        <input class="field-input @error('editRemaining') is-invalid @enderror" id="edit-player-remaining" type="number" min="0" max="{{ $maxMatches }}" wire:model="editRemaining" />
                    </div>
                    <div>
                        <label class="field-label" for="edit-player-type">Jenis</label>
                        <select class="field-input" id="edit-player-type" wire:model="editType">
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
                    <div><p class="eyebrow">Pindahan dari notepad</p><h2 id="import-title">Tempel banyak pemain</h2></div>
                    <button class="icon-button" type="button" aria-label="Tutup" wire:click="closeImport">×</button>
                </div>
                <p class="modal-help">Satu baris satu pemain, dipisah garis tegak <code>|</code>:<br /><code>nama | ID MLBB | sisa game | jenis | catatan</code><br />ID, jenis, dan catatan boleh kosong. Jenis: <code>vip</code> (Mabar VIP) atau <code>joki</code> (Mainin akun). Pemain masuk di akhir antrean sesuai urutan baris.</p>
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
                    <div><p class="eyebrow">Berlaku untuk semua sesi</p><h2 id="rules-title">Aturan party</h2></div>
                    <button class="icon-button" type="button" aria-label="Tutup" wire:click="closeRules">×</button>
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
                    <input type="checkbox" wire:model="censorNames" />
                    <span class="switch-track"></span>
                    <span><strong>Sensor nama &amp; ID</strong><small>Separuh akhir nama dan ID disamarkan di halaman viewer &amp; overlay (sama dengan pengaturan di halaman Overlay).</small></span>
                </label>
                <p class="modal-help">Jumlah game dihitung dari kelipatan paket (75rb = 50rb + 25rb). Donasi yang tidak otomatis masuk tetap muncul di halaman Donasi sebagai "Perlu cek".</p>
                <p class="modal-help">Pemain yang sudah mencapai batas tetap ada di daftar dengan sisa game-nya, tapi dilewati saat mengisi party sampai sesi live berikutnya. Kamu tetap bisa memasukkannya manual dengan tombol ▶. Pemain "Mainin akun" tidak masuk party dan tidak dibatasi.</p>
                <div class="modal-actions">
                    <button class="button button-outline" type="button" wire:click="closeRules">Batal</button>
                    <button class="button button-primary" type="submit" wire:loading.attr="disabled">Simpan aturan</button>
                </div>
            </form>
        </dialog>
    @endif
</div>

