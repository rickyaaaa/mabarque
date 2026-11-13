@php
    $statusLabels = ['pending' => 'Menunggu webhook', 'active' => 'Aktif', 'paused' => 'Dijeda', 'error' => 'Error'];
@endphp

<article class="provider-panel" aria-labelledby="provider-title">
    <div class="panel-heading">
        <div><p class="eyebrow">Koneksi otomatis</p><h2 id="provider-title">Sumber donasi</h2></div>
    </div>

    <div class="provider-list">
        @foreach ($providers as $key => $provider)
            @php($connection = $provider['connection'])
            <div class="provider-card" wire:key="provider-{{ $key }}">
                <div class="provider-brand provider-brand-{{ $key }}">{{ $provider['short'] }}</div>
                <div>
                    <strong>{{ $provider['label'] }}{{ $connection?->label ? ' · '.$connection->label : '' }}</strong>
                    <span>
                        @if (! $connection)
                            Belum terhubung
                        @elseif ($connection->last_received_at)
                            Webhook terakhir {{ $connection->last_received_at->diffForHumans() }}
                        @else
                            Belum pernah menerima webhook
                        @endif
                        @unless ($provider['verified']) · <em>format belum terkonfirmasi</em>@endunless
                    </span>
                </div>
                @if ($connection)
                    <span @class(['connection-badge', 'is-paused' => $connection->status !== 'active'])><i></i>{{ $statusLabels[$connection->status] ?? $connection->status }}</span>
                @endif
                <div class="provider-card-actions">
                    <button class="filter-button" type="button" wire:click="open('{{ $key }}')">{{ $connection ? 'Ubah' : 'Hubungkan' }}</button>
                    @if ($connection)
                        <button class="filter-button" type="button" wire:click="togglePause('{{ $key }}')">{{ $connection->status === 'paused' ? 'Lanjutkan' : 'Jeda' }}</button>
                    @endif
                </div>
                @if ($provider['endpoint'])
                    <div class="provider-endpoint" x-data>
                        <code>{{ $provider['endpoint'] }}</code>
                        <button class="icon-button" type="button" aria-label="Salin URL webhook {{ $provider['label'] }}"
                                x-on:click="navigator.clipboard.writeText(@js($provider['endpoint'])).then(() => $dispatch('notify', { message: 'URL webhook {{ $provider['label'] }} disalin.' }))">⧉</button>
                    </div>
                @endif
                @if ($connection?->last_error)
                    <p class="provider-error" role="status">⚠ {{ $connection->last_error }}</p>
                @endif
                @if ($connection?->last_payload)
                    <div class="provider-sample">
                        <button class="text-button" type="button" wire:click="inspect('{{ $key }}')" aria-expanded="{{ $inspecting === $key ? 'true' : 'false' }}">
                            {{ $inspecting === $key ? 'Sembunyikan' : 'Lihat' }} contoh webhook terakhir
                        </button>
                        @if ($inspecting === $key)
                            <pre>{{ json_encode($connection->last_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            <small>Kredensial disamarkan (***). Bagikan contoh ini ke developer bila format provider berubah.</small>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    <p class="provider-note">Setiap provider memverifikasi webhook dengan caranya sendiri (Saweria: signature dari stream key; Trakteer & SociaBuzz: webhook token). Webhook dengan kredensial salah ditolak.</p>

    @if ($editing)
        <dialog class="modal connection-modal" aria-labelledby="connection-title" x-data x-init="$el.showModal()" x-on:close="$wire.close()">
            <form class="modal-card" wire:submit="save">
                <div class="modal-header">
                    <div><p class="eyebrow">Koneksi provider</p><h2 id="connection-title">{{ $providers[$editing]['label'] }}</h2></div>
                    <button class="icon-button" type="button" wire:click="close" aria-label="Tutup">×</button>
                </div>
                <p class="connection-help">{{ $providers[$editing]['steps'] }}</p>
                <label class="field-label" for="connection-label">Label <span>(opsional)</span></label>
                <input class="field-input" id="connection-label" wire:model="label" maxlength="80" placeholder="Contoh: Akun utama" />
                <label class="field-label" for="connection-secret">{{ $providers[$editing]['credential'] }} @if ($editingConnection)<span>(kosongkan untuk tetap memakai yang lama)</span>@endif</label>
                <input class="field-input @error('signingSecret') is-invalid @enderror" id="connection-secret" type="password" wire:model="signingSecret" autocomplete="off" placeholder="Tempel {{ strtolower($providers[$editing]['credential']) }} dari dashboard {{ $providers[$editing]['label'] }}" />
                @error('signingSecret') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <p class="connection-help">{{ $providers[$editing]['credential'] }} disimpan terenkripsi dan tidak ditampilkan lagi.</p>
                <div class="modal-actions">
                    <button class="button button-outline" type="button" wire:click="close">Batal</button>
                    <button class="button button-primary" type="submit" wire:loading.attr="disabled">Simpan koneksi</button>
                </div>
            </form>
        </dialog>
    @endif
</article>
