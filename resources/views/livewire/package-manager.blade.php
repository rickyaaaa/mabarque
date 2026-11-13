@php($rupiah = fn (int $value) => 'Rp '.number_format($value, 0, ',', '.'))

<div>
    <header class="packages-header">
        <div>
            <p class="eyebrow">Harga fleksibel untuk komunitas</p>
            <h1>Paket VIP</h1>
            <p class="session-meta">Nominal donasi menentukan paket: donasi yang sama persis dengan harga paket aktif otomatis masuk antrean.</p>
        </div>
        <button class="button button-primary" type="button" wire:click="create">+ Buat paket baru</button>
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
                <article wire:key="package-{{ $package->id }}" @class(['package-card', 'is-inactive' => ! $package->is_active])>
                    <span class="package-ribbon">{{ $package->is_active ? 'Tampil' : 'Disembunyikan' }}</span>
                    <h2>{{ $package->name }}</h2>
                    <div class="package-price">{{ $rupiah($package->price) }}</div>
                    <div class="package-matches">{{ $package->match_count }} match per donasi</div>
                    <ul class="package-features">
                        <li>Donasi tepat {{ $rupiah($package->price) }} → otomatis masuk antrean</li>
                        <li>{{ $package->queue_entries_count }} pemain pernah memakai paket ini</li>
                    </ul>
                    <div class="package-actions">
                        <label class="package-toggle">
                            <input type="checkbox" @checked($package->is_active) wire:click="toggle({{ $package->id }})" />
                            <span class="package-status-label">{{ $package->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </label>
                        <button type="button" wire:click="edit({{ $package->id }})">Edit paket</button>
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    <section class="package-preview">
        <div>
            <p class="eyebrow">Tampilan penonton</p>
            <h2>Pratinjau paket di halaman publik</h2>
            <p>Penonton melihat paket aktif ini sebelum memilih nominal donasi.</p>
        </div>
        <a class="button button-outline" href="{{ $publicUrl }}" target="_blank" rel="noopener">Lihat halaman publik ↗</a>
        <div class="public-package-preview">
            @forelse ($activePackages as $package)
                <div class="public-package-card" wire:key="preview-{{ $package->id }}"><strong>{{ $package->name }}</strong><span>{{ $rupiah($package->price) }} · {{ $package->match_count }} match</span></div>
            @empty
                <div class="public-package-card"><strong>Belum ada paket aktif</strong><span>Penonton belum melihat pilihan VIP.</span></div>
            @endforelse
        </div>
    </section>

    @if ($showForm)
        <dialog class="modal" aria-labelledby="package-modal-title" x-data x-init="$el.showModal(); $nextTick(() => $el.querySelector('input')?.focus())" x-on:close="$wire.closeForm()">
            <form class="modal-card" wire:submit="save">
                <div class="modal-header">
                    <div><p class="eyebrow">Paket donasi</p><h2 id="package-modal-title">{{ $editingId ? 'Edit paket VIP' : 'Buat paket VIP' }}</h2></div>
                    <button class="icon-button" type="button" wire:click="closeForm" aria-label="Tutup">×</button>
                </div>
                <label class="field-label" for="package-name">Nama paket</label>
                <input class="field-input @error('name') is-invalid @enderror" id="package-name" wire:model="name" placeholder="Contoh: VIP 3 Match" maxlength="80" />
                @error('name') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <div class="field-grid">
                    <div>
                        <label class="field-label" for="package-price">Harga donasi (Rp)</label>
                        <input class="field-input @error('price') is-invalid @enderror" id="package-price" type="number" min="1000" step="1000" wire:model="price" />
                    </div>
                    <div>
                        <label class="field-label" for="package-matches">Jatah match</label>
                        <input class="field-input @error('matchCount') is-invalid @enderror" id="package-matches" type="number" min="1" max="99" wire:model="matchCount" />
                    </div>
                </div>
                @error('price') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                @error('matchCount') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                <div class="modal-actions">
                    <button class="button button-outline" type="button" wire:click="closeForm">Batal</button>
                    <button class="button button-primary" type="submit" wire:loading.attr="disabled">Simpan paket</button>
                </div>
            </form>
        </dialog>
    @endif
</div>
