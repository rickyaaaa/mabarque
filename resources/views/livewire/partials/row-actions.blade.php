{{-- Aksi per pemain di papan antrean (dipakai tabel desktop & kartu mobile). --}}
<div class="row-actions">
    @if ($entry->status !== 'playing')
        <button class="row-action" type="button" wire:click="markPlaying({{ $entry->id }})" title="Mulai main" aria-label="Mulai main: {{ $entry->display_name }}">▶</button>
    @else
        <button class="row-action" type="button" wire:click="finishMatch({{ $entry->id }})" title="Selesai match" aria-label="Selesai match: {{ $entry->display_name }}">✓</button>
    @endif
    <button class="row-action" type="button" wire:click="openEdit({{ $entry->id }})" title="Edit pemain" aria-label="Edit {{ $entry->display_name }}">✎</button>
    <button class="row-action" type="button" wire:click="move({{ $entry->id }}, 'up')" title="Naikkan" aria-label="Naikkan {{ $entry->display_name }}">↑</button>
    <button class="row-action" type="button" wire:click="move({{ $entry->id }}, 'down')" title="Turunkan" aria-label="Turunkan {{ $entry->display_name }}">↓</button>
    @if (in_array($entry->status, ['ready', 'offline'], true))
        <button class="row-action" type="button" wire:click="toggleStatus({{ $entry->id }})" title="{{ $entry->status === 'offline' ? 'Tandai siap' : 'Tandai offline' }}" aria-label="Ubah status {{ $entry->display_name }}">↺</button>
    @endif
    <button class="row-action" type="button" wire:click="remove({{ $entry->id }})" wire:confirm="Hapus {{ $entry->display_name }} dari antrean?" title="Hapus" aria-label="Hapus {{ $entry->display_name }}">✕</button>
</div>
