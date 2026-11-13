@extends('layouts.dashboard')

@section('title', 'VIP & Donasi')
@section('active', 'donations')
@section('main-class', 'donation-content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/donations.css') }}" />
@endpush

@section('content')
    <livewire:donation-inbox />
@endsection
