@if ($w['image'] !== '')
<div class="b-hotspot">
    <img src="{{ $w['image'] }}" alt="{{ $w['alt'] }}" loading="lazy" decoding="async">
    @foreach (array_values(array_filter($w['points'], fn ($p) => $p['title'] !== '')) as $i => $point)
        @php $x = max(0, min(100, (int) $point['x'])); $y = max(0, min(100, (int) $point['y'])); @endphp
        <div class="b-hotspot-point{{ $x > 60 ? ' is-right' : '' }}{{ $y > 60 ? ' is-up' : '' }}" style="left: {{ $x }}%; top: {{ $y }}%">
            <button type="button" class="b-hotspot-dot b-hotspot-{{ $w['marker'] }}" aria-expanded="false" data-b-hotspot aria-label="{{ $point['title'] }}">@if ($w['marker'] === 'number'){{ $i + 1 }}@elseif ($w['marker'] === 'plus'){{ icon('plus', 14) }}@endif</button>
            <div class="b-hotspot-tip" role="tooltip">
                <strong>{{ $point['title'] }}</strong>
                @if ($point['text'] !== '')<p>{{ $point['text'] }}</p>@endif
                @if ($point['url'] !== '')<a href="{{ $point['url'] }}">{{ __('Learn more') }} →</a>@endif
            </div>
        </div>
    @endforeach
</div>
@endif
