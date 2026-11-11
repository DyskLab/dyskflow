{{-- One form message, loaded into the right sheet. Opening it marks it as read. --}}
<div class="sheet-header">
    <h2 class="sheet-title">{{ $submission->form }}</h2>
    <p class="muted small">{{ cms_date($submission->created_at) }}@if ($submission->page_url) · <a href="{{ $submission->page_url }}" target="_blank" rel="noopener">{{ $submission->page_title ?: $submission->page_url }}</a>@endif</p>
</div>
<div class="sheet-content">
    <dl class="submission">
        @foreach ($submission->data as $answer)
            <div>
                <dt>{{ $answer['label'] }}</dt>
                <dd>{{ $answer['value'] !== '' ? $answer['value'] : '—' }}</dd>
            </div>
        @endforeach
    </dl>
</div>
<div class="sheet-footer">
    <button class="btn btn-outline" type="button" data-sheet-close>{{ __('Close') }}</button>
    @if ($submission->email)<a class="btn" href="mailto:{{ $submission->email }}?subject={{ rawurlencode('Re: '.$submission->form) }}">{{ icon('mail') }} {{ __('Reply by email') }}</a>@endif
</div>
