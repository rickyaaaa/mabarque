@php($rupiah = fn (int $value) => 'Rp '.number_format($value, 0, ',', '.'))

<div>
    <header class="packages-header">
        <div>
            <p class="eyebrow">Harga fleksibel untuk komunitas</p>
            <h1>Paket VIP</h1>
            <p class="session-meta">Nominal donasi menentukan jumlah game: paket termahal yang muat dihitung dulu, lalu kelipatannya (mis. 75rb = 50rb + 25rb). Donasi di bawah harga termurah tidak dihitung. Paket <strong>Reward lain</strong> (mis. Follback IG) tidak menjadi game — donasinya masuk daftar reward.</p>
        </div>
        <div class="packages-header-actions">
            <a class="button button-outline" href="{{ $publicUrl }}" target="_blank" rel="noopener">@include('partials.icon', ['name' => 'link', 'size' => 16]) Lihat halaman publik</a>
            <button class="button button-primary" type="button" wire:click="create">@include('partials.icon', ['name' => 'plus', 'size' => 16]) Buat paket baru</button>
        </div>
    </header>

    <section class="package-summary">
        <span><strong>{{ $activePackages->count() }}</strong> paket aktif</span>
        <span>Total donasi hari ini <strong>{{ $rupiah($donationsToday) }}</strong></span>
        <span class="summary-note">{{ $packages->count() - $activePackages->count() }} disembunyikan</span>
    </section>

    @if ($packages->isEmpty())
        <div class="package-empty" role="status">
            <strong>Belum ada paket VIP</strong>
            <p>Buat paket pertama supaya donasi bisa otomatis masuk antrean sesuai nominalnya.</p>
            <button class="button button-primary" type="button" wire:click="create">Buat paket pertama</button>
        </div>
    @else
        <section class="package-grid" aria-label="Daftar paket VIP">
            @foreach ($packages as $package)
                <article wire:key="package-{{ $package->id }}" @class(['package-card', 'is-inactive' => ! $package->is_active, 'is-reward' => $package->isReward()])>
                    <span class="package-ribbon">{{ $package->is_active ? 'Tampil' : 'Disembunyikan' }}</span>
                    <h2>{{ $package->name }}</h2>
                    <div class="package-price">{{ $rupiah($package->price) }}</div>
                    @if ($package->isReward())
                        <div class="package-matches"><span class="package-kind">Reward</span> {{ $package->getsLabel() }}</div>
                        <ul class="package-features">
                            <li>Donasi {{ $rupiah($package->price) }} atau lebih → {{ $package->getsLabel() }} (tidak masuk antrean mabar)</li>
                            <li>{{ $package->reward_count }} donatur pernah dapat reward ini</li>
                        </ul>
                    @else
                        <div class="package-matches">{{ $package->match_count }} match per donasi</div>
                        <ul class="package-features">
                            <li>Donasi {{ $rupiah($package->price) }} (atau kelipatannya) → {{ $package->match_count }} game per {{ $rupiah($package->price) }}</li>
                            <li>{{ $package->queue_entries_count }} pemain pernah memakai paket ini</li>
                        </ul>
                    @endif
                    <div class="package-actions">
                        <label class="package-toggle">
                            <input type="checkbox" @checked($package->is_active) wire:click="toggle({{ $package->id }})" />
                            <span class="package-status-label">{{ $package->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </label>
                        <button type="button" wire:click="edit({{ $package->id }})">@include('partials.icon', ['name' => 'pencil', 'size' => 14]) Edit paket</button>
                        <button class="package-delete" type="button" wire:click="delete({{ $package->id }})"
                                wire:confirm="Hapus paket {{ $package->name }}? Paket hilang dari halaman penonton. Sisa game pemain yang sudah tercatat tidak berubah."
                                aria-label="Hapus paket {{ $package->name }}" title="Hapus paket">@include('partials.icon', ['name' => 'trash', 'size' => 14])</button>
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    <section class="donation-links-panel" aria-labelledby="donation-links-title">
        <div>
            <p class="eyebrow">Arah tombol paket</p>
            <h2 id="donation-links-title">Link donasi untuk viewer</h2>
            <p>Saat viewer mengklik paket di halaman antrean, mereka diarahkan ke halaman donasi ini. Isi yang kamu pakai saja.</p>
        </div>
        <form class="donation-links-form" wire:submit="saveLinks">
            @foreach (\App\Services\DonationLinkService::PLATFORMS as $platform => $info)
                <div class="donation-link-row">
                    <label class="field-label" for="link-{{ $platform }}">{{ $info['label'] }}</label>
                    <input class="field-input" id="link-{{ $platform }}" type="url" inputmode="url" wire:model="links.{{ $platform }}" placeholder="{{ $info['example'] }}" autocomplete="off" />
                    <label class="donation-link-primary"><input type="radio" name="primary-link" value="{{ $platform }}" wire:model="primaryLink" /> Tombol utama</label>
                </div>
            @endforeach
            @error('links') <p class="form-error" role="alert">{{ $message }}</p> @enderror
            <div class="modal-actions"><button class="button button-primary" type="submit" wire:loading.attr="disabled">Simpan link donasi</button></div>
        </form>
    </section>

    @if ($showForm)
        <dialog wire:ignore.self class="modal" aria-labelledby="package-modal-title" x-data x-init="$el.showModal(); $nextTick(() => $el.querySelector('input')?.focus())" x-on:close="$wire.closeForm()">
            <form class="modal-card" wire:submit="save">
                <div class="modal-header">
                    <div><p class="eyebrow">Paket donasi</p><h2 id="package-modal-title">{{ $editingId ? 'Edit paket VIP' : 'Buat paket VIP' }}</h2></div>
                    <button class="icon-button" type="button" wire:click="closeForm" aria-label="Tutup">×</button>
                </div>
                <div class="layout-toggle package-kind-toggle" role="radiogroup" aria-label="Jenis paket">
                    <label @class(['layout-option', 'is-active' => $kind === 'mabar'])><input type="radio" value="mabar" wire:model.live="kind" /> Mabar VIP <small>harga → jumlah game</small></label>
                    <label @class(['layout-option', 'is-active' => $kind === 'reward'])><input type="radio" value="reward" wire:model.live="kind" /> Reward lain <small>mis. Follback IG, shoutout</small></label>
                </div>
                <label class="field-label" for="package-name">Nama paket</label>
                <input class="field-input @error('name') is-invalid @enderror" id="package-name" wire:model="name" placeholder="Contoh: VIP 3 Match" maxlength="80" />
                @error('name') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <div class="field-grid">
                    <div>
                        <label class="field-label" for="package-price">Harga donasi (Rp)</label>
                        <input class="field-input @error('price') is-invalid @enderror" id="package-price" type="number" min="1000" step="1000" wire:model="price" />
                    </div>
                    @if ($kind === 'reward')
                        <div>
                            <label class="field-label" for="package-reward">Yang didapat donatur</label>
                            <input class="field-input @error('rewardLabel') is-invalid @enderror" id="package-reward" wire:model="rewardLabel" placeholder="Contoh: Follback IG" maxlength="80" />
                        </div>
                    @else
                        <div>
                            <label class="field-label" for="package-matches">Jatah match</label>
                            <input class="field-input @error('matchCount') is-invalid @enderror" id="package-matches" type="number" min="1" max="99" wire:model="matchCount" />
                        </div>
                    @endif
                </div>
                @if ($kind === 'reward')
                    <p class="modal-help">Donasi senilai harga ini atau lebih masuk daftar <strong>Reward donasi</strong> di papan antrean (tidak jadi game). Kelebihan nominal tidak dihitung.</p>
                @endif
                @error('price') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                @error('matchCount') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                @error('rewardLabel') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <div class="modal-actions">
                    <button class="button button-outline" type="button" wire:click="closeForm">Batal</button>
                    <button class="button button-primary" type="submit" wire:loading.attr="disabled">Simpan paket</button>
                </div>
            </form>
        </dialog>
    @endif
</div>
