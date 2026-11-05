{{-- The rich text editor. Submits as "content". Var: value. --}}
<div class="editor" data-editor>
    <div class="editor-toolbar" role="toolbar" aria-label="{{ __('Formatting') }}">
        <button class="btn btn-ghost btn-sm" type="button" data-cmd="bold" title="{{ __('Bold') }}" aria-label="{{ __('Bold') }}">{{ icon('bold') }}</button>
        <button class="btn btn-ghost btn-sm" type="button" data-cmd="italic" title="{{ __('Italic') }}" aria-label="{{ __('Italic') }}">{{ icon('italic') }}</button>
        <button class="btn btn-ghost btn-sm text" type="button" data-cmd="formatBlock" data-value="h2">H2</button>
        <button class="btn btn-ghost btn-sm text" type="button" data-cmd="formatBlock" data-value="h3">H3</button>
        <button class="btn btn-ghost btn-sm text" type="button" data-cmd="formatBlock" data-value="p">P</button>
        <button class="btn btn-ghost btn-sm" type="button" data-cmd="insertUnorderedList" title="{{ __('List') }}" aria-label="{{ __('List') }}">{{ icon('list') }}</button>
        <button class="btn btn-ghost btn-sm" type="button" data-cmd="insertOrderedList" title="{{ __('Numbered list') }}" aria-label="{{ __('Numbered list') }}">{{ icon('ordered') }}</button>
        <button class="btn btn-ghost btn-sm" type="button" data-cmd="formatBlock" data-value="blockquote" title="{{ __('Quote') }}" aria-label="{{ __('Quote') }}">{{ icon('quote') }}</button>
        <button class="btn btn-ghost btn-sm" type="button" data-cmd="link" title="{{ __('Link') }}" aria-label="{{ __('Link') }}">{{ icon('link') }}</button>
        <button class="btn btn-ghost btn-sm" type="button" data-cmd="image" title="{{ __('Image') }}" aria-label="{{ __('Image') }}">{{ icon('image') }}</button>
        <button class="btn btn-ghost btn-sm" type="button" data-cmd="source" title="{{ __('HTML source') }}" aria-label="{{ __('HTML source') }}">{{ icon('code') }}</button>
    </div>
    <div class="row" data-link-row hidden style="padding:8px;border-bottom:1px solid var(--border);flex-wrap:nowrap">
        <input class="input" type="url" placeholder="https://" aria-label="URL">
        <button class="btn btn-sm" type="button">{{ __('Apply') }}</button>
    </div>
    <div class="editor-area prose" contenteditable="true" role="textbox" aria-multiline="true" aria-label="{{ __('Content') }}">{!! old('content', $value) !!}</div>
    <textarea class="editor-source" hidden aria-label="{{ __('HTML source') }}"></textarea>
    <input type="hidden" name="content" data-editor-value value="{{ old('content', $value) }}">
</div>
