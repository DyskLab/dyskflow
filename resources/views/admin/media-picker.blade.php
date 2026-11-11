{{-- Image picker (opens wide): search, upload by dropping or choosing, then click an image. --}}
<div class="sheet-header">
    <h2 class="sheet-title">{{ __('Choose an image') }}</h2>
    <p class="muted small">{{ __('Click an image to use it, or upload a new one.') }} {{ __('Up to :max per file', ['max' => \Illuminate\Support\Number::fileSize($maxBytes)]) }}</p>
</div>
<div class="sheet-content picker" data-picker data-uploader data-upload-url="{{ route('admin.media.store') }}" data-allowed="{{ implode(',', $allowed) }}" data-max="{{ $maxBytes }}" data-max-label="{{ \Illuminate\Support\Number::fileSize($maxBytes) }}">
    @csrf
    <div class="picker-tools">
        <input class="input" type="search" placeholder="{{ __('Search images…') }}" aria-label="{{ __('Search images') }}" data-picker-filter @if ($items->isEmpty()) hidden @endif>
        <label class="btn btn-outline picker-upload">
            {{ icon('upload') }} {{ __('Upload') }}
            <input class="sr-only" type="file" multiple accept="{{ collect($allowed)->map(fn ($e) => '.'.$e)->implode(',') }}" data-upload-input>
        </label>
    </div>
    <div class="upload-list" data-upload-list aria-live="polite"></div>
    <div class="picker-grid" data-picker-grid data-dropzone>
        @foreach ($items as $item)
            <button class="picker-item {{ $current !== '' && $current === $item->path ? 'is-current' : '' }}" type="button" data-media-choose
                data-path="{{ $item->path }}" data-url="{{ $item->url() }}" data-alt="{{ $item->alt }}" data-title="{{ mb_strtolower($item->title.' '.$item->filename.' '.$item->alt) }}"
                title="{{ $item->title }}" @if ($current !== '' && $current === $item->path) aria-current="true" @endif>
                <span class="picker-thumb"><img src="{{ $item->url() }}" alt="" loading="lazy" decoding="async" @if (str_ends_with($item->path, '.svg')) class="is-svg" @endif></span>
                <span class="picker-name">{{ $item->title ?: $item->filename }}</span>
                <span class="picker-meta">{{ $item->width && $item->height ? $item->width.'×'.$item->height : \Illuminate\Support\Number::fileSize($item->size) }}</span>
            </button>
        @endforeach
        <div class="picker-empty" data-picker-empty @if ($items->isNotEmpty()) hidden @endif>
            {{ icon('image', 28) }}
            <strong>{{ __('No images yet') }}</strong>
            <span class="muted small">{{ __('Drop images here, or press Upload.') }}</span>
        </div>
    </div>
    <p class="muted small" data-picker-none hidden>{{ __('No images match.') }}</p>
    <div class="picker-drop-hint" aria-hidden="true">{{ icon('upload', 28) }}<strong>{{ __('Drop to upload') }}</strong></div>
</div>
