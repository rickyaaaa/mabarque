@php
    $accent = preg_match('/^#[0-9A-Fa-f]{6}$/', $accentColor) ? $accentColor : '#D82A1C';
    $providerLabels = ['manual' => 'Manual', 'saweria' => 'Saweria', 'sociabuzz' => 'SociaBuzz', 'trakteer' => 'Trakteer'];
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
                    <div class="preview-brand">{{ \Illuminate\Support\Str::upper($channelName) }} <span>VIP</span></div>
                    @if ($showNowPlaying)
                        <div class="preview-now">
                            <span>NOW PLAYING</span>
                            <strong>{{ $current?->player_name ?? 'Belum ada' }}</strong>
                            <small>{{ $current ? ($providerLabels[$current->provider] ?? $current->provider).' · '.max(0, $current->matches_total - $current->matches_played).' match tersisa' : 'Mulai main dari papan antrean' }}</small>
                        </div>
                    @endif
                    <div class="preview-queue">
                        @forelse ($upcoming as $index => $entry)
                            <div class="preview-entry" wire:key="preview-entry-{{ $entry->id }}"><span>{{ $index + 1 }}. {{ $entry->player_name }}</span><small>{{ max(0, $entry->matches_total - $entry->matches_played) }} match</small></div>
                        @empty
                            <div class="preview-entry"><span>Antrean kosong</span><small>—</small></div>
                        @endforelse
                    </div>
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
