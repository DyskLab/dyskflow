@extends('site.layout')

@section('content')
    @if ($heading)
        <div class="stack-sm">
            <h1 class="site-title">{{ $heading }}</h1>
            @if (! empty($intro))<p class="muted site-lead">{{ $intro }}</p>@endif
        </div>
    @endif

    <div class="post-list">
        @forelse ($posts as $post)
            <article class="post-item">
                <div class="post-text">
                    <div class="post-meta">
                        {{ cms_date($post->published_at, 'd M Y') }}
                        @foreach ($post->categories as $category) · <a href="{{ $category->url() }}">{{ $category->name }}</a>@endforeach
                    </div>
                    <h2><a href="{{ $post->url() }}">{{ $post->title }}</a></h2>
                    <p class="muted">{{ $post->excerpt ?: cms_excerpt($post->content, 180) }}</p>
                </div>
                @if ($post->featured_image)
                    <a class="post-thumb" href="{{ $post->url() }}" tabindex="-1" aria-hidden="true"><img src="{{ cms_asset_url($post->featured_image) }}" alt="" loading="lazy"></a>
                @endif
            </article>
        @empty
            <p class="muted">{{ __('No articles found.') }}</p>
        @endforelse
    </div>

    @if ($posts->hasPages())
        <nav class="site-pagination" aria-label="{{ __('Page navigation') }}">
            @if ($posts->previousPageUrl())<a class="btn btn-outline" href="{{ $posts->previousPageUrl() }}" rel="prev">{{ __('Newer') }}</a>@else<span></span>@endif
            @if ($posts->nextPageUrl())<a class="btn btn-outline" href="{{ $posts->nextPageUrl() }}" rel="next">{{ __('Older') }}</a>@endif
        </nav>
    @endif
@endsection
