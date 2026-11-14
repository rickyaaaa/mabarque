{{-- Sisa jatah game: angka saja dalam kotak berukuran sama; "Habis" bila jatah sudah 0. --}}
@if ($remaining === 0)
    <span class="quota-chip is-empty">Habis</span>
@else
    <span class="quota-chip">{{ $remaining }}</span>
@endif
