{{-- Label status hanya untuk keadaan yang tidak biasa; pemain "Siap" tidak diberi label. --}}
@php
    $isCapped = $entry->status !== 'playing' && $cap > 0 && $entry->service_type !== 'joki' && ($played[$entry->id] ?? 0) >= $cap;
    $label = $isCapped ? 'Maks sesi' : ['offline' => 'Offline', 'playing' => 'Main', 'skipped' => 'Dilewati', 'done' => 'Selesai'][$entry->status] ?? null;
@endphp
@if ($label)
    <span class="status-pill {{ $isCapped ? 'capped' : $entry->status }}" @if ($isCapped) title="Sudah {{ $played[$entry->id] ?? 0 }} game di sesi ini — dilewati saat mengisi party" @endif><span class="status-dot"></span>{{ $label }}</span>
@endif
