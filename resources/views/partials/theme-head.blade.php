{{-- Terapkan tema tersimpan (terang/gelap) sebelum CSS dirender supaya tidak berkedip.
     Pakai di <head> setiap halaman yang memuat partials.sidebar (tombol tema ada di sidebar). --}}
<script>
  (() => {
    const theme = localStorage.getItem('mabarqueue-theme') === 'dark' ? 'dark' : 'light';
    document.documentElement.dataset.theme = theme;
  })();
</script>
