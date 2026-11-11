@if ($w['image'] !== '' || $w['title'] !== '')
<div class="b-imagebox b-imagebox-{{ $w['position'] }} b-ratio-{{ str_replace(':', '-', $w['ratio']) }}">
    @if ($w['image'] !== '')<img src="{{ $w['image'] }}" alt="{{ $w['alt'] }}" loading="lazy" decoding="async">@endif
    <div class="b-imagebox-body stack-sm">
        @if ($w['kicker'] !== '')<span class="b-kicker">{{ $w['kicker'] }}</span>@endif
        @if ($w['title'] !== '')<h3 class="b-heading b-size-md">{{ $w['title'] }}</h3>@endif
        @if ($w['text'] !== '')<p>{{ $w['text'] }}</p>@endif
        @if ($w['button_label'] !== '' && $w['url'] !== '')<div class="b-buttons"><a class="btn b-btn-md" href="{{ $w['url'] }}">{{ $w['button_label'] }}</a></div>@endif
    </div>
</div>
@endif
