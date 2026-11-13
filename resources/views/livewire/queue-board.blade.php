<section wire:poll.5s="refreshQueue" aria-label="Papan antrean mabar">
    <header>
        <h1>{{ $session->title }}</h1>
        <p>{{ $session->status === 'active' ? 'Sesi aktif' : 'Sesi berakhir' }}</p>
    </header>

    @if ($currentEntry)
        <div role="status">
            <strong>Giliran sekarang</strong>
            <span>{{ $currentEntry->player_name }}</span>
        </div>
    @endif

    <div role="group" aria-label="Filter status pemain">
        <button type="button" wire:click="setFilter('all')" @class(['is-active' => $filter === 'all'])>Semua</button>
        <button type="button" wire:click="setFilter('ready')" @class(['is-active' => $filter === 'ready'])>Siap</button>
        <button type="button" wire:click="setFilter('offline')" @class(['is-active' => $filter === 'offline'])>Offline</button>
    </div>

    <button type="button" wire:click="$set('showAddPlayer', true)">Tambah pemain</button>

    @if ($showAddPlayer)
        <form wire:submit="addPlayer">
            <label>Nama pemain <input wire:model="playerName" required /></label>
            @error('playerName') <span role="alert">{{ $message }}</span> @enderror
            <label>Jatah mabar <input wire:model="matchesTotal" type="number" min="1" max="99" required /></label>
            @error('matchesTotal') <span role="alert">{{ $message }}</span> @enderror
            <button type="submit">Masukkan ke antrean</button>
            <button type="button" wire:click="$set('showAddPlayer', false)">Batal</button>
        </form>
    @endif

    <ol>
        @forelse ($entries as $entry)
            <li wire:key="queue-entry-{{ $entry->id }}">
                <span>{{ $entry->position }}</span>
                <strong>{{ $entry->player_name }}</strong>
                <span>{{ $entry->status }}</span>
                <span>{{ $entry->matches_remaining }} match tersisa</span>
                <button type="button" wire:click="move({{ $entry->id }}, 'up')" aria-label="Naikkan {{ $entry->player_name }}">↑</button>
                <button type="button" wire:click="move({{ $entry->id }}, 'down')" aria-label="Turunkan {{ $entry->player_name }}">↓</button>
                <button type="button" wire:click="toggleStatus({{ $entry->id }})">Ubah status</button>
                <button type="button" wire:click="skip({{ $entry->id }})">Lewati</button>
                <button type="button" wire:click="remove({{ $entry->id }})">Hapus</button>
            </li>
        @empty
            <li>Belum ada pemain pada filter ini.</li>
        @endforelse
    </ol>
</section>
