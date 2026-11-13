@extends('layouts.dashboard')

@section('title', 'Sesi Live')
@section('active', 'live-session')
@section('main-class', 'live-content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/live-session.css') }}" />
@endpush

@section('content')
    @if (session('notice'))
        <p class="page-notice" role="status">{{ session('notice') }}</p>
    @endif
    <livewire:live-session-panel />
@endsection
