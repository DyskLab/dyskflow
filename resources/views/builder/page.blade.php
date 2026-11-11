{{-- A page made with the visual builder (posts.builder). "site" adds the shared header and footer; "blank" leaves them out. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fbf9f7">
    @if ($preview)<meta name="robots" content="noindex">@endif
    {!! $seo !!}
    @include('site.partials.theme-script')
    <link rel="stylesheet" href="{{ theme_asset('css/ui.css') }}">
    <link rel="stylesheet" href="{{ theme_asset('css/site.css') }}">
    {!! \App\Support\Builder\Templates::linkCss() !!}
    {{-- The site's custom CSS, before this page's own CSS so the page can still override it. --}}
    {!! \App\Support\Theme::customTag('css') !!}
    @if ($layoutCss !== '' || $preview)<style data-b-layout-css>{!! $layoutCss !!}</style>@endif
    @if ($css !== '' || $preview)<style data-b-page-css>{!! $css !!}</style>@endif
</head>
<body class="b-page{{ $preview ? ' b-preview' : '' }}">
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
@if ($layout === 'site')
    @php do_action('cms.body_open'); @endphp
    @include('site.partials.header')
@endif
<main class="b-main" id="main" tabindex="-1">{{ $builder }}</main>
@if ($layout === 'site')
    @include('site.partials.footer', ['customJsLater' => true])
    @php do_action('cms.footer'); @endphp
@endif
<script src="{{ theme_asset('js/site.js') }}"></script>
<script src="{{ asset_v('js/builder.js') }}" defer></script>
{{-- No custom JavaScript in the editor preview, like the page's own JavaScript. --}}
@unless ($preview){!! \App\Support\Theme::customTag('js') !!}@endunless
@if ($js !== '')<script data-b-page-js>{!! $js !!}</script>@endif
</body>
</html>
