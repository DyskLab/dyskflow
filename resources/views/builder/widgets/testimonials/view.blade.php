@php $items = array_filter($w['items'], fn ($i) => $i['quote'] !== ''); @endphp
@if ($items)
<div class="b-cards {{ \App\Support\Builder\Tokens::classes('cards', $w['columns'], ['1', '2', '3']) }}">
    @foreach ($items as $item)
        <figure class="b-card b-quote">
            <blockquote>{{ $item['quote'] }}</blockquote>
            <figcaption>
                @if ($item['image'] !== '')<img src="{{ $item['image'] }}" alt="" width="40" height="40" loading="lazy" decoding="async">@endif
                <span><strong>{{ $item['name'] }}</strong>@if ($item['role'] !== '')<small>{{ $item['role'] }}</small>@endif</span>
            </figcaption>
        </figure>
    @endforeach
</div>
@endif
