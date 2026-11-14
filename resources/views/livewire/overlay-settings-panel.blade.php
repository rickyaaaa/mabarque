@php
    $accent = preg_match('/^#[0-9A-Fa-f]{6}$/', $accentColor) ? $accentColor : '#D82A1C';
    $providerLabels = ['manual' => 'Manual', 'saweria' => 'Saweria', 'sociabuzz' => 'SociaBuzz', 'trakteer' => 'Trakteer'];
    $previewName = fn ($entry) => $censorNames
        ? (trim((string) $entry->player_name) !== '' ? \App\Services\PublicQueueService::censor(trim($entry->player_name)) : ($entry->mlbb_id ? 'ID '.\App\Services\PublicQueueService::censor($entry->mlbb_id) : 'Tanpa nama'))
        : $entry->display_name;
@endphp

<div>
    <header class="overlay-header">
        <div>
            <p class="eyebrow">Siap ditempel ke OBS</p>
            <h1>Tampilan overlay</h1>
            <p class="session-meta">Tambahkan <strong>Browser Source</strong> di OBS, tempel link di bawah, lalu atur ukuran 480 × 600.</p>
        </div>
        <button class="button button-primary" type="button" x-data
                x-on:click="navigator.clipboard.writeText(@js($overlayUrl)).then(() => $dispatch('notify', { message: 'Link overlay disalin. Tempel sebagai Browser Source di OBS.' }))">Salin link overlay</button>
    </header>

    <section class="overlay-layout">
        <form class="overlay-controls" wire:submit="save">
            <div class="panel-heading">
                <h2>Pengaturan</h2>
                <span class="save-status" role="status">{{ $dirty ? 'Ada perubahan belum disimpan' : 'Tersimpan' }}</span>
            </div>

            <label class="control-label" for="accent-color">Warna aksen</label>
            <div class="color-control">
                <input id="accent-color" type="color" wire:model.live="accentColor" value="{{ $accent }}" />
                <input class="field-input @error('accentColor') is-invalid @enderror" wire:model.live.debounce.400ms="accentColor" maxlength="7" aria-label="Kode warna aksen" />
            </div>
            @error('accentColor') <p class="form-error" role="alert">{{ $message }}</p> @enderror

            <label class="control-label" for="font-scale">Ukuran huruf <output>{{ $fontScale }}%</output></label>
            <input id="font-scale" type="range" min="80" max="130" step="5" wire:model.live="fontScale" />

            <label class="control-label" for="max-players">Jumlah pemain ditampilkan</label>
            <select id="max-players" class="field-input" wire:model.live="maxPlayers">
                @foreach (\App\Livewire\OverlaySettingsPanel::PLAYER_OPTIONS as $option)
                    <option value="{{ $option }}">{{ $option }} pemain</option>
                @endforeach
            </select>

            <label class="switch-control">
                <input type="checkbox" wire:model.live="showNowPlaying" />
                <span class="switch-track"></span>
                <span><strong>Tampilkan now playing</strong><small>Penonton tahu siapa yang sedang bermain.</small></span>
            </label>
            <label class="switch-control">
                <input type="checkbox" wire:model.live="transparentMode" />
                <span class="switch-track"></span>
                <span><strong>Mode transparan</strong><small>Hilangkan latar belakang untuk browser source OBS.</small></span>
            </label>
            <label class="switch-control">
                <input type="checkbox" wire:model.live="censorNames" />
                <span class="switch-track"></span>
                <span><strong>Sensor nama &amp; ID</strong><small>Separuh akhir nama dan ID MLBB disamarkan (WangSu*******) di halaman antrean viewer dan overlay. Mencegah orang mengaku-ngaku sisa game milik orang lain dari screenshot.</small></span>
            </label>
            <label class="switch-control">
                <input type="checkbox" wire:model.live="showScore" />
                <span class="switch-track"></span>
                <span><strong>Tampilkan skor Win / Lose</strong><small>Skor sesi live, bertambah saat kamu klik Menang/Kalah di papan antrean.</small></span>
            </label>
            <label class="switch-control">
                <input type="checkbox" wire:model.live="showPackages" />
                <span class="switch-track"></span>
                <span><strong>Tampilkan harga paket VIP</strong><small>Paket aktif tampil di bawah overlay, mis. 25K · 2 game.</small></span>
            </label>

            <div class="score-control">
                <div>
                    <strong>Skor sesi ini</strong>
                    <small>{{ $session ? 'Koreksi bila salah klik.' : 'Tersedia saat sesi live berjalan.' }}</small>
                </div>
                <div class="score-adjust" aria-label="Koreksi menang">
                    <button type="button" wire:click="adjustScore('win', -1)" @disabled(! $session) aria-label="Kurangi menang">−</button>
                    <span class="score-pill win">W {{ $session?->wins ?? 0 }}</span>
                    <button type="button" wire:click="adjustScore('win', 1)" @disabled(! $session) aria-label="Tambah menang">+</button>
                </div>
                <div class="score-adjust" aria-label="Koreksi kalah">
                    <button type="button" wire:click="adjustScore('loss', -1)" @disabled(! $session) aria-label="Kurangi kalah">−</button>
                    <span class="score-pill loss">L {{ $session?->losses ?? 0 }}</span>
                    <button type="button" wire:click="adjustScore('loss', 1)" @disabled(! $session) aria-label="Tambah kalah">+</button>
                </div>
                <button class="overlay-link" type="button" wire:click="resetScore" @disabled(! $session) wire:confirm="Reset skor sesi ini ke 0–0?">Reset</button>
            </div>

            <div class="control-actions">
                <button class="button button-outline" type="button" wire:click="resetToSaved" @disabled(! $dirty)>Batalkan</button>
                <button class="button button-primary" type="submit" @disabled(! $dirty) wire:loading.attr="disabled">Simpan pengaturan</button>
            </div>
        </form>

        <section class="overlay-preview-panel" aria-labelledby="preview-title">
            <div class="panel-heading">
                <h2 id="preview-title">Pratinjau langsung</h2>
                <span class="preview-live"><span @class(['status-dot', 'is-live' => $session])></span>{{ $session ? 'LIVE' : 'BELUM LIVE' }}</span>
            </div>
            <div @class(['overlay-stage', 'is-solid' => ! $transparentMode])>
                <div class="overlay-preview" style="--preview-accent: {{ $accent }}; --preview-scale: {{ $fontScale / 100 }}">
                    <div class="preview-head">
                        <div class="preview-brand">{{ \Illuminate\Support\Str::upper($channelName) }} <span>VIP</span></div>
                        @if ($showScore)
                            <div class="preview-score"><span class="win">W {{ $session?->wins ?? 0 }}</span><span class="loss">L {{ $session?->losses ?? 0 }}</span></div>
                        @endif
                    </div>
                    @if ($showNowPlaying)
                        <div class="preview-now">
                            <span>NOW PLAYING</span>
                            <strong>{{ $party->isNotEmpty() ? $party->map($previewName)->join(' · ') : 'Belum ada' }}</strong>
                            <small>{{ $party->isEmpty() ? 'Mulai game dari papan antrean' : ($party->count() === 1 ? ($providerLabels[$party[0]->provider] ?? $party[0]->provider).' · '.max(0, $party[0]->matches_total - $party[0]->matches_played).' game tersisa' : $party->count().' pemain di party') }}</small>
                        </div>
                    @endif
                    <div class="preview-queue">
                        @forelse ($upcoming as $index => $entry)
                            <div class="preview-entry" wire:key="preview-entry-{{ $entry->id }}"><span>{{ $index + 1 }}. {{ $previewName($entry) }}</span><small>{{ max(0, $entry->matches_total - $entry->matches_played) }} game</small></div>
                        @empty
                            <div class="preview-entry"><span>Antrean kosong</span><small>—</small></div>
                        @endforelse
                    </div>
                    @if ($showPackages && $packages->isNotEmpty())
                        <div class="preview-packages">
                            @foreach ($packages as $package)
                                <span><strong>{{ \App\Http\Controllers\OverlayController::compactPrice((int) $package->price) }}</strong> {{ $package->match_count }} game</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
            <div class="overlay-url">
                <span>Link browser source</span>
                <code>{{ $overlayUrl }}</code>
                <button class="icon-button" type="button" aria-label="Salin link overlay" x-data
                        x-on:click="navigator.clipboard.writeText(@js($overlayUrl)).then(() => $dispatch('notify', { message: 'Link overlay disalin.' }))">⧉</button>
            </div>
            <div class="overlay-url">
                <a class="overlay-link" href="{{ $overlayUrl }}" target="_blank" rel="noopener">Buka overlay ↗</a>
                <button class="overlay-link" type="button" wire:click="regenerateLink"
                        wire:confirm="Buat link overlay baru? Link lama langsung tidak berlaku dan browser source di OBS harus diganti.">Buat link baru</button>
            </div>
        </section>
    </section>
</div>
