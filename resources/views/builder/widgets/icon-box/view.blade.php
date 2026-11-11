@php $items = array_values(array_filter($w['items'], fn ($i) => $i['title'] !== '')); @endphp
@if ($items)
<div class="b-iconboxes b-cards {{ \App\Support\Builder\Tokens::classes('cards', $w['columns'], ['1', '2', '3', '4']) }} b-iconbox-{{ $w['position'] }}{{ $w['boxed'] ? ' b-iconbox-boxed' : '' }}">
    @foreach ($items as $item)
        <div class="b-iconbox">
            <span class="b-icon b-icon-md b-icon-{{ $w['variant'] }}" aria-hidden="true">{{ icon($item['icon'], 24) }}</span>
            <div class="b-iconbox-body">
                <h3>@if ($item['url'] !== '')<a href="{{ $item['url'] }}">{{ $item['title'] }}</a>@else{{ $item['title'] }}@endif</h3>
                @if ($item['text'] !== '')<p>{{ $item['text'] }}</p>@endif
            </div>
        </div>
    @endforeach
</div>
@endif
