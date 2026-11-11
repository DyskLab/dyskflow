@if ($w['title'] !== '')
<div class="b-cta b-cta-{{ $w['layout'] }} b-cta-{{ $w['align'] }}{{ $w['image'] === '' ? ' b-cta-noimage' : '' }}" @if ($w['layout'] === 'cover' && $w['image'] !== '') style="--b-cta-image: url('{{ $w['image'] }}')" @endif>
    @if ($w['ribbon'] !== '')<span class="b-cta-ribbon">{{ $w['ribbon'] }}</span>@endif
    @if ($w['layout'] !== 'cover' && $w['image'] !== '')<img class="b-cta-image" src="{{ $w['image'] }}" alt="" loading="lazy" decoding="async">@endif
    <div class="b-cta-body stack-sm">
        @if ($w['kicker'] !== '')<span class="b-kicker">{{ $w['kicker'] }}</span>@endif
        <h3 class="b-heading b-size-lg">{{ $w['title'] }}</h3>
        @if ($w['text'] !== '')<p>{{ $w['text'] }}</p>@endif
        @if (($w['button_label'] !== '' && $w['url'] !== '') || ($w['button2_label'] !== '' && $w['url2'] !== ''))
            <div class="b-buttons">
                @if ($w['button_label'] !== '' && $w['url'] !== '')<a class="btn b-btn-lg" href="{{ $w['url'] }}">{{ $w['button_label'] }}</a>@endif
                @if ($w['button2_label'] !== '' && $w['url2'] !== '')<a class="btn btn-outline b-btn-lg" href="{{ $w['url2'] }}">{{ $w['button2_label'] }}</a>@endif
            </div>
        @endif
    </div>
</div>
@endif
