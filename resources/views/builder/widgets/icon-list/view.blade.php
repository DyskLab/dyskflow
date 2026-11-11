@php $items = array_filter($w['items'], fn ($i) => $i['title'] !== '' || $i['text'] !== ''); @endphp
@if ($items)
<{{ $w['marker'] === 'number' ? 'ol' : 'ul' }} class="b-list b-list-{{ $w['marker'] }}">
    @foreach ($items as $item)
        <li>
            @if ($w['marker'] === 'check'){{ icon('check', 18) }}@endif
            <div>
                @if ($item['title'] !== '')<strong>{{ $item['title'] }}</strong>@endif
                @if ($item['text'] !== '')<p>{{ $item['text'] }}</p>@endif
            </div>
        </li>
    @endforeach
</{{ $w['marker'] === 'number' ? 'ol' : 'ul' }}>
@endif
