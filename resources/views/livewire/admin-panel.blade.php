@php
    $statusLabels = ['pending' => 'Menunggu', 'active' => 'Aktif', 'inactive' => 'Nonaktif'];
    $filterLabels = ['pending' => 'Menunggu persetujuan', 'active' => 'Aktif', 'inactive' => 'Nonaktif', 'all' => 'Semua'];
@endphp

<div wire:poll.30s>
    <header class="admin-header">
        <div>
            <p class="eyebrow">Super Admin</p>
            <h1>Akses streamer</h1>
            <p class="session-meta">Setujui akun baru supaya streamer bisa membuka dashboard. Persetujuan mengirim email pemberitahuan.</p>
        </div>
        <div class="admin-header-actions">
            <span class="admin-who">{{ auth()->user()->email }}</span>
            <button class="button button-outline" type="button" wire:click="logout">Keluar</button>
        </div>
    </header>

    <section class="admin-stats" aria-label="Ringkasan akun streamer">
        <article class="admin-stat is-pending"><span>Menunggu persetujuan</span><strong>{{ $counts['pending'] }}</strong></article>
        <article class="admin-stat is-active"><span>Streamer aktif</span><strong>{{ $counts['active'] }}</strong></article>
        <article class="admin-stat is-inactive"><span>Dinonaktifkan</span><strong>{{ $counts['inactive'] }}</strong></article>
        <article class="admin-stat"><span>Total streamer</span><strong>{{ $counts['all'] }}</strong></article>
    </section>

    <section class="admin-panel" aria-labelledby="admin-list-title">
        <div class="admin-toolbar">
            <h2 id="admin-list-title" class="sr-only">Daftar streamer</h2>
            <div class="filter-group" role="group" aria-label="Filter status akses">
                @foreach ($filterLabels as $key => $label)
                    <button type="button" wire:click="setFilter('{{ $key }}')" @class(['filter-button', 'is-active' => $status === $key]) aria-pressed="{{ $status === $key ? 'true' : 'false' }}">
                        {{ $label }} <span>({{ $counts[$key] }})</span>
                    </button>
                @endforeach
            </div>
            <label class="admin-search">
                <span class="sr-only">Cari streamer</span>
                <input class="field-input" type="search" wire:model.live.debounce.400ms="search" placeholder="Cari email, nama channel, atau slug" />
            </label>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <caption class="sr-only">Akun streamer dan status akses beta</caption>
                <thead>
                    <tr><th scope="col">Streamer</th><th scope="col">Email</th><th scope="col">Daftar</th><th scope="col">Aktivitas</th><th scope="col">Status</th><th scope="col">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse ($streamers as $streamer)
                        <tr wire:key="streamer-{{ $streamer->id }}">
                            <td>
                                <strong>{{ $streamer->channel_name }}</strong>
                                <small>
                                    @if ($streamer->beta_status === 'active')
                                        <a href="/{{ $streamer->slug }}" target="_blank" rel="noopener">{{ '@'.$streamer->slug }} ↗</a>
                                    @else
                                        {{ '@'.$streamer->slug }}
                                    @endif
                                </small>
                            </td>
                            <td>{{ $streamer->email }}</td>
                            <td>{{ $streamer->created_at?->translatedFormat('j M Y, H:i') }}</td>
                            <td><small>{{ $streamer->live_sessions_count }} sesi · {{ $streamer->platform_connections_count }} koneksi</small></td>
                            <td><span class="admin-status is-{{ $streamer->beta_status }}">{{ $statusLabels[$streamer->beta_status] ?? $streamer->beta_status }}</span></td>
                            <td>
                                <div class="admin-actions">
                                    @if ($streamer->beta_status !== 'active')
                                        <button class="button button-small button-primary" type="button" wire:click="setBetaStatus({{ $streamer->id }}, 'active')">Setujui</button>
                                    @endif
                                    @if ($streamer->beta_status === 'inactive')
                                        <button class="button button-small button-outline" type="button" wire:click="setBetaStatus({{ $streamer->id }}, 'pending')">Tunda</button>
                                    @endif
                                    @if ($streamer->beta_status !== 'inactive')
                                        <button class="button button-small button-outline" type="button"
                                                wire:click="setBetaStatus({{ $streamer->id }}, 'inactive')"
                                                wire:confirm="Nonaktifkan {{ '@'.$streamer->slug }}? Streamer langsung keluar dan tidak bisa membuka dashboard.">Nonaktifkan</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="admin-empty">{{ $search ? 'Tidak ada streamer yang cocok dengan pencarian.' : ($status === 'pending' ? 'Tidak ada akun yang menunggu persetujuan. 🎉' : 'Belum ada akun di kategori ini.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($streamers->hasPages())
            <div class="admin-pagination">{{ $streamers->onEachSide(1)->links() }}</div>
        @endif
    </section>
</div>
