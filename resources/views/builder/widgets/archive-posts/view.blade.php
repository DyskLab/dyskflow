@php $posts = \App\Support\Builder\Context::$posts; @endphp
@if ($posts)
<div class="post-list b-post-list {{ \App\Support\Builder\Tokens::classes('cards', $w['columns'], ['1', '2', '3', '4']) }}">
    @forelse ($posts as $post)
        <article class="post-item">
            <div class="post-text">
                @if ($w['meta'])
                    <div class="post-meta">{{ cms_date($post->published_at, 'd M Y') }}@foreach ($post->categories as $category) · <a href="{{ $category->url() }}">{{ $category->name }}</a>@endforeach</div>
                @endif
                <h2><a href="{{ $post->url() }}">{{ $post->title }}</a></h2>
                @if ($w['excerpt'])<p class="muted">{{ $post->excerpt ?: cms_excerpt($post->content, 180) }}</p>@endif
            </div>
            @if ($w['image'] && $post->featured_image)
                <a class="post-thumb" href="{{ $post->url() }}" tabindex="-1" aria-hidden="true"><img src="{{ cms_asset_url($post->featured_image) }}" alt="" loading="lazy"></a>
            @endif
        </article>
    @empty
        <p class="muted">{{ __('No articles found.') }}</p>
    @endforelse
</div>
@elseif ($preview)
<p class="b-note">{{ __('Shows the articles of the list. Use it in the article list or category template.') }}</p>
@endif
