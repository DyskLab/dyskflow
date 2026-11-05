{{-- A header button. Action: label, icon, secondary, and one of url / sheet / post, with optional fields, confirm. --}}
@php $class = 'btn '.(! empty($action['secondary']) ? 'btn-outline' : ''); @endphp
@if (isset($action['sheet']))
    <button class="{{ $class }}" type="button" data-sheet-url="{{ $action['sheet'] }}">{{ icon($action['icon'] ?? 'plus') }} {{ $action['label'] }}</button>
@elseif (isset($action['post']))
    <button class="{{ $class }}" type="button" data-post-url="{{ $action['post'] }}"
        @if (! empty($action['fields'])) data-post-fields="{{ json_encode($action['fields']) }}" @endif
        @if (! empty($action['confirm'])) data-confirm="{{ $action['confirm'] }}" @endif>{{ icon($action['icon'] ?? 'plus') }} {{ $action['label'] }}</button>
@else
    <a class="{{ $class }}" href="{{ $action['url'] }}">{{ icon($action['icon'] ?? 'plus') }} {{ $action['label'] }}</a>
@endif
