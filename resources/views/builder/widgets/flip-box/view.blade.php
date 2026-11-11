@if ($w['front_title'] !== '')
<div class="b-flip b-flip-{{ $w['effect'] }} b-flip-{{ $w['height'] }}" tabindex="0" data-b-flip>
    <div class="b-flip-inner">
        <div class="b-flip-front{{ $w['front_image'] !== '' ? ' has-image' : '' }}" @if ($w['front_image'] !== '') style="--b-flip-image: url('{{ $w['front_image'] }}')" @endif>
            <span class="b-icon b-icon-lg b-icon-plain" aria-hidden="true">{{ icon($w['icon'], 24) }}</span>
            <h3>{{ $w['front_title'] }}</h3>
            @if ($w['front_text'] !== '')<p>{{ $w['front_text'] }}</p>@endif
        </div>
        <div class="b-flip-back">
            @if ($w['back_title'] !== '')<h3>{{ $w['back_title'] }}</h3>@endif
            @if ($w['back_text'] !== '')<p>{{ $w['back_text'] }}</p>@endif
            @if ($w['button_label'] !== '' && $w['url'] !== '')<a class="btn b-btn-md" href="{{ $w['url'] }}">{{ $w['button_label'] }}</a>@endif
        </div>
    </div>
</div>
@endif
