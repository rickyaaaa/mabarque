@extends('layouts.dashboard')

@section('title', 'Paket VIP')
@section('active', 'vip-packages')
@section('main-class', 'packages-content')

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/vip-packages.css') }}" />
@endpush

@section('content')
    <livewire:package-manager />
@endsection
