@extends('layouts.dashboard')

@section('title', 'Papan Antrean')
@section('active', 'dashboard')

@section('content')
    @if ($session)
        <livewire:queue-board :session="$session" />
    @else
        @include('partials.no-session', ['heading' => 'Papan antrean'])
    @endif
@endsection
