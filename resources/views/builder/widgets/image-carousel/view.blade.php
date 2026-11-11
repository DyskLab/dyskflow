@php $images = array_values(array_filter($w['images'], fn ($i) => $i['src'] !== '')); $label = __('Images'); @endphp
@if ($images)
<div class="b-carousel b-imgcarousel b-ratio-{{ str_replace(':', '-', $w['ratio']) }}{{ $w['logos'] ? ' b-logos' : '' }} {{ \App\Support\Builder\Tokens::classes('view', $w['per_view'], ['1', '2', '3', '4', '5']) }}" data-b-carousel data-autoplay="{{ (int) $w['autoplay'] }}" role="region" aria-roledescription="{{ __('carousel') }}" aria-label="{{ $label }}">
    <div class="b-carousel-track" tabindex="0">
        @foreach ($images as $image)
            <figure class="b-carousel-slide" aria-roledescription="{{ __('slide') }}">
                @if ($image['url'] !== '')<a href="{{ $image['url'] }}">@endif
                <img src="{{ $image['src'] }}" alt="{{ $image['alt'] }}" loading="lazy" decoding="async">
                @if ($image['url'] !== '')</a>@endif
                @if ($image['caption'] !== '')<figcaption>{{ $image['caption'] }}</figcaption>@endif
            </figure>
        @endforeach
    </div>
    @if ($w['arrows'])
        <button type="button" class="b-carousel-btn b-carousel-prev" data-b-prev aria-label="{{ __('Previous') }}">{{ icon('left', 20) }}</button>
        <button type="button" class="b-carousel-btn b-carousel-next" data-b-next aria-label="{{ __('Next') }}">{{ icon('right', 20) }}</button>
    @endif
    @if ($w['dots'])<div class="b-carousel-dots" data-b-dots></div>@endif
</div>
@endif
