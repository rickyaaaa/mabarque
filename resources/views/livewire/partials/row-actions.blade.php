{{-- Aksi per pemain di papan antrean (dipakai tabel desktop & kartu mobile). Ikon + label tersembunyi untuk pembaca layar. --}}
<div class="row-actions">
    @if ($entry->service_type === 'joki')
        {{-- VIP Mainin akun: tidak ikut party & tidak dihitung per game. Menunggu → Sedang dimainkan → Selesai. --}}
        @if ($entry->status === 'playing')
            <button class="row-action row-action-done" type="button" wire:click="finishJoki({{ $entry->id }})" title="Selesai dimainkan" aria-label="Tandai akun {{ $entry->display_name }} selesai dimainkan">@include('partials.icon', ['name' => 'check'])</button>
        @else
            <button class="row-action" type="button" wire:click="startJoki({{ $entry->id }})" title="Mulai mainkan akun" aria-label="Mulai mainkan akun {{ $entry->display_name }}">@include('partials.icon', ['name' => 'play'])</button>
        @endif
    @elseif (! $isLive)
        {{-- Belum live: hanya kelola data pemain; party & catat game tersedia saat live. --}}
    @elseif ($entry->status === 'playing')
        <button class="row-action" type="button" wire:click="finishMatch({{ $entry->id }})" title="Catat 1 game hanya untuk pemain ini" aria-label="Catat 1 game: {{ $entry->display_name }}">@include('partials.icon', ['name' => 'check'])</button>
        <button class="row-action" type="button" wire:click="bench({{ $entry->id }})" title="Keluarkan dari party" aria-label="Keluarkan {{ $entry->display_name }} dari party">@include('partials.icon', ['name' => 'eject'])</button>
    @else
        <button class="row-action" type="button" wire:click="markPlaying({{ $entry->id }})" title="Masukkan ke party" aria-label="Masukkan {{ $entry->display_name }} ke party">@include('partials.icon', ['name' => 'play'])</button>
        @if ($entry->isPickedForNext())
            <button class="row-action is-active" type="button" wire:click="unpickNext({{ $entry->id }})" title="Batalkan dari Next game" aria-label="Batalkan {{ $entry->display_name }} dari Next game">@include('partials.icon', ['name' => 'skip'])</button>
        @else
            <button class="row-action" type="button" wire:click="pickNext({{ $entry->id }})" title="Masukkan ke Next game" aria-label="Masukkan {{ $entry->display_name }} ke Next game">@include('partials.icon', ['name' => 'skip'])</button>
        @endif
    @endif
    <button class="row-action" type="button" wire:click="openEdit({{ $entry->id }})" title="Edit pemain" aria-label="Edit {{ $entry->display_name }}">@include('partials.icon', ['name' => 'pencil'])</button>
    @if (in_array($entry->status, ['ready', 'offline'], true))
        <button class="row-action" type="button" wire:click="toggleStatus({{ $entry->id }})" title="{{ $entry->status === 'offline' ? 'Tandai siap' : 'Tandai offline' }}" aria-label="Ubah status {{ $entry->display_name }}">@include('partials.icon', ['name' => 'rotate'])</button>
    @endif
    <button class="row-action row-action-danger" type="button" wire:click="remove({{ $entry->id }})" wire:confirm="Hapus {{ $entry->display_name }} dari antrean?" title="Hapus" aria-label="Hapus {{ $entry->display_name }}">@include('partials.icon', ['name' => 'trash'])</button>
</div>
