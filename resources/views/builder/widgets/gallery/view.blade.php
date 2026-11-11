@php $images = array_filter($w['images'], fn ($i) => $i['src'] !== ''); @endphp
@if ($images)
<div class="b-gallery b-ratio-{{ str_replace(':', '-', $w['ratio']) }} {{ \App\Support\Builder\Tokens::classes('cards', $w['columns'], ['2', '3', '4']) }}">
    @foreach ($images as $image)
        <figure>
            @if ($w['open'])<a href="{{ $image['src'] }}" target="_blank" rel="noopener">@endif
            <img src="{{ $image['src'] }}" alt="{{ $image['alt'] }}" loading="lazy" decoding="async">
            @if ($w['open'])</a>@endif
            @if ($image['caption'] !== '')<figcaption>{{ $image['caption'] }}</figcaption>@endif
        </figure>
    @endforeach
</div>
@endif
