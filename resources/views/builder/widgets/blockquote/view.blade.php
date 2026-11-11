@if ($w['quote'] !== '')
<figure class="b-blockquote b-blockquote-{{ $w['variant'] }} b-blockquote-{{ $w['size'] }}">
    <blockquote><p>{{ $w['quote'] }}</p></blockquote>
    @if ($w['author'] !== '' || $w['role'] !== '')
        <figcaption>
            @if ($w['image'] !== '')<img src="{{ $w['image'] }}" alt="" width="44" height="44" loading="lazy">@endif
            <span><strong>{{ $w['author'] }}</strong>@if ($w['role'] !== '')<span>{{ $w['role'] }}</span>@endif</span>
        </figcaption>
    @endif
</figure>
@endif
