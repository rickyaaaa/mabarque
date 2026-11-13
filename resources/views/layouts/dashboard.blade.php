{{--
    Layout halaman dashboard streamer (Livewire).
    Pakai: @extends('layouts.dashboard') + @section('title'), @section('active'), @push('styles'), @section('content').
    Komponen memicu toast dengan: $this->dispatch('notify', message: '...').
--}}
<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', 'Dashboard') — MabarQueue</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Agdasima:wght@400;700&family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,600;12..96,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}" />
    @stack('styles')
  </head>
  <body>
    <div class="app-shell">
      @include('partials.sidebar', ['active' => trim($__env->yieldContent('active'))])
      <main class="main-content @yield('main-class')" id="main">
        @yield('content')
      </main>
    </div>

    <div class="toast" role="status" aria-live="polite"
         x-data="{ message: '', visible: false, timer: null }"
         x-on:notify.window="message = $event.detail.message; visible = true; clearTimeout(timer); timer = setTimeout(() => visible = false, 2800)"
         x-bind:class="{ 'is-visible': visible }"
         x-text="message"></div>
  </body>
</html>
