@extends('admin.layout')

@section('content')
    <form method="post" action="{{ route("$base.translation.save", $original) }}" data-dirty-guard>
        @csrf
        <div class="page-head" style="margin-bottom:16px">
            <div>
                <h1 class="page-title">{{ __('Translate to :language', ['language' => \App\Support\Locales::name($locale)]) }}</h1>
            </div>
            <div class="row">
                @if ($post->exists)
                    <button class="btn btn-outline" type="button" data-post-url="{{ route("$base.translation.delete", $original) }}" data-confirm="{{ __('Delete this translation? This cannot be undone.') }}" data-danger>{{ icon('trash') }} {{ __('Delete') }}</button>
                    <a class="btn btn-outline" href="{{ $viewUrl }}" target="_blank" rel="noopener">{{ icon('external') }} {{ __('View') }}</a>
                @endif
                <button class="btn" type="submit">{{ __('Save') }}</button>
            </div>
        </div>

        <div class="editor-grid">
            <div class="stack">
                <div class="field">
                    <label class="label" for="title">{{ __('Title') }}</label>
                    <input class="input title-input" id="title" name="title" lang="{{ $locale }}" value="{{ old('title', $post->title) }}" required>
                </div>
                <div class="field">
                    <label class="label" for="slug">{{ __('Slug') }}</label>
                    <input class="input" id="slug" name="slug" value="{{ old('slug', $post->slug) }}" data-slug-from="title" autocomplete="off" spellcheck="false">
                </div>
                @if ($original->isBuilder())
                    <div class="card">
                        <div class="card-content stack-sm">
                            <strong>{{ __('This page is designed with the builder') }}</strong>
                            <p class="muted small">{{ __('Its text in each language is edited in the builder: choose the language at the top of the builder. Set the title, address and SEO for this language here.') }}</p>
                            <div class="row"><a class="btn btn-outline" href="{{ route('admin.pages.builder.edit', $original) }}">{{ icon('landing') }} {{ __('Edit with the builder') }}</a></div>
                        </div>
                    </div>
                @else
                    <div class="field">
                        <span class="label">{{ __('Content') }}</span>
                        @include('admin.partials.editor', ['value' => $post->content])
                    </div>
                @endif
                <div class="field">
                    <label class="label" for="excerpt">{{ __('Excerpt') }}</label>
                    <textarea class="textarea" id="excerpt" name="excerpt" rows="2">{{ old('excerpt', $post->excerpt) }}</textarea>
                </div>
                @include('admin.partials.post-seo', ['seoBase' => \App\Support\Locales::url($type === 'article' ? 'blog' : '', $locale), 'translation' => true])
            </div>

            <div class="editor-side">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">{{ __('Publish') }}</h2></div>
                    <div class="card-content stack">
                        @include('admin.partials.field', ['field' => ['name' => 'status', 'label' => __('Status'), 'type' => 'select', 'options' => ['draft' => __('Draft'), 'published' => __('Published')], 'help' => __('Visible only while the original is also published. The publish date, images, categories and comments come from the original.')], 'value' => $post->status])
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h2 class="card-title">{{ __('Original') }}</h2></div>
                    <div class="card-content stack-sm">
                        <a href="{{ route("$base.edit", $original) }}" style="font-weight:500;text-decoration:none">{{ $original->title }}</a>
                        @if ($original->excerpt)<p class="muted small">{{ $original->excerpt }}</p>@endif
                        <div class="prose small" lang="{{ \App\Support\Locales::default() }}" style="max-height:360px;overflow:auto">{!! $original->content !!}</div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
