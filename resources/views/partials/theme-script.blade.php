{{-- Tombol "Mode gelap/terang" di sidebar. Pakai sebelum </body> bersama partials.theme-head. --}}
<script>
  (() => {
    const root = document.documentElement;
    const buttons = document.querySelectorAll('[data-theme-toggle]');
    const syncThemeControls = () => {
      const dark = root.dataset.theme === 'dark';
      buttons.forEach((button) => {
        button.setAttribute('aria-pressed', dark ? 'true' : 'false');
        button.querySelector('[data-theme-label]').textContent = dark ? 'Mode terang' : 'Mode gelap';
      });
    };

    syncThemeControls();
    buttons.forEach((button) => button.addEventListener('click', () => {
      const theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
      root.dataset.theme = theme;
      localStorage.setItem('mabarqueue-theme', theme);
      syncThemeControls();
    }));
  })();
</script>
