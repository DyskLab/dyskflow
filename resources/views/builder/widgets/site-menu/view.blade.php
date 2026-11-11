@php
    $locale = app()->getLocale();
    $menu = \App\Models\Post::where('type', 'page')->liveIn($locale)->where('show_in_menu', true)->orderBy('menu_order')->orderBy('title')->get()->each->localize($locale);
@endphp
<nav class="site-nav" aria-label="{{ __('Main menu') }}">
    @if ($w['show_articles'])<a href="{{ site_route('blog') }}">{{ __('Articles') }}</a>@endif
    @foreach ($menu as $item)<a href="{{ $item->url() }}">{{ $item->title }}</a>@endforeach
    @foreach (\App\Support\Landing::menu() as $item)<a href="{{ $item['url'] }}">{{ $item['label'] }}</a>@endforeach
</nav>
