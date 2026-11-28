{{-- Nama, ID MLBB, dan penanda catatan pemain (tabel desktop & kartu mobile papan antrean). --}}
<span class="player-identity">
    <span class="player-name">{{ $entry->display_name }}</span>
    @if (trim((string) $entry->player_name) !== '')
        <small @class(['player-mlbb-id', 'is-missing' => ! $entry->mlbb_id])>{{ $entry->mlbb_id ? 'ID '.$entry->mlbb_id : 'ID belum ada' }}</small>
    @endif
    @if ($joined = $entry->joinedLabel())
        <small class="player-joined" title="{{ $entry->joinedTitle() }}">{{ $joined }}</small>
    @endif
    @if ($entry->provider !== 'manual')
        {{-- Masuk otomatis dari donasi: tampilkan nominal & pesan supaya streamer bisa menilai apakah memang mau mabar. --}}
        <small class="player-donation" title="{{ $entry->donation?->message }}">Donasi {{ $entry->donation ? $entry->donation->amountLabel() : 'Rp '.number_format((int) $entry->donation_amount, 0, ',', '.') }}{{ $entry->donation?->message ? ' · “'.\Illuminate\Support\Str::limit($entry->donation->message, 50).'”' : ' · tanpa pesan' }}</small>
    @endif
    @if ($entry->note)
        <small class="player-note" title="{{ $entry->note }}">@include('partials.icon', ['name' => 'pencil', 'size' => 11]) {{ \Illuminate\Support\Str::limit($entry->note, 60) }}</small>
    @endif
</span>
