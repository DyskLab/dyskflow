{{-- Loaded into the right sheet. Vars: title, description, action, fields, values, submit, multipart. --}}
<div class="sheet-header">
    <h2 class="sheet-title">{{ $title }}</h2>
    @isset($description)<p class="muted small">{{ $description }}</p>@endisset
</div>
<form class="sheet-form" method="post" action="{{ $action }}" @if (! empty($multipart)) enctype="multipart/form-data" @endif>
    @csrf
    <div class="sheet-content">
        @foreach ($fields as $field)
            @include('admin.partials.field', ['field' => $field, 'value' => $values[$field['name']] ?? ($field['default'] ?? null)])
        @endforeach
    </div>
    <div class="sheet-footer">
        <button class="btn btn-outline" type="button" data-sheet-close>{{ __('Cancel') }}</button>
        <button class="btn" type="submit">{{ $submit ?? __('Save') }}</button>
    </div>
</form>
