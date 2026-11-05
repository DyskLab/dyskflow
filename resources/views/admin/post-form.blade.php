@extends('admin.layout')

@php
    $isArticle = $type === 'article';
    $tz = setting('timezone', 'Asia/Jakarta');
    $publishedLocal = old('published_at', $post->published_at?->copy()->setTimezone($tz)->format('Y-m-d\TH:i'));
@endphp

@section('content')
    <form method="post" action="{{ $post->exists ? route("$base.update", $post) : route("$base.store") }}" data-dirty-guard>
        @csrf
        <div class="page-head" style="margin-bottom:16px">
            <div>
                <h1 class="page-title">{{ $post->exists ? __('Edit') : ($isArticle ? __('New article') : __('New page')) }}</h1>
            </div>
            <div class="row">
                @if ($post->exists)<a class="btn btn-outline" href="{{ $post->url() }}" target="_blank" rel="noopener">{{ icon('external') }} {{ __('View') }}</a>@endif
                <button class="btn" type="submit">{{ __('Save') }}</button>
            </div>
        </div>

        <div class="editor-grid">
            <div class="stack">
                <div class="field">
                    <label class="label" for="title">{{ __('Title') }}</label>
                    <input class="input title-input" id="title" name="title" value="{{ old('title', $post->title) }}" required>
                </div>
                <div class="field">
                    <label class="label" for="slug">{{ __('Slug') }}</label>
                    <input class="input" id="slug" name="slug" value="{{ old('slug', $post->slug) }}" data-slug-from="title" autocomplete="off" spellcheck="false">
                </div>

                <div class="field">
                    <span class="label">{{ __('Content') }}</span>
                    @if (! $isArticle)<p class="help">{{ __('Not shown when this page uses a landing page.') }}</p>@endif
                    @include('admin.partials.editor', ['value' => $post->content])
                </div>

                <div class="field">
                    <label class="label" for="excerpt">{{ __('Excerpt') }}</label>
                    <textarea class="textarea" id="excerpt" name="excerpt" rows="2">{{ old('excerpt', $post->excerpt) }}</textarea>
                </div>

                @include('admin.partials.post-seo', ['seoBase' => url($isArticle ? 'blog' : '')])
            </div>

            <div class="editor-side">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">{{ __('Publish') }}</h2></div>
                    <div class="card-content stack">
                        @include('admin.partials.field', ['field' => ['name' => 'status', 'label' => __('Status'), 'type' => 'select', 'options' => ['draft' => __('Draft'), 'published' => __('Published')]], 'value' => $post->status])
                        <div class="field">
                            <label class="label" for="published_at">{{ __('Publish date') }}</label>
                            <input class="input" id="published_at" type="datetime-local" name="published_at" value="{{ $publishedLocal }}">
                            <p class="help">{{ __('A future date schedules it. Timezone: :tz', ['tz' => $tz]) }}</p>
                        </div>
                    </div>
                </div>

                @if ($post->exists)
                    @php $postTranslation = $post->translations->firstWhere('locale', \App\Support\Locales::second()); @endphp
                    <div class="card">
                        <div class="card-header"><h2 class="card-title">{{ __('Translation') }}</h2></div>
                        <div class="card-content stack-sm">
                            <div class="row between">
                                <span>{{ \App\Support\Locales::name(\App\Support\Locales::second()) }}</span>
                                @if (! $postTranslation)<span class="badge badge-muted">{{ __('Not translated') }}</span>
                                @elseif ($postTranslation->status === 'published')<span class="badge badge-success">{{ __('Published') }}</span>
                                @else<span class="badge badge-muted">{{ __('Draft') }}</span>@endif
                            </div>
                            <a class="btn btn-outline" href="{{ route("$base.translation", $post) }}">{{ icon('languages') }} {{ $postTranslation ? __('Edit translation') : __('Add translation') }}</a>
                        </div>
                    </div>
                @endif

                @if ($isArticle)
                    <div class="card">
                        <div class="card-header"><h2 class="card-title">{{ __('Categories') }}</h2></div>
                        <div class="card-content">
                            <div class="check-list">
                                @forelse ($categories as $category)
                                    <label class="check-row">
                                        <input class="checkbox" type="checkbox" name="categories[]" value="{{ $category->id }}" @checked(in_array($category->id, old('categories', $selectedCategories)))>
                                        <span>{{ $category->name }}</span>
                                    </label>
                                @empty
                                    <p class="muted small">{{ __('No categories yet.') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h2 class="card-title">{{ __('Tags') }}</h2></div>
                        <div class="card-content">
                            <input class="input" name="tags" value="{{ old('tags', $tags) }}" aria-label="{{ __('Tags') }}">
                            <p class="help" style="margin-top:6px">{{ __('Separate with commas. New tags are created automatically.') }}</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-content">
                            @include('admin.partials.field', ['field' => ['name' => 'allow_comments', 'label' => __('Allow comments'), 'type' => 'switch'], 'value' => $post->allow_comments ? '1' : '0'])
                        </div>
                    </div>
                @else
                    <div class="card">
                        <div class="card-header"><h2 class="card-title">{{ __('Landing page') }}</h2></div>
                        <div class="card-content stack-sm">
                            <select class="select" name="landing" aria-label="{{ __('Landing page') }}">
                                <option value="">{{ __('None: use the content above') }}</option>
                                @foreach ($landings as $slug => $name)
                                    <option value="{{ $slug }}" @selected(old('landing', $post->landing) === $slug)>{{ $name }}</option>
                                @endforeach
                            </select>
                            <p class="help">{{ __('Landing pages are coded separately in the landing folder. SEO fields here still apply.') }}</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h2 class="card-title">{{ __('Menu') }}</h2></div>
                        <div class="card-content stack">
                            @include('admin.partials.field', ['field' => ['name' => 'show_in_menu', 'label' => __('Show in site menu'), 'type' => 'switch'], 'value' => $post->show_in_menu ? '1' : '0'])
                            @include('admin.partials.field', ['field' => ['name' => 'menu_order', 'label' => __('Menu order'), 'type' => 'number'], 'value' => $post->menu_order ?? 0])
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header"><h2 class="card-title">{{ __('Featured image') }}</h2></div>
                    <div class="card-content">
                        @include('admin.partials.field', ['field' => ['name' => 'featured_image', 'label' => __('Image'), 'type' => 'media'], 'value' => $post->featured_image])
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
