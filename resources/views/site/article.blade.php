@extends('site.layout')

@section('content')
    <article class="stack">
        <header class="stack-sm">
            <h1 class="site-title">{{ $post->title }}</h1>
            <div class="post-meta">
                {{ cms_date($post->published_at, 'd M Y') }}
                @if ($post->author) · {{ $post->author->name }}@endif
                @if (setting('show_view_count', '1')) · {{ __('Views: :count', ['count' => number_format($post->view_count)]) }}@endif
                @foreach ($post->categories as $category) · <a href="{{ $category->url() }}">{{ $category->name }}</a>@endforeach
            </div>
        </header>
        @if ($post->featured_image)
            @include('site.partials.cover')
        @endif
        <div class="prose">{!! $content !!}</div>
        @if ($post->tags->isNotEmpty())
            <div class="row">
                @foreach ($post->tags as $tag)<a class="badge" style="text-decoration:none" href="{{ $tag->url() }}">{{ $tag->name }}</a>@endforeach
            </div>
        @endif
    </article>

    @if ($comments->isNotEmpty() || $post->commentsOpen())
        <section class="stack" id="comments">
            <h2>{{ __('Comments') }} ({{ $comments->count() }})</h2>
            <div>
                @foreach ($comments as $comment)
                    <div class="comment">
                        <div>
                            <strong>{{ $comment->author_name }}</strong>
                            <span class="post-meta">· {{ cms_date($comment->created_at) }}</span>
                        </div>
                        <p>{{ $comment->content }}</p>
                    </div>
                @endforeach
            </div>

            @if ($post->commentsOpen())
                <form class="stack comment-form" method="post" action="{{ site_route('comment', $post->slug) }}#comments">
                    @csrf
                    <div class="hp-field" aria-hidden="true">
                        <label>Website confirm <input type="text" name="website_confirm" tabindex="-1" autocomplete="off"></label>
                    </div>
                    <div class="field"><label class="label" for="author_name">{{ __('Name') }}</label><input class="input" id="author_name" name="author_name" value="{{ old('author_name') }}" required autocomplete="name" @error('author_name') aria-invalid="true" aria-describedby="error-author_name" @enderror>@error('author_name')<p class="error" id="error-author_name">{{ $message }}</p>@enderror</div>
                    <div class="field"><label class="label" for="author_email">{{ __('Email') }}</label><input class="input" id="author_email" type="email" name="author_email" value="{{ old('author_email') }}" @if (setting('comments_require_email', '1')) required @endif autocomplete="email" spellcheck="false" @error('author_email') aria-invalid="true" aria-describedby="error-author_email" @enderror><p class="help">{{ __('Not shown publicly.') }}</p>@error('author_email')<p class="error" id="error-author_email">{{ $message }}</p>@enderror</div>
                    <div class="field"><label class="label" for="content">{{ __('Comment') }}</label><textarea class="textarea" id="content" name="content" rows="4" required @error('content') aria-invalid="true" aria-describedby="error-content" @enderror>{{ old('content') }}</textarea>@error('content')<p class="error" id="error-content">{{ $message }}</p>@enderror</div>
                    <div><button class="btn" type="submit">{{ __('Send comment') }}</button></div>
                </form>
            @endif
        </section>
    @endif
@endsection
