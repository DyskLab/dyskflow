@if ($w['code'] !== '')
<div class="b-code b-code-{{ $w['theme'] }}{{ $w['line_numbers'] ? ' b-code-lines' : '' }}{{ $w['wrap'] ? ' b-code-wrap' : '' }}">
    @if ($w['language'] !== '' || $w['copy'])
        <div class="b-code-bar">
            <span>{{ $w['language'] }}</span>
            @if ($w['copy'])<button type="button" data-b-copy-code data-copied="{{ __('Copied') }}">{{ icon('copy', 14) }} {{ __('Copy') }}</button>@endif
        </div>
    @endif
    <pre tabindex="0"><code>@foreach (preg_split('/\R/', rtrim($w['code'])) as $line)<span class="b-code-line">{{ $line }}</span>@endforeach</code></pre>
</div>
@endif
