@extends('layouts.dashboard')

@section('title', 'Hitungan Match')
@section('active', 'match-count')
@section('main-class', 'match-content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/match-count.css') }}" />
@endpush

@section('content')
    @if ($session)
        <livewire:match-board :session="$session" />
    @else
        @include('partials.no-session', ['heading' => 'Hitungan match'])
    @endif
@endsection
