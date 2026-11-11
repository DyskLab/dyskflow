@php
    $fields = [];
    foreach ($w['fields'] as $field) {
        if ($field['label'] === '' || ! in_array($field['kind'], \App\Support\Forms::FIELD_TYPES, true)) continue;
        $options = in_array($field['kind'], ['select', 'radio'], true) ? \App\Support\Forms::options($field['options']) : [];
        if (in_array($field['kind'], ['select', 'radio'], true) && ! $options) continue;
        $fields[] = $field + ['choices' => $options];
    }
    $id = (string) ($node['id'] ?? 'form');
    $anchor = 'form-'.$id;
    $sealed = $fields ? \App\Support\Forms::seal([
        'id' => $id,
        'anchor' => $anchor,
        'name' => $w['name'] ?: __('Form'),
        'fields' => array_map(fn ($f) => ['label' => $f['label'], 'type' => $f['kind'], 'required' => (bool) $f['required'], 'options' => $f['choices']], $fields),
        'email_to' => $w['email_to'],
        'success' => $w['success'],
        'redirect' => $w['redirect'],
        'url' => url()->current(),
        'page' => \App\Support\Builder\Context::$post?->title ?? '',
        'locale' => app()->getLocale(),
    ]) : '';
    $sent = request()->query('sent') === $id;
@endphp
@if ($fields)
<form class="b-form" id="{{ $anchor }}" method="post" action="{{ route('form.submit') }}" data-b-form data-sending="{{ __('Sending…') }}" data-failed="{{ __('The message could not be sent. Check your connection and try again.') }}">
    <input type="hidden" name="_form" value="{{ $sealed }}">
    <div class="b-form-hp" aria-hidden="true"><label>Website <input type="text" name="{{ \App\Support\Forms::HONEYPOT }}" tabindex="-1" autocomplete="off"></label></div>
    <div class="b-form-status" role="status" aria-live="polite" @unless ($sent) hidden @endunless>@if ($sent){{ $w['success'] ?: __('Thank you. Your message has been sent.') }}@endif</div>
    <div class="b-form-grid">
        @foreach ($fields as $i => $field)
            @php $fid = $anchor.'-f'.$i; $req = $field['required']; @endphp
            <div class="b-form-field b-form-{{ $field['width'] }} b-form-{{ $field['kind'] }}">
                @if ($field['kind'] === 'checkbox')
                    <label class="b-form-check"><input type="checkbox" name="f{{ $i }}" value="1" @if ($req) required @endif> <span>{{ $field['label'] }}@if ($req) <span class="b-form-req" aria-hidden="true">*</span>@endif</span></label>
                @elseif ($field['kind'] === 'radio')
                    <fieldset>
                        <legend>{{ $field['label'] }}@if ($req) <span class="b-form-req" aria-hidden="true">*</span>@endif</legend>
                        @foreach ($field['choices'] as $choice)
                            <label class="b-form-check"><input type="radio" name="f{{ $i }}" value="{{ $choice }}" @if ($req) required @endif> <span>{{ $choice }}</span></label>
                        @endforeach
                    </fieldset>
                @else
                    <label for="{{ $fid }}">{{ $field['label'] }}@if ($req) <span class="b-form-req" aria-hidden="true">*</span>@endif</label>
                    @if ($field['kind'] === 'textarea')
                        <textarea class="textarea" id="{{ $fid }}" name="f{{ $i }}" rows="5" maxlength="5000" placeholder="{{ $field['placeholder'] }}" @if ($req) required @endif></textarea>
                    @elseif ($field['kind'] === 'select')
                        <select class="select" id="{{ $fid }}" name="f{{ $i }}" @if ($req) required @endif>
                            <option value="">{{ $field['placeholder'] ?: __('Choose…') }}</option>
                            @foreach ($field['choices'] as $choice)<option>{{ $choice }}</option>@endforeach
                        </select>
                    @else
                        <input class="input" id="{{ $fid }}" type="{{ $field['kind'] }}" name="f{{ $i }}" placeholder="{{ $field['placeholder'] }}" @if ($field['kind'] === 'email') autocomplete="email" @elseif ($field['kind'] === 'tel') autocomplete="tel" inputmode="tel" @endif maxlength="{{ $field['kind'] === 'number' ? 30 : 300 }}" @if ($req) required @endif>
                    @endif
                @endif
                <p class="b-form-error" hidden></p>
            </div>
        @endforeach
    </div>
    <button class="btn b-btn-lg b-form-submit{{ $w['button_width'] === 'full' ? ' b-form-submit-full' : '' }}" type="submit">{{ $w['submit'] ?: __('Send') }}</button>
    @if ($w['consent'] !== '')<p class="b-form-consent">{{ $w['consent'] }}</p>@endif
</form>
@elseif ($preview)
<p class="b-note">{{ __('Add at least one field. Select and radio fields need choices, one per line.') }}</p>
@endif
