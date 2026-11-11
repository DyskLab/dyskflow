@php $tagline = app()->getLocale() === \App\Support\Locales::default() ? (string) setting('tagline') : ((string) setting('tagline_translated') ?: (string) setting('tagline')); @endphp
<div class="b-sitetitle">
    <{{ $w['tag'] }} class="b-heading b-size-{{ $w['size'] }}">@if ($w['link'])<a href="{{ site_route('home') }}">{{ setting('site_title') }}</a>@else{{ setting('site_title') }}@endif</{{ $w['tag'] }}>
    @if ($w['tagline'] && $tagline !== '')<p class="muted">{{ $tagline }}</p>@endif
</div>
