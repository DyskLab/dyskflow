@php
    $post = \App\Support\Builder\Context::$post;
    $comments = \App\Support\Builder\Context::$comments ?? collect();
@endphp
@if ($post && ($comments->isNotEmpty() || $post->commentsOpen()))
<section class="stack" id="comments">
    @if (session('success'))<div class="alert" role="status">{{ session('success') }}</div>@endif
    <h2>{{ __('Comments') }} ({{ $comments->count() }})</h2>
    <div>
        @foreach ($comments as $comment)
            <div class="comment">
                <div><strong>{{ $comment->author_name }}</strong> <span class="post-meta">· {{ cms_date($comment->created_at) }}</span></div>
                <p>{{ $comment->content }}</p>
            </div>
        @endforeach
    </div>
    @if ($post->commentsOpen() && ! \App\Support\Builder\Context::$sample)
        @php $errors = $errors ?? new \Illuminate\Support\ViewErrorBag; @endphp
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
    @elseif ($post->commentsOpen())
        <p class="b-note">{{ __('The comment form shows here on the site.') }}</p>
    @endif
</section>
@elseif ($preview && ! $post)
<p class="b-note">{{ __('Shows the comments and the comment form of the article.') }}</p>
@endif
