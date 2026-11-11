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
            @case('starters')
                {{-- Starting points as cards, each with a live thumbnail of the page it makes. --}}
                <div class="starter-grid" role="radiogroup" aria-label="{{ $field['label'] }}">
                    @foreach ($field['options'] as $optionValue => $card)
                        <label class="starter">
                            <input type="radio" name="{{ $name }}" value="{{ $optionValue }}" @checked((string) $current === (string) $optionValue)>
                            <span class="starter-thumb" aria-hidden="true"><iframe src="{{ $card['preview'] }}" loading="lazy" tabindex="-1" sandbox="" title=""></iframe></span>
                            <span class="starter-text">
                                <strong>{{ $card['title'] }}</strong>
                                @if ($card['description'] !== '')<span>{{ $card['description'] }}</span>@endif
                            </span>
                            <a class="starter-open" href="{{ $card['preview'] }}" target="_blank" rel="noopener">{{ icon('external', 14) }} {{ __('Preview') }}</a>
                        </label>
                    @endforeach
                </div>
                @break
            @case('checkboxes')
                {{-- Options can be grouped: groups = [[label, options => [value => label or [label, description, danger]]]]. --}}
                @php
                    $selected = is_array($current) ? array_map('strval', $current) : array_filter(explode(',', (string) $current));
                    $rich = isset($field['groups']);
                    $groups = $field['groups'] ?? [['label' => null, 'options' => $field['options']]];
                    $total = collect($groups)->sum(fn ($g) => count($g['options']));
                    $picked = collect($groups)->flatMap(fn ($g) => array_keys($g['options']))->filter(fn ($v) => in_array((string) $v, $selected, true))->count();
                @endphp
                <input type="hidden" name="{{ $name }}[]" value="">
                <div class="check-panel {{ $rich ? '' : 'check-panel-compact' }}" data-check-panel role="group" aria-label="{{ $field['label'] }}">
                    @if ($rich)
                        <div class="check-panel-head">
                            @if ($total > 10)
                                <input class="input check-filter" type="search" placeholder="{{ __('Filter…') }}" aria-label="{{ __('Filter') }}" data-check-filter>
                            @endif
                            <span class="muted small check-count" data-check-count data-text="{{ __(':count of :total selected') }}">{{ __(':count of :total selected', ['count' => $picked, 'total' => $total]) }}</span>
                        </div>
                    @endif
                    @foreach ($groups as $group)
                        @php $allOn = $group['options'] && collect(array_keys($group['options']))->every(fn ($v) => in_array((string) $v, $selected, true)); @endphp
                        <div class="check-group" data-check-group>
                            @if ($group['label'] ?? null)
                                <div class="check-group-head">
                                    <span>{{ $group['label'] }}</span>
                                    <button type="button" class="check-group-all" data-check-group-all data-select="{{ __('Select all') }}" data-clear="{{ __('Clear') }}">{{ $allOn ? __('Clear') : __('Select all') }}</button>
                                </div>
                            @endif
                            <div class="check-options">
                                @foreach ($group['options'] as $optionValue => $option)
                                    @php $option = is_array($option) ? $option : ['label' => $option]; @endphp
                                    <label class="check-option" data-check-text="{{ mb_strtolower($option['label'].' '.($option['description'] ?? '')) }}">
                                        <input class="checkbox" type="checkbox" name="{{ $name }}[]" value="{{ $optionValue }}" @checked(in_array((string) $optionValue, $selected, true))>
                                        <span class="check-option-text">
                                            <span class="check-option-label">{{ $option['label'] }}@if (! empty($option['danger'])) <span class="badge badge-warning">{{ __('Sensitive') }}</span>@endif</span>
                                            @if (! empty($option['description']))<span class="check-option-desc">{{ $option['description'] }}</span>@endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    @if ($rich && $total > 10)<p class="muted small check-none" data-check-none hidden>{{ __('Nothing matches.') }}</p>@endif
                </div>
                @break
            @case('color')
                {{-- A color picker and the hex code side by side, kept in sync. Empty means the theme default. --}}
                <div class="color-field" data-color-field>
                    <input type="color" value="{{ preg_match('/^#[0-9a-f]{6}$/i', (string) $current) ? $current : ($field['placeholder'] ?? '#000000') }}" aria-label="{{ $field['label'] }}" data-color-picker @if ((string) $current === '') data-empty @endif>
                    <input class="input mono" id="{{ $id }}" name="{{ $name }}" value="{{ $current }}" maxlength="7" placeholder="{{ $field['placeholder'] ?? '' }}" pattern="#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})" data-color-text>
                    <button type="button" class="btn btn-ghost btn-sm" data-color-clear @if ((string) $current === '') hidden @endif>{{ __('Default') }}</button>
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
