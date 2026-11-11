@php $cards = array_filter($w['cards'], fn ($c) => $c['title'] !== '' || $c['text'] !== '' || $c['image'] !== ''); @endphp
@if ($cards)
<div class="b-cards {{ \App\Support\Builder\Tokens::classes('cards', $w['columns'], ['1', '2', '3', '4']) }}">
    @foreach ($cards as $card)
        <article class="b-card">
            @if ($card['image'] !== '')<img class="b-card-img" src="{{ $card['image'] }}" alt="" loading="lazy" decoding="async">@endif
            @if ($card['kicker'] !== '')<span class="b-kicker">{{ $card['kicker'] }}</span>@endif
            @if ($card['title'] !== '')<h3>@if ($card['url'] !== '')<a href="{{ $card['url'] }}">{{ $card['title'] }}</a>@else{{ $card['title'] }}@endif</h3>@endif
            @if ($card['text'] !== '')<p>{{ $card['text'] }}</p>@endif
        </article>
    @endforeach
</div>
@endif
