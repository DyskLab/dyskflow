@if ($w['title'] !== '' || $w['text'] !== '')
<div class="b-alert b-alert-{{ $w['kind'] }}" role="{{ in_array($w['kind'], ['warning', 'danger'], true) ? 'alert' : 'note' }}" data-b-alert>
    <span class="b-alert-icon" aria-hidden="true">{{ icon($w['kind'] === 'success' ? 'check' : 'alert', 18) }}</span>
    <div>
        @if ($w['title'] !== '')<strong>{{ $w['title'] }}</strong>@endif
        @if ($w['text'] !== '')<p>{{ $w['text'] }}</p>@endif
    </div>
    @if ($w['dismissible'])<button type="button" class="b-alert-close" data-b-alert-close aria-label="{{ __('Close') }}">{{ icon('x', 16) }}</button>@endif
</div>
@endif
