<div class="site-tools">
    @if ($w['search'])
        <form action="{{ site_route('search') }}" method="get" role="search">
            <input class="input" type="search" name="q" autocomplete="off" value="{{ is_string(request('q')) ? request('q') : '' }}" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}">
        </form>
    @endif
    @if ($w['language'] && ($link = \App\Support\Builder\Context::$languageLink))
        <a class="btn btn-ghost btn-sm" href="{{ $link['url'] }}" hreflang="{{ $link['locale'] }}" lang="{{ $link['locale'] }}" title="{{ \App\Support\Locales::name($link['locale']) }}" aria-label="{{ \App\Support\Locales::name($link['locale']) }}">{{ icon('languages') }} {{ strtoupper($link['locale']) }}</a>
    @endif
    @if ($w['theme'])@include('admin.partials.theme-toggle')@endif
</div>
