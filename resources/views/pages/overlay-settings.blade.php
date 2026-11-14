@extends('layouts.dashboard')

@section('title', 'Overlay OBS')
@section('active', 'overlay-settings')
@section('main-class', 'overlay-content')

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/overlay-settings.css') }}" />
@endpush

@section('content')
    <livewire:overlay-settings-panel />
@endsection
