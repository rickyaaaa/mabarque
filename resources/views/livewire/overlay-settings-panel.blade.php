@php
    $accent = preg_match('/^#[0-9A-Fa-f]{6}$/', $accentColor) ? $accentColor : '#D82A1C';
    $providerLabels = ['manual' => 'Manual', 'saweria' => 'Saweria', 'sociabuzz' => 'SociaBuzz', 'trakteer' => 'Trakteer'];
@endphp

<div>
    <header class="overlay-header">
        <div>
            <p class="eyebrow">Siap ditempel ke OBS</p>
            <h1>Tampilan overlay</h1>
            <p class="session-meta">Tambahkan <strong>Browser Source</strong> di OBS, tempel link di bawah, lalu isi lebar &amp; tinggi sesuai ukuran yang tertulis di bawah pratinjau.</p>
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

            <label class="control-label">Bentuk &amp; ukuran</label>
            <div class="layout-presets" role="group" aria-label="Ukuran siap pakai">
                <button type="button" class="filter-button" wire:click="applyPreset('card')">Kartu 440</button>
                <button type="button" class="filter-button" wire:click="applyPreset('card-big')">Kartu besar 600</button>
                <button type="button" class="filter-button" wire:click="applyPreset('bar-bottom')">Bar bawah 1280×140</button>
                <button type="button" class="filter-button" wire:click="applyPreset('bar-wide')">Bar penuh 1920×160</button>
            </div>
            <div class="layout-toggle" role="radiogroup" aria-label="Bentuk overlay">
                <label @class(['layout-option', 'is-active' => $layout === 'vertical'])><input type="radio" value="vertical" wire:model.live="layout" /> ▯ Vertikal (kartu)</label>
                <label @class(['layout-option', 'is-active' => $layout === 'horizontal'])><input type="radio" value="horizontal" wire:model.live="layout" /> ▭ Horizontal (bar)</label>
            </div>
            <div class="size-grid">
                <div>
                    <label class="control-label" for="card-width">Lebar <output>{{ $cardWidth }} px</output></label>
                    <input id="card-width" class="field-input @error('cardWidth') is-invalid @enderror" type="number" min="240" max="1920" step="10" wire:model.live.debounce.500ms="cardWidth" />
                </div>
                <div>
                    <label class="control-label" for="card-height">Tinggi <output>{{ $autoHeight ? 'otomatis' : $cardHeight.' px' }}</output></label>
                    <input id="card-height" class="field-input @error('cardHeight') is-invalid @enderror" type="number" min="80" max="1080" step="10" wire:model.live.debounce.500ms="cardHeight" @disabled($autoHeight) />
                    <label class="auto-height"><input type="checkbox" wire:model.live="autoHeight" /> Otomatis mengikuti isi</label>
                </div>
            </div>
            @error('cardWidth') <p class="form-error" role="alert">{{ $message }}</p> @enderror
            @error('cardHeight') <p class="form-error" role="alert">{{ $message }}</p> @enderror

            <label class="control-label" for="font-scale">Ukuran huruf <output>{{ $fontScale }}%</output></label>
            <input id="font-scale" type="range" min="60" max="200" step="5" wire:model.live="fontScale" />

            <label class="control-label" for="max-players">Jumlah pemain ditampilkan <span class="control-hint">(1–{{ \App\Livewire\OverlaySettingsPanel::MAX_PLAYERS }}, di luar yang sedang main)</span></label>
            <input id="max-players" class="field-input @error('maxPlayers') is-invalid @enderror" type="number" min="{{ \App\Livewire\OverlaySettingsPanel::MIN_PLAYERS }}" max="{{ \App\Livewire\OverlaySettingsPanel::MAX_PLAYERS }}" step="1" wire:model.live.debounce.400ms="maxPlayers" />
            @error('maxPlayers') <p class="form-error" role="alert">{{ $message }}</p> @enderror

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
                <div class="overlay-frame-wrap" style="width: {{ (int) round($preview['frame_width'] * $preview['fit']) }}px; height: {{ (int) round($preview['frame_height'] * $preview['fit']) }}px">
                    <iframe wire:key="overlay-frame-{{ md5($preview['src']) }}" src="{{ $preview['src'] }}" title="Pratinjau overlay"
                            width="{{ $preview['frame_width'] }}" height="{{ $preview['frame_height'] }}" loading="lazy"
                            style="transform: scale({{ $preview['fit'] }})"></iframe>
                </div>
            </div>
            <p class="obs-size">
                Ukuran <strong>Browser Source</strong> di OBS:
                <strong>{{ $preview['obs_width'] }} × {{ $preview['obs_height'] ?? 'tinggi bebas (mis. 600)' }}</strong>.
                Samakan persis supaya tajam — jangan diperbesar/diperkecil di OBS, ubah ukurannya di sini.
                @if ($dirty) <em>Pratinjau sudah memakai perubahan yang belum disimpan.</em> @endif
            </p>
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
