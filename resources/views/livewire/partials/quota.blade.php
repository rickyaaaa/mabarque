{{-- Sisa jatah match + penanda menipis/habis (teks, tidak hanya warna). --}}
@if ($remaining === 0)
    <span class="quota-chip is-empty">Habis</span>
@elseif ($remaining <= 2)
    <span class="quota-chip is-low">{{ $remaining }} · menipis</span>
@else
    <span class="quota-chip">{{ $remaining }}</span>
@endif
