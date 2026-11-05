<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>404</title>
    <script>
    (function () {
      var theme = null;
      try { theme = localStorage.getItem('theme'); } catch (e) {}
      if (theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
    })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}">
    <style>.c{min-height:100vh;display:grid;place-items:center;text-align:center;padding:16px}</style>
</head>
<body>
<div class="c"><div class="stack-sm">
    <h1 style="font-size:40px;font-weight:700">404</h1>
    <p class="muted">{{ __('This page could not be found.') }}</p>
    <div><a class="btn btn-outline" href="{{ url('/') }}">{{ __('Back to home') }}</a></div>
</div></div>
</body>
</html>
