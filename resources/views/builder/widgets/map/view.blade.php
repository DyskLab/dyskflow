@php
    $place = trim(preg_replace('/\s*\R\s*/', ', ', $w['address'] !== '' ? $w['address'] : (string) setting('contact_address')));
    $embed = 'https://www.google.com/maps?'.http_build_query(['q' => $place, 'z' => (int) $w['zoom'], 'output' => 'embed'], '', '&', PHP_QUERY_RFC3986);
    $open = 'https://www.google.com/maps/search/?'.http_build_query(['api' => 1, 'query' => $place], '', '&', PHP_QUERY_RFC3986);
@endphp
@if ($place !== '')
<div class="b-map b-map-{{ $w['height'] }}" data-b-video="{{ $embed }}" data-b-title="{{ $w['title'] ?: $place }}">
    <div class="b-map-cover">
        <span class="b-map-place">{{ icon('globe', 18) }} {{ $place }}</span>
        <span class="b-map-actions">
            <button type="button" class="btn b-map-load">{{ __('Show map') }}</button>
            <a class="btn btn-outline" href="{{ $open }}" target="_blank" rel="noopener">{{ __('Open in Google Maps') }}</a>
        </span>
        <small>{{ __('The map is loaded from Google when you press the button.') }}</small>
    </div>
</div>
@elseif ($preview)
<p class="b-note">{{ __('Fill in an address, or the address in Settings > Contact.') }}</p>
@endif
