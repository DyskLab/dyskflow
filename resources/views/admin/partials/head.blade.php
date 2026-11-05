<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#ffffff">
{{ cms_favicon() }}
<title>{{ $title ?? 'CMS' }}</title>
<script>
(function () {
  var theme = null, sidebar = null;
  try { theme = localStorage.getItem('theme'); sidebar = localStorage.getItem('sidebar'); } catch (e) {}
  if (sidebar === 'collapsed') document.documentElement.classList.add('sidebar-collapsed');
  if (theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
    document.querySelector('meta[name="theme-color"]').content = '#0a0a0a';
  }
})();
</script>
<link rel="stylesheet" href="{{ asset('css/ui.css') }}?v={{ config('cms.version') }}">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ config('cms.version') }}">
