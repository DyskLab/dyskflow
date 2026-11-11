@php $items = array_values(array_filter($w['items'], fn ($i) => $i['title'] !== '')); @endphp
@if ($items)
<ul class="b-pricelist b-cards {{ \App\Support\Builder\Tokens::classes('cards', $w['columns'], ['1', '2']) }}{{ $w['leader'] ? ' b-pricelist-leader' : '' }}">
    @foreach ($items as $item)
        <li class="b-pricelist-item">
            @if ($item['image'] !== '')<img src="{{ $item['image'] }}" alt="" width="64" height="64" loading="lazy">@endif
            <div class="b-pricelist-body">
                <div class="b-pricelist-head">
                    <strong>@if ($item['url'] !== '')<a href="{{ $item['url'] }}">{{ $item['title'] }}</a>@else{{ $item['title'] }}@endif</strong>
                    <span class="b-pricelist-dots" aria-hidden="true"></span>
                    @if ($item['price'] !== '')<span class="b-pricelist-price">{{ $item['price'] }}</span>@endif
                </div>
                @if ($item['description'] !== '')<p>{{ $item['description'] }}</p>@endif
            </div>
        </li>
    @endforeach
</ul>
@endif
