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
    <link rel="stylesheet" href="{{ theme_asset('css/ui.css') }}">
    <link rel="stylesheet" href="{{ theme_asset('css/site.css') }}">
    @if (\App\Support\Builder\Templates::hasParts()){!! \App\Support\Builder\Templates::linkCss() !!}@endif
    @include('site.partials.head-hooks')
    {!! \App\Support\Theme::customTag('css') !!}
</head>
<body>
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
@php do_action('cms.body_open'); @endphp
@include('site.partials.header')
<main class="site-wrap @yield('width') site-main" id="main" tabindex="-1">
    @if (session('success'))<div class="alert" role="status">{{ session('success') }}</div>@endif
    @yield('content')
</main>
@include('site.partials.footer', ['customJsLater' => true])
@php do_action('cms.footer'); @endphp
<script src="{{ theme_asset('js/site.js') }}"></script>
{!! \App\Support\Theme::customTag('js') !!}
</body>
</html>
