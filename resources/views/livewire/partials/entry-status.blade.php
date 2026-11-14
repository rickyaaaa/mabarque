{{-- Status pemain di papan antrean; pemain yang mencapai batas game sesi ditandai "Maks sesi". --}}
@php
    $labels = ['ready' => 'Siap', 'offline' => 'Offline', 'playing' => 'Main', 'skipped' => 'Dilewati', 'done' => 'Selesai'];
    $isCapped = $entry->status !== 'playing' && $cap > 0 && $entry->service_type !== 'joki' && ($played[$entry->id] ?? 0) >= $cap;
@endphp
@if ($isCapped)
    <span class="status-pill capped" title="Sudah {{ $played[$entry->id] ?? 0 }} game di sesi ini — dilewati saat mengisi party"><span class="status-dot"></span>Maks sesi</span>
@else
    <span class="status-pill {{ $entry->status }}"><span class="status-dot"></span>{{ $labels[$entry->status] ?? $entry->status }}</span>
@endif
