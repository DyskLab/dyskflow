@php $items = array_values(array_filter($w['items'], fn ($i) => $i['title'] !== '')); $label = __('Slides'); @endphp
@if ($items)
<div class="b-carousel b-slides b-slides-{{ $w['height'] }} b-slides-{{ $w['align'] }} b-slides-overlay-{{ $w['overlay'] }}" data-b-carousel data-autoplay="{{ (int) $w['autoplay'] }}" role="region" aria-roledescription="{{ __('carousel') }}" aria-label="{{ $label }}">
    <div class="b-carousel-track" tabindex="0">
        @foreach ($items as $i => $item)
            <div class="b-carousel-slide b-slide" aria-roledescription="{{ __('slide') }}" aria-label="{{ ($i + 1).' / '.count($items) }}">
                @if ($item['image'] !== '')<img class="b-slide-bg" src="{{ $item['image'] }}" alt="" @if ($i > 0) loading="lazy" @else fetchpriority="high" @endif decoding="async">@endif
                <div class="b-slide-body stack-sm">
                    @if ($item['kicker'] !== '')<span class="b-kicker">{{ $item['kicker'] }}</span>@endif
                    @php $tag = $i === 0 && $w['first_heading'] ? 'h1' : 'h2'; @endphp
                    <{{ $tag }} class="b-heading b-size-xl">{{ $item['title'] }}</{{ $tag }}>
                    @if ($item['text'] !== '')<p>{{ $item['text'] }}</p>@endif
                    @if ($item['button_label'] !== '' && $item['url'] !== '')<div class="b-buttons"><a class="btn b-btn-lg" href="{{ $item['url'] }}">{{ $item['button_label'] }}</a></div>@endif
                </div>
            </div>
        @endforeach
    </div>
    @if ($w['arrows'])
        <button type="button" class="b-carousel-btn b-carousel-prev" data-b-prev aria-label="{{ __('Previous') }}">{{ icon('left', 20) }}</button>
        <button type="button" class="b-carousel-btn b-carousel-next" data-b-next aria-label="{{ __('Next') }}">{{ icon('right', 20) }}</button>
    @endif
    @if ($w['dots'])<div class="b-carousel-dots" data-b-dots></div>@endif
</div>
@endif
