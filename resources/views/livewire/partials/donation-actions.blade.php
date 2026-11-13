{{-- Aksi donasi: hanya donasi yang belum diproses (Baru / Perlu cek) yang bisa dimasukkan atau diabaikan. --}}
@if (in_array($donation->status, ['received', 'review'], true))
    <button class="button button-small button-primary" type="button" wire:click="startQueue({{ $donation->id }})">Masukkan antrean</button>
    <button class="button button-small button-outline" type="button" wire:click="ignore({{ $donation->id }})"
            wire:confirm="Abaikan donasi {{ $donation->donor_name }}? Donatur tidak akan masuk antrean.">Abaikan</button>
@else
    <span class="session-meta">—</span>
@endif
