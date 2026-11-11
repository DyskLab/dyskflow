@php $items = array_values(array_filter($w['items'], fn ($i) => $i['text'] !== '')); $label = __('Reviews'); @endphp
@if ($items)
<div class="b-carousel b-reviews {{ \App\Support\Builder\Tokens::classes('view', $w['per_view'], ['1', '2', '3', '4', '5']) }}" data-b-carousel data-autoplay="{{ (int) $w['autoplay'] }}" role="region" aria-roledescription="{{ __('carousel') }}" aria-label="{{ $label }}">
    <div class="b-carousel-track" tabindex="0">
        @foreach ($items as $item)
            <figure class="b-carousel-slide b-review" aria-roledescription="{{ __('slide') }}">
                @if ((int) $item['rating'] > 0)<span class="b-stars" style="--b-rating: {{ (int) $item['rating'] }}" role="img" aria-label="{{ __(':rating out of 5 stars', ['rating' => (int) $item['rating']]) }}"></span>@endif
                <blockquote><p>{{ $item['text'] }}</p></blockquote>
                @if ($item['name'] !== '')
                    <figcaption>
                        @if ($item['image'] !== '')<img src="{{ $item['image'] }}" alt="" width="40" height="40" loading="lazy">@else<span class="b-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($item['name'], 0, 1)) }}</span>@endif
                        <span><strong>{{ $item['name'] }}</strong>@if ($item['role'] !== '')<span>{{ $item['role'] }}</span>@endif</span>
                    </figcaption>
                @endif
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
