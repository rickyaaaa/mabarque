{{-- Terapkan tema tersimpan (terang/gelap) sebelum CSS dirender supaya tidak berkedip.
     Pakai di <head> setiap halaman yang memuat partials.sidebar (tombol tema ada di sidebar). --}}
<script>
  (() => {
    // Kunci lama (sebelum ganti nama ke Mabarku) dipindahkan sekali supaya pilihan tema user tidak hilang.
    if (localStorage.getItem('mabarku-theme') === null && localStorage.getItem('mabarqueue-theme') !== null) {
      localStorage.setItem('mabarku-theme', localStorage.getItem('mabarqueue-theme'));
      localStorage.removeItem('mabarqueue-theme');
    }
    const theme = localStorage.getItem('mabarku-theme') === 'dark' ? 'dark' : 'light';
    document.documentElement.dataset.theme = theme;
  })();
</script>
