<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex" />
    <title>Panel Admin — Mabarku</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/styles.css') }}" />
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/admin.css') }}" />
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/ui-v2.css') }}" />
  </head>
  {{-- Area Super Admin: terpisah dari workspace streamer (tanpa sidebar streamer), login tetap lewat /login. --}}
  <body class="admin-body ui-v2">
    <div class="admin-topbar">
      <a class="public-brand" href="/admin"><span class="brand-mark" aria-hidden="true">MB</span><span>Mabarku <em>Admin</em></span></a>
    </div>
    <main class="admin-shell">
      <livewire:admin-panel />
    </main>

    <div class="toast" role="status" aria-live="polite"
         x-data="{ message: '', visible: false, timer: null }"
         x-on:notify.window="message = $event.detail.message; visible = true; clearTimeout(timer); timer = setTimeout(() => visible = false, 3200)"
         x-bind:class="{ 'is-visible': visible }"
         x-text="message"></div>
  </body>
</html>
