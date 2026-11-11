@extends('admin.layout')

@php
    $cm = fn (string $file) => asset_v('vendor/codemirror/'.$file);
    $labels = ['blade' => 'Blade', 'css' => 'CSS', 'js' => 'JavaScript', 'json' => 'JSON', 'php' => 'PHP', 'xml' => 'XML / SVG', 'markdown' => 'Markdown', 'text' => __('Text')];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ $cm('lib/codemirror.css') }}">
    <link rel="stylesheet" href="{{ $cm('addon/dialog/dialog.css') }}">
    <link rel="stylesheet" href="{{ asset_v('css/code-editor.css') }}">
@endpush

@section('content')
    <div class="code-editor" data-code-editor
        data-save-url="{{ route('admin.design.files.save') }}"
        data-path="{{ $path }}"
        data-hash="{{ $hash }}"
        data-language="{{ $language }}"
        data-i18n="{{ json_encode([
            'saving' => __('Saving…'),
            'saved' => __('Saved.'),
            'unsaved' => __('Unsaved changes'),
            'failed' => __('Could not save. Check your connection and try again.'),
            'expired' => __('Your session expired. Copy your changes, then reload the page.'),
            'restored' => __('Your unsaved changes were restored.'),
            'line' => __('Line'),
            'column' => __('Col'),
            'goto' => __('Go to line:'),
        ]) }}">
        <div class="code-toolbar">
            <div class="code-title">
                @include('admin.design.path', ['crumbs' => [...$crumbs, ['label' => basename($path), 'url' => null]]])
                <span class="badge badge-muted">{{ $labels[$language] ?? $language }}</span>
                <span class="code-state muted small" data-code-state aria-live="polite"></span>
            </div>
            <div class="row">
                @if ($viewUrl)
                    <a class="btn btn-outline btn-sm" href="{{ $viewUrl }}" target="_blank" rel="noopener">{{ icon('external') }} {{ __('View') }}</a>
                @endif
                <a class="btn btn-outline btn-sm" href="{{ route('admin.design.files.revisions', ['path' => $path]) }}">{{ icon('restore') }} {{ __('Revisions') }}</a>
                <button class="btn btn-sm" type="button" data-code-save>{{ __('Save') }} <kbd class="code-kbd" data-code-kbd>Ctrl S</kbd></button>
            </div>
        </div>

        <div class="alert alert-danger code-message" role="alert" data-code-message hidden>
            <span data-code-message-text></span>
            <span class="row" data-code-conflict hidden>
                <button class="btn btn-outline btn-sm" type="button" data-code-reload>{{ __('Load the version on disk') }}</button>
                <button class="btn btn-danger btn-sm" type="button" data-code-overwrite>{{ __('Overwrite with mine') }}</button>
            </span>
            <a class="btn btn-outline btn-sm" href="#" data-code-confirm hidden>{{ __('Confirm and continue') }}</a>
        </div>

        @if ($unchecked)
            <div class="alert alert-warning" role="status">{{ __('This server cannot run php -l, so syntax is not checked before saving. A mistake can break the page until it is fixed.') }}</div>
        @endif

        <div class="code-area">
            <label class="sr-only" for="code-content">{{ __('Content of :name', ['name' => basename($path)]) }}</label>
            <textarea id="code-content" spellcheck="false">{{ $content }}</textarea>
        </div>

        <div class="code-status muted small">
            <span data-code-cursor>{{ __('Line') }} 1, {{ __('Col') }} 1</span>
            <span class="mono">{{ $path }}</span>
            <span data-code-size>{{ \Illuminate\Support\Number::fileSize($size) }}</span>
            <span>{{ __('Ctrl F to search, Ctrl G to go to a line') }}</span>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ $cm('lib/codemirror.min.js') }}"></script>
    @foreach (['addon/mode/overlay.js', 'addon/dialog/dialog.js', 'addon/search/searchcursor.js', 'addon/search/search.js', 'addon/edit/matchbrackets.js', 'addon/edit/closebrackets.js', 'addon/selection/active-line.js', 'mode/xml/xml.js', 'mode/javascript/javascript.js', 'mode/css/css.js', 'mode/htmlmixed/htmlmixed.js', 'mode/clike/clike.js', 'mode/php/php.js'] as $file)
        <script src="{{ $cm($file) }}"></script>
    @endforeach
    <script src="{{ asset_v('js/code-editor.js') }}"></script>
@endpush
