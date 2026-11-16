@extends('layouts.dashboard')

@php
    $rupiah = fn (int $value) => 'Rp '.number_format($value, 0, ',', '.');
    $providerLabels = ['manual' => 'Manual', 'saweria' => 'Saweria', 'sociabuzz' => 'SociaBuzz', 'trakteer' => 'Trakteer'];
    $donationStatus = ['received' => 'Baru', 'review' => 'Perlu cek', 'queued' => 'Masuk antrean', 'reward' => 'Reward', 'ignored' => 'Diabaikan', 'refunded' => 'Refund'];
    $duration = $summary['duration'];
@endphp

@section('title', 'Arsip — '.$session->title)
@section('active', 'live-session')
@section('main-class', 'live-content')

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/live-session.css') }}" />
@endpush

@section('content')
    <header class="live-header archive-header">
        <div>
            <a class="archive-back" href="/live-session">← Kembali ke Sesi Live</a>
            <p class="eyebrow">Arsip sesi · hanya dilihat</p>
            <h1>{{ $session->title }}</h1>
            <p class="session-meta">
                {{ $session->started_at?->translatedFormat('l, j F Y') }}
                · {{ $session->started_at?->format('H:i') }}–{{ $session->ended_at?->format('H:i') }} WIB
                @if ($duration) · {{ $duration->h > 0 ? $duration->h.' jam ' : '' }}{{ $duration->i }} menit @endif
            </p>
        </div>
        <a class="button button-outline" href="/dashboard">Kelola di papan antrean</a>
    </header>

    <p class="archive-note">Halaman ini hanya rekap. Untuk mengubah sisa game, mengoreksi match, atau mengedit pemain, buka papan antrean.</p>

    <section class="live-stat-grid archive-stats" aria-label="Ringkasan sesi">
        <article class="live-stat live-stat-red"><span>Game dimainkan</span><strong>{{ $summary['games'] }}</strong><small>Win {{ $summary['wins'] }} · Lose {{ $summary['losses'] }}</small></article>
        <article class="live-stat live-stat-orange"><span>Pemain ikut main</span><strong>{{ $summary['players'] }}</strong><small>pemain berbeda</small></article>
        <article class="live-stat"><span>Donasi masuk</span><strong>{{ $summary['donations'] }}</strong><small>{{ $rupiah($summary['donation_all']) }} total</small></article>
        <article class="live-stat"><span>Donasi VIP</span><strong class="archive-money">{{ $rupiah($summary['donation_total']) }}</strong><small>yang masuk antrean</small></article>
    </section>

    <div class="archive-grid">
        <section class="archive-panel" aria-labelledby="archive-games-title">
            <div class="panel-heading"><div><p class="eyebrow">Urutan main</p><h2 id="archive-games-title">Game hari itu</h2></div><span class="panel-count">{{ $games->count() }} game</span></div>
            @forelse ($games as $game)
                <div class="archive-game">
                    <span class="archive-game-number">{{ $game['number'] }}</span>
                    <div>
                        <strong>{{ implode(' · ', $game['players']) }}</strong>
                        @if ($game['result'])<span @class(['archive-result', $game['result']])>{{ $game['result'] === 'win' ? 'Menang' : 'Kalah' }}</span>@endif
                        <small>{{ $game['time']?->format('H:i') }} WIB{{ $game['note'] ? ' · '.$game['note'] : '' }}</small>
                    </div>
                </div>
            @empty
                <p class="session-meta">Tidak ada game yang dicatat di sesi ini.</p>
            @endforelse
        </section>

        <section class="archive-panel" aria-labelledby="archive-players-title">
            <div class="panel-heading"><div><p class="eyebrow">Per pemain</p><h2 id="archive-players-title">Rekap pemain</h2></div><span class="panel-count">{{ $players->count() }} pemain</span></div>
            @if ($players->isNotEmpty())
                <div class="archive-table-wrap">
                    <table class="archive-table">
                        <thead><tr><th scope="col">Pemain</th><th scope="col">Main</th><th scope="col">Sisa saat selesai</th></tr></thead>
                        <tbody>
                            @foreach ($players as $player)
                                <tr>
                                    <td><strong>{{ $player['name'] }}</strong><small>{{ $player['service'] }}{{ $player['mlbb_id'] ? ' · ID '.$player['mlbb_id'] : '' }}</small></td>
                                    <td>{{ $player['played'] }} game</td>
                                    <td>{{ $player['remaining_at_end'] > 0 ? $player['remaining_at_end'].' game' : 'Habis' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="session-meta">Belum ada pemain yang main di sesi ini.</p>
            @endif
        </section>
    </div>

    <section class="archive-panel archive-donations" aria-labelledby="archive-donations-title">
        <div class="panel-heading"><div><p class="eyebrow">Masuk selama sesi</p><h2 id="archive-donations-title">Donasi</h2></div><span class="panel-count">{{ $donations->count() }} donasi</span></div>
        @if ($donations->isNotEmpty())
            <div class="archive-table-wrap">
                <table class="archive-table">
                    <thead><tr><th scope="col">Jam</th><th scope="col">Donatur</th><th scope="col">Nominal</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        @foreach ($donations as $donation)
                            <tr>
                                <td>{{ $donation->received_at?->format('H:i') }}</td>
                                <td><strong>{{ $donation->donor_name }}</strong><small>{{ $providerLabels[$donation->provider] ?? $donation->provider }}{{ $donation->message ? ' · “'.\Illuminate\Support\Str::limit($donation->message, 60).'”' : '' }}</small></td>
                                <td>{{ $donation->amountLabel() }}</td>
                                <td>{{ $donationStatus[$donation->status] ?? $donation->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="session-meta">Tidak ada donasi yang masuk selama sesi ini.</p>
        @endif
    </section>
@endsection
