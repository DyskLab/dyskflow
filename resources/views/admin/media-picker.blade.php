<div class="sheet-header">
    <h2 class="sheet-title">{{ __('Choose an image') }}</h2>
</div>
<div class="sheet-content">
    <form method="post" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="stack-sm" data-media-upload>
        @csrf
        <div class="row" style="flex-wrap:nowrap">
            <input class="input" type="file" name="files[]" accept="image/*" required aria-label="{{ __('Files') }}">
            <button class="btn btn-outline" type="submit">{{ __('Upload') }}</button>
        </div>
        <p class="help" data-upload-status aria-live="polite"></p>
    </form>
    <div class="media-grid">
        @forelse ($items as $item)
            <button class="media-item" type="button" data-media-choose data-path="{{ $item->path }}" data-url="{{ $item->url() }}" data-alt="{{ $item->alt }}" title="{{ $item->title }}">
                <img src="{{ $item->url() }}" alt="{{ $item->alt }}" loading="lazy">
            </button>
        @empty
            <p class="muted">{{ __('No images yet. Upload one above.') }}</p>
        @endforelse
    </div>
</div>
