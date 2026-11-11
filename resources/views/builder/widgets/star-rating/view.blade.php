@php $rating = max(0, min(5, (float) $w['rating'])); @endphp
<div class="b-stars-wrap b-stars-{{ $w['size'] }}">
    <span class="b-stars" style="--b-rating: {{ $rating }}" role="img" aria-label="{{ __(':rating out of 5 stars', ['rating' => rtrim(rtrim(number_format($rating, 1), '0'), '.')]) }}"></span>
    @if ($w['show_number'])<strong>{{ rtrim(rtrim(number_format($rating, 1), '0'), '.') }}</strong>@endif
    @if ($w['label'] !== '')<span class="b-stars-label">{{ $w['label'] }}</span>@endif
</div>
