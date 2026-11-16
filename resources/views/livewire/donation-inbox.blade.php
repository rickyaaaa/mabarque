@php
    $rupiah = fn (int $value) => 'Rp '.number_format($value, 0, ',', '.');
    $statusLabels = ['received' => 'Baru', 'review' => 'Perlu cek', 'queued' => 'Masuk antrean', 'ignored' => 'Diabaikan', 'refunded' => 'Refund'];
    $statusClass = ['received' => 'new', 'review' => 'review', 'queued' => 'queued', 'ignored' => 'ignored', 'refunded' => 'ignored'];
    $providerLabels = ['saweria' => 'Saweria', 'sociabuzz' => 'SociaBuzz', 'trakteer' => 'Trakteer'];
    $initials = fn (string $name) => mb_strtoupper(collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('')) ?: '?';
    $avatarColors = ['#3b1945', '#d82a1c', '#1f6faf', '#684780', '#238b39', '#a91b16'];
@endphp

<div @if (! $queueing) wire:poll.10s @endif>
    <header class="donation-header">
        <div>
            <p class="eyebrow">Donasi masuk otomatis</p>
            <h1>VIP &amp; donasi</h1>
            <p class="session-meta">Donasi yang nominalnya cocok dengan paket aktif langsung masuk antrean. Sisanya menunggu dicek di sini.</p>
        </div>
        <div class="donation-header-actions">
            <a class="button button-primary" href="/dashboard">Buka antrean</a>
        </div>
        <div class="sync-line" role="status" aria-live="polite">
            <span @class(['status-dot', 'is-live' => $activeProviders > 0])></span>
            <span>{{ $activeProviders > 0 ? 'Webhook aktif' : ($connectedProviders ? 'Menunggu webhook pertama' : 'Belum ada koneksi provider') }}</span>
            <span class="meta-separator">•</span>
            <span>Diperbarui otomatis tiap 10 detik</span>
        </div>
    </header>

    <section class="donation-summary" aria-label="Ringkasan donasi">
        <article class="donation-stat donation-stat-red"><span>Donasi hari ini</span><strong>{{ $todayCount }}</strong><small>transaksi masuk</small></article>
        <article class="donation-stat donation-stat-orange"><span>Perlu dicek</span><strong>{{ $needsCheck }}</strong><small>belum masuk antrean</small></article>
        <article class="donation-stat donation-stat-purple"><span>Nilai donasi</span><strong>{{ $rupiah($donationValue) }}</strong><small>{{ $session ? 'semua donasi sejak sesi dimulai' : 'semua donasi hari ini' }}</small></article>
        <article class="donation-stat donation-stat-cream"><span>Provider aktif</span><strong>{{ $activeProviders }}/3</strong><small>{{ $connectedProviders }} terhubung</small></article>
    </section>

    <section class="donation-toolbar" aria-label="Filter donasi">
        <div><p class="eyebrow">Kotak masuk</p><h2>Donasi terbaru</h2></div>
        <div class="donation-filters" role="group" aria-label="Filter status">
            @foreach (['all' => 'Semua', 'received' => 'Baru', 'review' => 'Perlu cek', 'queued' => 'Masuk antrean', 'ignored' => 'Diabaikan'] as $key => $label)
                <button type="button" wire:click="setStatus('{{ $key }}')" @class(['filter-button', 'is-active' => $status === $key]) aria-pressed="{{ $status === $key ? 'true' : 'false' }}">{{ $label }} <span>({{ $counts[$key] }})</span></button>
            @endforeach
        </div>
        <label class="provider-filter">Provider
            <select wire:model.live="provider" aria-label="Filter berdasarkan provider">
                <option value="all">Semua provider</option>
                @foreach ($providerLabels as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
        </label>
    </section>

    <section class="donation-table-wrap" aria-labelledby="donations-table-title">
        <h2 class="sr-only" id="donations-table-title">Daftar donasi terbaru</h2>
        <table class="donation-table">
            <caption class="sr-only">Donasi dari provider yang terhubung</caption>
            <thead><tr><th scope="col">Waktu</th><th scope="col">Donatur</th><th scope="col">Sumber</th><th scope="col">Nominal</th><th scope="col">Paket</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
            <tbody>
                @foreach ($donations as $donation)
                    <tr wire:key="donation-{{ $donation->id }}">
                        <td>{{ $donation->received_at?->format('H:i') }} WIB<br /><small>{{ $donation->received_at?->translatedFormat('j M') }}</small></td>
                        <td><div class="donor-cell"><span class="avatar" style="background: {{ $avatarColors[$donation->id % 6] }}">{{ $initials($donation->donor_name) }}</span><div><strong>{{ $donation->donor_name }}</strong><small>{{ $donation->queueEntry ? 'Posisi #'.$donation->queueEntry->position : ($donation->message ? '“'.\Illuminate\Support\Str::limit($donation->message, 40).'”' : '—') }}</small></div></div></td>
                        <td><span class="provider-pill {{ $donation->provider }}">{{ $providerLabels[$donation->provider] ?? $donation->provider }}</span></td>
                        <td class="donation-amount">{{ $donation->amountLabel() }}</td>
                        <td>{{ $donation->vipPackage?->name ?? '—' }}</td>
                        <td><span class="donation-status {{ $statusClass[$donation->status] ?? 'new' }}">{{ $statusLabels[$donation->status] ?? $donation->status }}</span></td>
                        <td><span class="donation-actions">@include('livewire.partials.donation-actions', ['donation' => $donation])</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="donation-card-list">
            @foreach ($donations as $donation)
                <article class="donation-card" wire:key="donation-card-{{ $donation->id }}">
                    <div class="donation-card-head"><div class="donor-cell"><span class="avatar" style="background: {{ $avatarColors[$donation->id % 6] }}">{{ $initials($donation->donor_name) }}</span><div><strong>{{ $donation->donor_name }}</strong><small>{{ $donation->received_at?->format('H:i') }} WIB</small></div></div><span class="donation-status {{ $statusClass[$donation->status] ?? 'new' }}">{{ $statusLabels[$donation->status] ?? $donation->status }}</span></div>
                    <div class="donation-card-meta"><span class="provider-pill {{ $donation->provider }}">{{ $providerLabels[$donation->provider] ?? $donation->provider }}</span><strong>{{ $donation->amountLabel() }}</strong><span>{{ $donation->vipPackage?->name ?? 'Tanpa paket' }}</span></div>
                    <div class="donation-card-foot">@include('livewire.partials.donation-actions', ['donation' => $donation])</div>
                </article>
            @endforeach
        </div>

        @if ($donations->isEmpty())
            <div class="donation-empty">
                <strong>{{ $counts['all'] === 0 ? 'Belum ada donasi' : 'Tidak ada donasi di filter ini' }}</strong>
                <p>{{ $counts['all'] === 0 ? 'Hubungkan provider di bawah, lalu donasi akan muncul di sini secara otomatis.' : 'Coba pilih status atau provider lain.' }}</p>
            </div>
        @endif
    </section>

    <section class="donation-lower-grid">
        <livewire:connection-manager />

        <article class="recent-panel" aria-labelledby="recent-title">
            <div class="panel-heading">
                <div><p class="eyebrow">Realtime</p><h2 id="recent-title">Notifikasi masuk</h2></div>
                <button class="filter-button" type="button" wire:click="markAllRead">Tandai dibaca</button>
            </div>
            <div class="activity-list" aria-live="polite">
                @forelse ($notifications as $notification)
                    @php
                        $context = $notification->context ?? [];
                        $icon = ['queued' => ['is-green', 'check'], 'review' => ['is-orange', 'alert'], 'failed' => ['is-red', 'x']][$notification->type] ?? ['is-plain', 'wallet'];
                        $label = ['queued' => 'masuk antrean otomatis', 'review' => 'perlu dicek', 'failed' => 'gagal diproses'][$notification->type] ?? 'mengirim donasi';
                    @endphp
                    <div @class(['activity-item', 'is-unread' => ! $notification->read_at]) wire:key="notif-{{ $notification->id }}">
                        <span class="activity-icon {{ $icon[0] }}" aria-hidden="true">@include('partials.icon', ['name' => $icon[1], 'size' => 15])</span>
                        <div><strong>{{ $context['donor_name'] ?? 'Donatur' }} {{ $label }}</strong><span>{{ $providerLabels[$context['provider'] ?? ''] ?? '' }} · {{ \App\Models\Donation::formatAmount((int) ($context['amount'] ?? 0), $context['currency'] ?? 'IDR', isset($context['original_amount']) ? (float) $context['original_amount'] : null) }}</span></div>
                        <span class="activity-time">{{ $notification->created_at?->format('H:i') }}</span>
                        <button class="notification-read" type="button" wire:click="markRead({{ $notification->id }})" @disabled($notification->read_at)
                                aria-label="{{ $notification->read_at ? 'Sudah dibaca' : 'Tandai notifikasi '.($context['donor_name'] ?? '').' sudah dibaca' }}">@if ($notification->read_at)@include('partials.icon', ['name' => 'check', 'size' => 13])@else<i class="unread-dot"></i>@endif</button>
                    </div>
                @empty
                    <p class="session-meta">Belum ada notifikasi. Donasi baru akan muncul di sini.</p>
                @endforelse
            </div>
            <p class="notification-footnote"><span class="notification-pulse"></span><span>{{ $unread }} notifikasi baru</span></p>
        </article>
    </section>

    @if ($queueing)
        <dialog wire:ignore.self class="modal" aria-labelledby="queue-donation-title" x-data x-init="$el.showModal()" x-on:close="$wire.cancelQueue()">
            <form class="modal-card" wire:submit="confirmQueue">
                <div class="modal-header">
                    <div><p class="eyebrow">Masukkan manual</p><h2 id="queue-donation-title">{{ $queueing->donor_name }}</h2></div>
                    <button class="icon-button" type="button" wire:click="cancelQueue" aria-label="Tutup">×</button>
                </div>
                <p class="session-meta">Donasi {{ $queueing->amountLabel() }} lewat {{ $providerLabels[$queueing->provider] ?? $queueing->provider }}.</p>
                @if ($queueing->isForeign())
                    <p class="modal-help is-warning">Donasi mata uang {{ $queueing->currency }}. {{ $queueing->amount > 0 ? 'Nilai rupiah di atas adalah uang yang diterima (sudah dipotong biaya platform), jadi bisa sedikit di bawah harga paket.' : 'Nilai rupiahnya tidak dikirim platform.' }} Tentukan jumlah game sesuai kesepakatan dengan viewer.</p>
                @endif
                @if ($queueing->message)
                    <blockquote class="donation-message">“{{ $queueing->message }}”</blockquote>
                @endif
                <p class="modal-help">Hitungan paket: <strong>{{ $queuePlan }}</strong>. Ubah jumlah game bila perlu.</p>
                <div class="field-row">
                    <div>
                        <label class="field-label" for="queue-matches">Jumlah game</label>
                        <input class="field-input @error('queueMatches') is-invalid @enderror" id="queue-matches" type="number" min="1" max="{{ \App\Services\QueueService::MAX_MATCHES }}" wire:model="queueMatches" />
                    </div>
                    <div>
                        <label class="field-label" for="queue-mlbb">ID MLBB <span>(boleh kosong)</span></label>
                        <input class="field-input" id="queue-mlbb" wire:model="queueMlbbId" placeholder="1185010926 (2345)" inputmode="numeric" maxlength="40" autocomplete="off" />
                    </div>
                </div>
                @error('queueMatches') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                @if (! $session) <p class="modal-help">Belum live — pemain masuk daftar sisa game dan ikut ke sesi live berikutnya.</p> @endif
                <div class="modal-actions">
                    <button class="button button-outline" type="button" wire:click="cancelQueue">Batal</button>
                    <button class="button button-primary" type="submit">Masukkan ke antrean</button>
                </div>
            </form>
        </dialog>
    @endif
</div>
