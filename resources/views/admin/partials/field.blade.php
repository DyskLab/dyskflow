{{-- Field: name, label, type, required, help, options, placeholder, slug_from, maxlength. Value: $value. --}}
@php
    $type = $field['type'] ?? 'text';
    $name = $field['name'];
    $id = 'f-'.preg_replace('/[^a-z0-9_-]/i', '-', $name);
    $current = old($name, $value ?? ($field['default'] ?? ''));
@endphp
@if ($type === 'switch')
    <label class="switch" for="{{ $id }}">
        <span class="label">{{ $field['label'] }}</span>
        <input type="hidden" name="{{ $name }}" value="0">
        <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="1" @checked((string) $current === '1')>
        <span class="switch-track"></span>
    </label>
    @isset($field['help'])<p class="help">{{ $field['help'] }}</p>@endisset
@else
    <div class="field">
        <label class="label" @if (! in_array($type, ['checkboxes', 'media'])) for="{{ $id }}" @endif>{{ $field['label'] }}@if (! empty($field['required'])) <span class="muted">*</span>@endif</label>
        @switch($type)
            @case('textarea')
                <textarea class="textarea" id="{{ $id }}" name="{{ $name }}" rows="{{ $field['rows'] ?? 3 }}" @if (! empty($field['required'])) required @endif @isset($field['placeholder']) placeholder="{{ $field['placeholder'] }}" @endisset>{{ $current }}</textarea>
                @break
            @case('select')
                <select class="select" id="{{ $id }}" name="{{ $name }}">
                    @foreach ($field['options'] as $optionValue => $optionLabel)
                        <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
                    @endforeach
                </select>
                @break
            @case('checkboxes')
                @php $selected = is_array($current) ? array_map('strval', $current) : array_filter(explode(',', (string) $current)); @endphp
                <input type="hidden" name="{{ $name }}[]" value="">
                <div class="check-grid">
                    @foreach ($field['options'] as $optionValue => $optionLabel)
                        <label class="check-row">
                            <input class="checkbox" type="checkbox" name="{{ $name }}[]" value="{{ $optionValue }}" @checked(in_array((string) $optionValue, $selected, true))>
                            <span>{{ $optionLabel }}</span>
                        </label>
                    @endforeach
                </div>
                @break
            @case('media')
                <div class="media-field" data-media-field>
                    <img class="media-preview" src="{{ cms_asset_url($current) }}" alt="" width="40" height="40" @if (! $current) hidden @endif>
                    <input class="input" type="text" name="{{ $name }}" value="{{ $current }}" aria-label="{{ $field['label'] }}" placeholder="uploads/…">
                    <button class="btn btn-outline" type="button" data-media-pick>{{ __('Choose') }}</button>
                    <button class="btn btn-ghost btn-icon" type="button" data-media-clear aria-label="{{ __('Clear') }}">{{ icon('x') }}</button>
                </div>
                @break
            @case('secret')
                <input class="input" id="{{ $id }}" type="password" name="{{ $name }}" value="" autocomplete="new-password" placeholder="{{ $value ? '••••••••' : '' }}">
                @break
            @case('password')
                <input class="input" id="{{ $id }}" type="password" name="{{ $name }}" value="" autocomplete="new-password" @if (! empty($field['required'])) required @endif>
                @break
            @default
                <input class="input" id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $current }}"
                    @if (! empty($field['required'])) required @endif
                    @isset($field['placeholder']) placeholder="{{ $field['placeholder'] }}" @endisset
                    @if ($type === 'email' || isset($field['slug_from'])) spellcheck="false" @endif
                    @isset($field['slug_from']) data-slug-from="{{ $field['slug_from'] }}" autocomplete="off" @endisset
                    @isset($field['maxlength']) maxlength="{{ $field['maxlength'] }}" @endisset>
        @endswitch
        @isset($field['help'])<p class="help">{{ $field['help'] }}</p>@endisset
    </div>
@endif
