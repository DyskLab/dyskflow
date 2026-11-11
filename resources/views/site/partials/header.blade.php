{{-- The one site header, shared by the default theme and landing pages. Needs $languageLink. A Header template (Design > Templates) replaces it. --}}
{{-- The site's custom CSS for landing pages, which load their own <head>; layouts print it there first. --}}
{!! \App\Support\Theme::customTag('css') !!}
@if ($headerTemplate = \App\Support\Builder\Templates::part('header', $languageLink ?? null))
{{ $headerTemplate }}
@else
@php
    $locale = app()->getLocale();
    $menu = \App\Models\Post::where('type', 'page')->liveIn($locale)->where('show_in_menu', true)->orderBy('menu_order')->orderBy('title')->get()->each->localize($locale);
@endphp
<header class="site-header">
    <div class="site-wrap wide">
        <div class="site-bar">
            <a class="site-name" href="{{ site_route('home') }}"><img src="{{ cms_asset_url(setting('favicon')) ?: asset('img/logo-mark.svg') }}" alt="" width="28" height="28"> {{ setting('site_title') }}</a>
            <nav class="site-nav" aria-label="{{ __('Main menu') }}">
                <a href="{{ site_route('blog') }}">{{ __('Articles') }}</a>
                @foreach ($menu as $item)<a href="{{ $item->url() }}">{{ $item->title }}</a>@endforeach
                @foreach (\App\Support\Landing::menu() as $item)<a href="{{ $item['url'] }}">{{ $item['label'] }}</a>@endforeach
            </nav>
            <div class="site-tools">
                <form action="{{ site_route('search') }}" method="get" role="search">
                    <input class="input" type="search" name="q" autocomplete="off" value="{{ is_string(request('q')) ? request('q') : '' }}" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}">
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
@endif
