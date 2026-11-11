@php $tag = $w['url'] !== '' ? 'a' : 'span'; @endphp
<{{ $tag }} class="b-icon b-icon-{{ $w['size'] }} b-icon-{{ $w['variant'] }}"@if ($w['url'] !== '') href="{{ $w['url'] }}"@endif @if ($w['label'] !== '') aria-label="{{ $w['label'] }}" @if ($tag === 'span') role="img" @endif @endif>{{ icon($w['icon'], 24) }}</{{ $tag }}>
