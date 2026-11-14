{{-- Aksi per pemain di papan antrean (dipakai tabel desktop & kartu mobile). --}}
<div class="row-actions">
    @if ($entry->service_type === 'joki')
        {{-- Mainin akun: tidak ikut party, game dicatat satu per satu. --}}
        <button class="row-action" type="button" wire:click="finishMatch({{ $entry->id }})" title="Catat 1 game selesai" aria-label="Catat 1 game: {{ $entry->display_name }}">✓</button>
    @elseif ($entry->status === 'playing')
        <button class="row-action" type="button" wire:click="finishMatch({{ $entry->id }})" title="Catat 1 game hanya untuk pemain ini" aria-label="Catat 1 game: {{ $entry->display_name }}">✓</button>
        <button class="row-action" type="button" wire:click="bench({{ $entry->id }})" title="Keluarkan dari party" aria-label="Keluarkan {{ $entry->display_name }} dari party">⏏</button>
    @else
        <button class="row-action" type="button" wire:click="markPlaying({{ $entry->id }})" title="Masukkan ke party" aria-label="Masukkan {{ $entry->display_name }} ke party">▶</button>
        @if ($entry->isPickedForNext())
            <button class="row-action is-active" type="button" wire:click="unpickNext({{ $entry->id }})" title="Batalkan dari Next game" aria-label="Batalkan {{ $entry->display_name }} dari Next game">⏭</button>
        @else
            <button class="row-action" type="button" wire:click="pickNext({{ $entry->id }})" title="Masukkan ke Next game" aria-label="Masukkan {{ $entry->display_name }} ke Next game">⏭</button>
        @endif
    @endif
    <button class="row-action" type="button" wire:click="openEdit({{ $entry->id }})" title="Edit pemain" aria-label="Edit {{ $entry->display_name }}">✎</button>
    @if (in_array($entry->status, ['ready', 'offline'], true))
        <button class="row-action" type="button" wire:click="toggleStatus({{ $entry->id }})" title="{{ $entry->status === 'offline' ? 'Tandai siap' : 'Tandai offline' }}" aria-label="Ubah status {{ $entry->display_name }}">↺</button>
    @endif
    <button class="row-action" type="button" wire:click="remove({{ $entry->id }})" wire:confirm="Hapus {{ $entry->display_name }} dari antrean?" title="Hapus" aria-label="Hapus {{ $entry->display_name }}">✕</button>
</div>
