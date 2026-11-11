@php
    $post = \App\Support\Builder\Context::$post;
    $prev = $next = null;
    if ($post && $post->type === 'article' && $post->exists && $post->published_at) {
        $locale = app()->getLocale();
        $category = $w['same_category'] ? $post->categories->first() : null;
        $base = fn () => \App\Models\Post::where('type', 'article')->liveIn($locale)->whereKeyNot($post->id)
            ->when($category, fn ($q) => $q->whereHas('categories', fn ($t) => $t->whereKey($category->id)));
        $prev = $base()->where('published_at', '<', $post->published_at)->orderByDesc('published_at')->first()?->localize($locale);
        $next = $base()->where('published_at', '>', $post->published_at)->orderBy('published_at')->first()?->localize($locale);
    }
@endphp
@if ($prev || $next)
<nav class="b-postnav" aria-label="{{ __('More articles') }}">
    @if ($prev)<a class="b-postnav-prev" href="{{ $prev->url() }}" rel="prev">@if ($w['labels'])<small>← {{ __('Previous') }}</small>@endif<span>{{ $prev->title }}</span></a>@else<span></span>@endif
    @if ($next)<a class="b-postnav-next" href="{{ $next->url() }}" rel="next">@if ($w['labels'])<small>{{ __('Next') }} →</small>@endif<span>{{ $next->title }}</span></a>@endif
</nav>
@elseif ($preview)
<p class="b-note">{{ __('Links to the previous and next article. Use it in the single article template.') }}</p>
@endif
