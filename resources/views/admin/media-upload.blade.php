{{-- Upload sheet: files go up one by one, each with its progress and, when it fails, the reason. --}}
<div class="sheet-header">
    <h2 class="sheet-title">{{ __('Upload files') }}</h2>
    <p class="muted small">{{ __('Allowed: :types', ['types' => implode(', ', $allowed)]) }} · {{ __('Up to :max per file', ['max' => \Illuminate\Support\Number::fileSize($maxBytes)]) }}</p>
</div>
<div class="sheet-content stack-sm" data-uploader data-upload-url="{{ route('admin.media.store') }}" data-allowed="{{ implode(',', $allowed) }}" data-max="{{ $maxBytes }}" data-max-label="{{ \Illuminate\Support\Number::fileSize($maxBytes) }}">
    @csrf
    <label class="dropzone" data-dropzone>
        <input class="sr-only" type="file" multiple data-upload-input aria-label="{{ __('Choose files') }}" accept="{{ collect($allowed)->map(fn ($e) => '.'.$e)->implode(',') }}">
        {{ icon('upload', 24) }}
        <strong>{{ __('Drop files here or click to choose') }}</strong>
        <span class="muted small">{{ __('You can choose several at once.') }}</span>
    </label>
    @if ($limitedByHost)
        <p class="help">{{ __('This hosting limits uploads to :max, less than the limit in Settings > Media.', ['max' => \Illuminate\Support\Number::fileSize($maxBytes)]) }}</p>
    @endif
    <div class="upload-list" data-upload-list aria-live="polite"></div>
</div>
<div class="sheet-footer">
    <button class="btn btn-outline" type="button" data-sheet-close>{{ __('Close') }}</button>
    <button class="btn" type="button" data-upload-done hidden>{{ __('Done') }}</button>
</div>
