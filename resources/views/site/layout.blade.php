@php
    $locale = app()->getLocale();
    $menu = \App\Models\Post::where('type', 'page')->liveIn($locale)->where('show_in_menu', true)->orderBy('menu_order')->orderBy('title')->get()->each->localize($locale);
    $languageLink = \App\Support\Seo::languageLink($seo);
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    @include('site.partials.seo')
    <script>
    (function () {
      var theme = null;
      try { theme = localStorage.getItem('theme'); } catch (e) {}
      if (theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
        document.querySelector('meta[name="theme-color"]').content = '#0a0a0a';
      }
    })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}?v={{ config('cms.version') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ config('cms.version') }}">
    @include('site.partials.head-hooks')
</head>
<body>
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
@php do_action('cms.body_open'); @endphp
<header class="site-header">
    <div class="site-wrap wide">
        <div class="site-bar">
            <a class="site-name" href="{{ site_route('home') }}">{{ setting('site_title') }}</a>
            <nav class="site-nav" aria-label="{{ __('Main menu') }}">
                <a href="{{ site_route('blog') }}">{{ __('Articles') }}</a>
                @foreach ($menu as $item)<a href="{{ $item->url() }}">{{ $item->title }}</a>@endforeach
            </nav>
            <div class="site-tools">
                <form action="{{ site_route('search') }}" method="get" role="search">
                    <input class="input" style="min-height:32px;padding:4px 10px;width:150px" type="search" name="q" autocomplete="off" value="{{ is_string(request('q')) ? request('q') : '' }}" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}">
                </form>
                {{-- A plain link, not the admin's form: each language has its own address and no cookie. --}}
                @if ($languageLink)
                    <a class="btn btn-ghost btn-sm" href="{{ $languageLink['url'] }}" hreflang="{{ $languageLink['locale'] }}" lang="{{ $languageLink['locale'] }}" title="{{ \App\Support\Locales::name($languageLink['locale']) }}" aria-label="{{ \App\Support\Locales::name($languageLink['locale']) }}">{{ icon('languages') }} {{ strtoupper($languageLink['locale']) }}</a>
                @endif
                @include('admin.partials.theme-toggle')
            </div>
        </div>
    </div>
</header>
<main class="site-wrap site-main" id="main" tabindex="-1">
    @if (session('success'))<div class="alert" role="status">{{ session('success') }}</div>@endif
    @yield('content')
</main>
<footer class="site-footer">
    <div class="site-wrap wide">© {{ date('Y') }} {{ setting('site_title') }}@if (\App\Support\Locales::text('tagline')) · {{ \App\Support\Locales::text('tagline') }}@endif</div>
</footer>
@php do_action('cms.footer'); @endphp
<script src="{{ asset('js/site.js') }}?v={{ config('cms.version') }}"></script>
</body>
</html>
