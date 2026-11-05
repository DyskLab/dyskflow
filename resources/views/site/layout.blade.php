@php
    $languageLink = \App\Support\Seo::languageLink($seo);
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fbf9f7">
    @include('site.partials.seo')
    @include('site.partials.theme-script')
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}?v={{ config('cms.version') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ config('cms.version') }}">
    @include('site.partials.head-hooks')
</head>
<body>
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
@php do_action('cms.body_open'); @endphp
@include('site.partials.header')
<main class="site-wrap @yield('width') site-main" id="main" tabindex="-1">
    @if (session('success'))<div class="alert" role="status">{{ session('success') }}</div>@endif
    @yield('content')
</main>
@include('site.partials.footer')
@php do_action('cms.footer'); @endphp
<script src="{{ asset('js/site.js') }}?v={{ config('cms.version') }}"></script>
</body>
</html>
