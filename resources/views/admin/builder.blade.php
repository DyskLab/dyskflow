@extends('admin.layout')

@php
    // The editor changes often between releases: the file time keeps browsers from using an old copy.
    $version = fn (string $file) => is_file(public_path($file)) ? filemtime(public_path($file)) : config('cms.version');
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/builder-editor.css') }}?v={{ $version('css/builder-editor.css') }}">
@endpush

@php
    $icons = collect(['plus', 'x', 'copy', 'trash', 'download', 'arrow-up', 'arrow-down', 'grip', 'chevron-right', 'alert', 'page', 'landing', 'heading', 'text', 'link', 'image', 'spacer', 'divider', 'list', 'card', 'posts', 'code', 'video', 'columns', 'check'])->merge(collect($boot['widgets'])->pluck('icon'))->unique()
        ->mapWithKeys(fn ($name) => [$name => (string) icon($name)]);
    $i18n = [
        'section' => __('Section'), 'column' => __('Column'), 'innerSection' => __('Inner section'), 'container' => __('Container'),
        'sizeSpacing' => __('Size and spacing'), 'invalidSize' => __('Use a size such as 24px, 50%, 2rem or 100vh.'), 'boxHelp' => __('One to four sizes, like CSS: top, right, bottom, left. For example 24px or 16px 32px.'),
        'containerInColumn' => __('A container cannot go inside a grid column. Select a container or the page.'), 'innerInContainer' => __('Use a container set to Row instead of an inner section here.'),
        'tooDeep' => __('Containers can be nested up to :n levels.'), 'emptyContainerHint' => __('Add widgets from the left panel, or a Container to nest one.'),
        'rowHint' => __('In a row, inner containers without a width share the space. Set Direction for Mobile to Column to stack them on phones.'),
        'box' => [
            'direction' => __('Direction'), 'justify' => __('Justify content'), 'align' => __('Align items'), 'wrap' => __('Wrap'), 'gap' => __('Gap'),
            'width' => __('Width'), 'min_height' => __('Minimum height'), 'content_width' => __('Content width'), 'boxed_width' => __('Boxed width'),
            'padding' => __('Padding'), 'margin' => __('Margin'), 'background' => __('Background'), 'background_color' => __('Background color'),
            'image' => __('Background image'), 'overlay' => __('Overlay'), 'color' => __('Text color'), 'radius' => __('Corner radius'),
            'border_width' => __('Border width'), 'border_color' => __('Border color'), 'shadow' => __('Shadow'), 'overflow' => __('Overflow'),
            'tag' => __('HTML tag'), 'align_self' => __('Align self'),
        ],
        'options' => [
            'plain' => __('Plain'),
            'filled' => __('Filled'),
            'outline' => __('Outline'),
            'top' => __('Top'),
            'bottom' => __('Bottom'),
            'left' => __('Left'),
            'right' => __('Right'),
            'settings' => __('From Settings'),
            'custom' => __('My own list'),
            'circle' => __('Circle'),
            'square' => __('Square'),
            'rotate' => __('Rotate'),
            'typing' => __('Typing'),
            'highlight' => __('Underline'),
            'lines' => __('Lines'),
            'boxed' => __('Boxed'),
            'pills' => __('Pills'),
            'info' => __('Information'),
            'success' => __('Success'),
            'warning' => __('Warning'),
            'danger' => __('Danger'),
            'line' => __('Line on the left'),
            'mark' => __('Quote mark'),
            'card' => __('Card'),
            'cover' => __('Text over the image'),
            'flip' => __('Flip'),
            'fade' => __('Fade'),
            'slide' => __('Slide up'),
            'boxes' => __('Boxes'),
            'side' => __('Beside the video'),
            'below' => __('Below the video'),
            'icon' => __('Icon'),
            'text' => __('Text'),
            'none' => __('None'),
            'messaging' => __('Messaging'),
            'social' => __('Social media'),
            'all' => __('All'),
            'dark' => __('Dark'),
            'light' => __('Light'),
            'number' => __('Number'),
            'plus' => __('Plus'),
            'dot' => __('Dot'),
            'auto' => __('Automatic'),
            'half' => __('Half width'),
            'full' => __('Full width'),
            'email' => __('Email'),
            'tel' => __('Phone'),
            'textarea' => __('Long text'),
            'select' => __('Dropdown'),
            'radio' => __('Choice buttons'),
            'checkbox' => __('Checkbox'),
            'date' => __('Date'),
            'screen' => __('Full screen'),
            'medium' => __('Medium'),
            'strong' => __('Strong'),
            'start' => __('Start'),
            'center' => __('Center'),
            'h2-h3' => __('h2 and h3'),
            'h2-h4' => __('h2 to h4'),
            'website' => __('Website'),
            'foreground' => __('Text color'),
            'primary' => __('Primary color'),
            'brand-secondary' => __('Secondary color'),
            'brand-accent' => __('Accent color'),
        ],
        'values' => [
            'column' => __('Column'), 'row' => __('Row'), 'start' => __('Start'), 'center' => __('Center'), 'end' => __('End'),
            'between' => __('Space between'), 'around' => __('Space around'), 'evenly' => __('Space evenly'), 'stretch' => __('Stretch'),
            'nowrap' => __('No wrap'), 'wrap' => __('Wrap'), 'boxed' => __('Boxed'), 'full' => __('Full width'),
            'default' => __('Default'), 'muted' => __('Muted'), 'primary' => __('Primary'), 'dark' => __('Dark'), 'image' => __('Image'),
            'none' => __('None'), 'light' => __('Light'), 'sm' => __('Small'), 'md' => __('Medium'), 'lg' => __('Large'),
            'visible' => __('Visible'), 'hidden' => __('Hidden'),
        ],
        'content' => __('Content'), 'style' => __('Style'), 'advanced' => __('Advanced'),
        'saving' => __('Saving…'), 'saved' => __('Draft saved.'), 'unsaved' => __('Unsaved changes'), 'published' => __('Published.'),
        'failed' => __('Could not save. Check your connection and try again.'), 'expired' => __('Your session expired. Reload the page; your last saved draft is kept.'),
        'updated' => __('Updated with changes made elsewhere.'), 'conflict' => __('The page was changed elsewhere, for example by an AI tool or in another tab.'),
        'nothing' => __('Select an element in the preview or the navigator to edit it.'),
        'empty' => __('This page is empty. Add a section to begin.'),
        'moveUp' => __('Move up'), 'moveDown' => __('Move down'), 'duplicate' => __('Duplicate'), 'delete' => __('Delete'),
        'saveBlock' => __('Save as block'), 'blockName' => __('Block name'), 'save' => __('Save'), 'cancel' => __('Cancel'),
        'savedBlocks' => __('Saved blocks'), 'noBlocks' => __('None yet. Select a container and press "Save as block" to reuse it.'), 'insertBlock' => __('Insert a copy of this block'),
        'addItem' => __('Add item'), 'item' => __('Item'), 'translate' => __('Translate separately'),
        'same' => __('Same text in every language.'), 'missing' => __('No text in this language yet: it shows nothing.'),
        'inherit' => __('Same as larger screens'), 'desktop' => __('Desktop'), 'tablet' => __('Tablet'), 'mobile' => __('Mobile'),
        'hideOn' => __('Hide on'), 'anchor' => __('Anchor (HTML id)'), 'anchorHelp' => __('Lowercase letters, digits and "-". Link to it with #name.'),
        'class' => __('CSS classes'), 'classHelp' => __('Class names from your theme CSS.'), 'id' => __('Id'),
        'width' => __('Width (out of 12)'), 'addColumn' => __('Add column'), 'addWidgetHere' => __('Add a widget from the left panel.'),
        'errors' => __('Problems'), 'html' => __('HTML: p, h2–h4, strong, em, a, ul, ol, li, blockquote, img. Scripts and styles are removed.'),
        'choose' => __('Choose'), 'none' => __('None'), 'publishFirst' => __('Fix these problems before publishing:'),
        'draft' => __('Draft'), 'publishedBadge' => __('Published'),
        'groups' => ['site' => __('Site'), 'template' => __('Template'), 'layout' => __('Layout'), 'basic' => __('Basic'), 'media' => __('Media'), 'content' => __('Content'), 'advanced' => __('Advanced'), 'custom' => __('Custom')],
        'page' => __('Page'), 'layout' => __('Layout'), 'layoutSite' => __('With the site header and footer'), 'layoutBlank' => __('Without header and footer'),
        'css' => __('Custom CSS'), 'cssHelp' => __('For this page only. Give an element a class or an anchor in its Advanced tab, then style it here, for example .promo { … }.'),
        'js' => __('Custom JavaScript'), 'jsHelp' => __('For this page only, at the end of the page. It runs on the site, not in this preview: publish and press View to try it.'),
        'jsLocked' => __('Adding or changing JavaScript needs permission to edit code.'),
        'onlyColumn' => __('A section needs at least one column. Delete the section instead.'), 'noNesting' => __('An inner section cannot hold another inner section.'),
        'mediaPicker' => auth()->user()->can('media.manage'),
        'upload' => __('Upload'), 'uploading' => __('Uploading…'), 'uploadHelp' => __('Saved in the media library.'),
        'movedTo' => __(':item moved: :n of :total in :parent.'),
        'needsCode' => __('Adding or changing HTML needs permission to edit code. You can still move, copy or delete it.'),
        'defaults' => ['heading' => __('Your heading'), 'text' => __('Write your text here.'), 'button' => __('Button'), 'item' => __('Item'), 'card' => __('Card title'), 'cardText' => __('A short description.'),
            'question' => __('Question'), 'answer' => __('The answer to this question.'), 'quote' => __('What a customer says about you.'), 'name' => __('Customer name'),
            'basic' => __('Basic'), 'pro' => __('Pro'), 'business' => __('Business'), 'price' => 'Rp 99.000', 'period' => __('/month'), 'features' => __("First feature\nSecond feature\nThird feature"), 'choose' => __('Choose plan'),
            'statA' => __('Years of experience'), 'statB' => __('Customers'), 'statC' => __('Satisfied')],
        'image' => __('Background image'), 'imageHelp' => __('uploads/… from the media library or https://…'),
        'tokens' => [
            'padding' => __('Padding'), 'gap' => __('Gap'), 'background' => __('Background'), 'overlay' => __('Overlay'),
            'width' => __('Content width'), 'valign' => __('Vertical alignment'), 'text' => __('Text alignment'),
            'align' => __('Alignment'), 'margin' => __('Space above'),
        ],
    ];
@endphp

{{-- Full screen: admin.layout stays for the sheet, the confirm modal, the media picker and toasts; CSS hides the rest. --}}
@section('body_class', 'bld-full')

@section('content')
<div class="bld" data-builder
    data-spec-url="{{ $url('spec') }}"
    data-draft-url="{{ $url('draft') }}"
    data-render-url="{{ $url('render') }}"
    data-publish-url="{{ $url('publish') }}"
    data-upload-url="{{ $url('upload') }}"
    data-locale="{{ $defaultLocale }}">
    <script type="application/json" data-builder-boot>@json($boot)</script>
    <script type="application/json" data-builder-i18n>@json($i18n)</script>
    <script type="application/json" data-builder-icons>@json($icons)</script>

    {{-- The top bar: what is being edited and its state on the left, the preview controls in the middle, actions on the right. --}}
    <div class="bld-bar">
        <div class="bld-title">
            <a class="btn btn-ghost btn-sm btn-icon bld-exit" href="{{ $exitUrl }}" data-bld-exit title="{{ $template ? __('Close the builder and go back to the templates') : __('Close the builder and go back to the page: title, address, SEO and translation') }}" aria-label="{{ __('Exit') }}">{{ icon('x') }}</a>
            <img class="bld-logo" src="{{ asset('img/logo-mark.svg') }}" alt="" width="24" height="24">
            <div class="bld-name">
                <strong title="{{ $post->title }}">{{ $post->title }}</strong>
                <span class="bld-meta">
                    <span class="badge badge-muted" data-bld-status>{{ $boot['draft'] || ! $boot['live'] ? __('Draft') : __('Published') }}</span>
                    <span class="bld-state" data-bld-state aria-live="polite"></span>
                </span>
            </div>
        </div>

        <div class="bld-view">
            <div class="seg bld-devices" role="group" aria-label="{{ __('Screen size') }}">
                <button type="button" class="seg-btn seg-icon" data-bld-device="desktop" aria-pressed="true" title="{{ __('Desktop') }}" aria-label="{{ __('Desktop') }}">{{ icon('monitor') }}</button>
                <button type="button" class="seg-btn seg-icon" data-bld-device="tablet" aria-pressed="false" title="{{ __('Tablet') }}" aria-label="{{ __('Tablet') }}">{{ icon('tablet') }}</button>
                <button type="button" class="seg-btn seg-icon" data-bld-device="mobile" aria-pressed="false" title="{{ __('Mobile') }}" aria-label="{{ __('Mobile') }}">{{ icon('smartphone') }}</button>
            </div>
            <div class="seg" role="group" aria-label="{{ __('Language') }}" @if (count($locales) < 2) hidden @endif>
                @foreach ($locales as $locale)
                    <button type="button" class="seg-btn" data-bld-locale="{{ $locale['code'] }}" aria-pressed="{{ $locale['code'] === $defaultLocale ? 'true' : 'false' }}" title="{{ $locale['name'] }}">{{ strtoupper($locale['code']) }}</button>
                @endforeach
            </div>
        </div>

        <div class="bld-tools">
            <span class="bld-history">
                <button type="button" class="btn btn-ghost btn-sm btn-icon" data-bld-undo disabled title="{{ __('Undo') }} (Ctrl Z)" aria-label="{{ __('Undo') }}">{{ icon('undo') }}</button>
                <button type="button" class="btn btn-ghost btn-sm btn-icon" data-bld-redo disabled title="{{ __('Redo') }} (Ctrl Shift Z)" aria-label="{{ __('Redo') }}">{{ icon('redo') }}</button>
            </span>
            <div class="seg bld-panels" role="group" aria-label="{{ __('Panels') }}">
                <button type="button" class="seg-btn bld-panel-btn" data-bld-panel="left" aria-pressed="true" aria-controls="bld-left" title="{{ __('Show or hide the left panel') }}" aria-label="{{ __('Left panel') }}">{{ icon('panel') }}</button>
                <button type="button" class="seg-btn bld-panel-btn bld-panel-right" data-bld-panel="right" aria-pressed="true" aria-controls="bld-right" title="{{ __('Show or hide the right panel') }}" aria-label="{{ __('Right panel') }}">{{ icon('panel') }}</button>
            </div>
            <div class="bld-more" data-bld-more>
                <button type="button" class="btn btn-ghost btn-sm btn-icon" data-bld-more-toggle aria-haspopup="menu" aria-expanded="false" aria-controls="bld-more-menu" title="{{ __('More') }}" aria-label="{{ __('More') }}">{{ icon('more') }}</button>
                <div class="bld-menu" id="bld-more-menu" role="menu" hidden>
                    <a class="bld-menu-item" role="menuitem" data-bld-view href="{{ $boot['viewUrl'] ?? '#' }}" target="_blank" rel="noopener" @if (! $boot['viewUrl']) hidden @endif>{{ icon('external') }} {{ __('View page') }}</a>
                    @if ($post->type === 'page')
                        <a class="bld-menu-item" role="menuitem" href="{{ route('admin.pages.edit', $post) }}">{{ icon('settings') }} {{ __('Page settings') }} <small>{{ __('Title, address, SEO, translation') }}</small></a>
                    @elseif ($post->type === 'template')
                        <button type="button" class="bld-menu-item" role="menuitem" data-sheet-url="{{ route('admin.design.templates.conditions', $post) }}">{{ icon('settings') }} {{ __('Conditions') }} <small>{{ __('Where this template is used') }}</small></button>
                    @endif
                    <a class="bld-menu-item" role="menuitem" href="{{ $url('revisions') }}">{{ icon('restore') }} {{ __('Revisions') }}</a>
                    <div class="bld-menu-sep" role="separator"></div>
                    <button type="button" class="bld-menu-item danger" role="menuitem" data-bld-discard data-post-url="{{ $url('discard') }}" data-confirm="{{ __('Remove all unpublished changes? The draft is kept as a revision.') }}" data-danger @if (! $boot['draft']) hidden @endif>{{ icon('trash') }} {{ __('Discard draft') }}</button>
                    <p class="bld-menu-note">{{ __('Changes are saved as a draft automatically. Visitors see them after Publish.') }}</p>
                </div>
            </div>
            <button type="button" class="btn btn-sm bld-publish" data-bld-publish>{{ __('Publish') }}</button>
        </div>
    </div>

    <div class="alert alert-warning bld-message" role="alert" data-bld-message hidden>
        <div data-bld-message-text></div>
        <span class="row" data-bld-conflict hidden>
            <button class="btn btn-outline btn-sm" type="button" data-bld-reload>{{ __('Load the saved version') }}</button>
            <button class="btn btn-danger btn-sm" type="button" data-bld-overwrite>{{ __('Overwrite with mine') }}</button>
        </span>
    </div>

    <div class="bld-body">
        <aside class="bld-left" id="bld-left" aria-label="{{ __('Add and navigate') }}">
            <div class="bld-tabs" role="tablist">
                <button type="button" role="tab" class="bld-tab" data-bld-left="add" aria-selected="true">{{ __('Add') }}</button>
                <button type="button" role="tab" class="bld-tab" data-bld-left="tree" aria-selected="false">{{ __('Navigator') }}</button>
            </div>
            <div class="bld-pane" data-bld-pane="add">
                <div class="bld-search">
                    {{ icon('search', 16) }}
                    <input type="search" class="input" data-bld-search-input placeholder="{{ __('Search widgets…') }}" aria-label="{{ __('Search widgets') }}" autocomplete="off">
                </div>
                <p class="bld-hint bld-search-empty" data-bld-search-empty hidden>{{ __('No widget matches.') }}</p>
                <details class="bld-group" data-bld-group="structure" open>
                <summary>{{ __('Container') }}</summary>
                <div class="bld-presets">
                    @foreach (['1' => '1', '1-1' => '½ ½', '1-1-1' => '⅓ ⅓ ⅓', '1-2' => '⅓ ⅔', '2-1' => '⅔ ⅓', '1-1-1-1' => '¼ ×4'] as $preset => $label)
                        <button type="button" class="bld-preset" draggable="true" data-bld-container="{{ $preset }}" title="{{ __('Add a flexible container: inner containers in a row, stacked on phones') }}">
                            <span class="bld-preset-cols">@foreach (explode('-', $preset) as $w)<i style="flex: {{ $w }}"></i>@endforeach</span>
                            <span class="small">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
                </details>
                <details class="bld-group" data-bld-group="grid">
                <summary>{{ __('Grid section') }}</summary>
                <div class="bld-presets">
                    @foreach (['12' => '1', '6-6' => '½ ½', '4-4-4' => '⅓ ⅓ ⅓', '4-8' => '⅓ ⅔', '8-4' => '⅔ ⅓', '3-3-3-3' => '¼ ×4'] as $preset => $label)
                        <button type="button" class="bld-preset" draggable="true" data-bld-section="{{ $preset }}" title="{{ __('Add a section with these columns') }}">
                            <span class="bld-preset-cols">@foreach (explode('-', $preset) as $w)<i style="flex: {{ $w }}"></i>@endforeach</span>
                            <span class="small">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
                </details>
                <div data-bld-palette></div>
            </div>
            <div class="bld-pane" data-bld-pane="tree" hidden>
                <div class="bld-tree" data-bld-tree role="tree" aria-label="{{ __('Navigator') }}" aria-describedby="bld-tree-help"></div>
                <p class="bld-hint" id="bld-tree-help">{{ __('Drag to move, or select and press Alt + ↑ / ↓.') }}</p>
            </div>
            <div class="bld-problems" data-bld-problems hidden></div>
        </aside>

        <div class="bld-stage" data-bld-stage>
            <iframe class="bld-frame" data-bld-frame title="{{ __('Preview') }}"></iframe>
        </div>

        <aside class="bld-right" id="bld-right" aria-label="{{ __('Edit element') }}">
            <div data-bld-inspector></div>
        </aside>
        <div class="bld-scrim" data-bld-mobile="preview" aria-hidden="true"></div>
    </div>

    {{-- Phones: the panels open as sheets over the preview, one at a time. --}}
    <nav class="bld-mobile-tabs" aria-label="{{ __('Builder panels') }}">
        <button type="button" data-bld-mobile="add" aria-pressed="false">{{ icon('plus') }}<span>{{ __('Add') }}</span></button>
        <button type="button" data-bld-mobile="tree" aria-pressed="false">{{ icon('layers') }}<span>{{ __('Navigator') }}</span></button>
        <button type="button" data-bld-mobile="preview" aria-pressed="true">{{ icon('smartphone') }}<span>{{ __('Preview') }}</span></button>
        <button type="button" data-bld-mobile="edit" aria-pressed="false">{{ icon('settings') }}<span>{{ __('Edit') }}</span></button>
    </nav>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/builder-editor.js') }}?v={{ $version('js/builder-editor.js') }}"></script>
@endpush
