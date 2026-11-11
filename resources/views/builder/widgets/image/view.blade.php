@if ($w['src'] !== '')
@php
    $img = '<img src="'.e($w['src']).'" alt="'.e($w['alt']).'"'.($w['width'] ? ' width="'.(int) $w['width'].'"' : '').($w['height'] ? ' height="'.(int) $w['height'].'"' : '').($w['lazy'] ? ' loading="lazy" decoding="async"' : '').'>';
@endphp
<figure class="b-image b-ratio-{{ str_replace(':', '-', $w['ratio']) }}{{ $w['rounded'] ? ' b-rounded' : '' }}">
    @if ($w['url'] !== '')<a href="{{ $w['url'] }}">{!! $img !!}</a>@else{!! $img !!}@endif
    @if ($w['caption'] !== '')<figcaption>{{ $w['caption'] }}</figcaption>@endif
</figure>
@endif
