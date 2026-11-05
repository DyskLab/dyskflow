<script>
(function () {
  var theme = null;
  try { theme = localStorage.getItem('theme'); } catch (e) {}
  if (theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
    document.querySelector('meta[name="theme-color"]').content = '#141210';
  }
})();
</script>
