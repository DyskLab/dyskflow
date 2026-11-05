<div class="sheet-header">
    <h2 class="sheet-title">{{ __('Upload files') }}</h2>
    <p class="muted small">{{ __('Allowed: :types', ['types' => implode(', ', $allowed)]) }}</p>
</div>
<form class="sheet-form" method="post" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="sheet-content">
        <input class="input" type="file" name="files[]" multiple required aria-label="{{ __('Files') }}">
    </div>
    <div class="sheet-footer">
        <button class="btn btn-outline" type="button" data-sheet-close>{{ __('Cancel') }}</button>
        <button class="btn" type="submit">{{ __('Upload') }}</button>
    </div>
</form>
