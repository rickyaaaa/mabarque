{{-- Jenis layanan pemain: Mabar VIP, atau VIP Mainin akun beserta status pengerjaannya. --}}
@if ($entry->service_type === 'joki')
    <span class="service-stack">
        <span class="service-pill joki">VIP Mainin akun</span>
        <small @class(['joki-state', 'is-playing' => $entry->status === 'playing'])>{{ $entry->status === 'playing' ? 'Sedang dimainkan' : 'Menunggu' }}</small>
    </span>
@else
    <span class="service-pill vip">Mabar VIP</span>
@endif
