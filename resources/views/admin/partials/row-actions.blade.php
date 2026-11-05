{{-- Each action: label, icon, and one of url / sheet / post. Optional: confirm, danger, fields, blank, wide. --}}
<div class="row-actions">
    @foreach ($actions as $action)
        @continue(! $action)
        @if (isset($action['sheet']))
            <button class="btn btn-ghost btn-icon btn-sm" type="button" data-sheet-url="{{ $action['sheet'] }}" @if (! empty($action['wide'])) data-sheet-wide @endif title="{{ $action['label'] }}" aria-label="{{ $action['label'] }}">{{ icon($action['icon']) }}</button>
        @elseif (isset($action['post']))
            <button class="btn btn-ghost btn-icon btn-sm {{ ! empty($action['danger']) ? 'btn-icon-danger' : '' }}" type="button"
                data-post-url="{{ $action['post'] }}"
                @if (! empty($action['fields'])) data-post-fields="{{ json_encode($action['fields']) }}" @endif
                @if (! empty($action['confirm'])) data-confirm="{{ $action['confirm'] }}" @endif
                @if (! empty($action['danger'])) data-danger @endif
                title="{{ $action['label'] }}" aria-label="{{ $action['label'] }}">{{ icon($action['icon']) }}</button>
        @else
            <a class="btn btn-ghost btn-icon btn-sm" href="{{ $action['url'] }}" @if (! empty($action['blank'])) target="_blank" rel="noopener" @endif title="{{ $action['label'] }}" aria-label="{{ $action['label'] }}">{{ icon($action['icon']) }}</a>
        @endif
    @endforeach
</div>
