@extends('layouts.dashboard')

@section('title', 'Papan Antrean')
@section('active', 'dashboard')
{{-- Tampilan baru (percobaan): neo-brutalism putih-hitam + aksen lime, hanya di papan antrean. --}}
@section('body-class', 'ui-v2')

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/board-v2.css') }}" />
@endpush

@section('content')
    @if ($session)
        <livewire:queue-board :session="$session" />
    @else
        @include('partials.no-session', ['heading' => 'Papan antrean'])
    @endif
@endsection
