@php
    $post = \App\Support\Builder\Context::$post;
    $locale = app()->getLocale();
    $related = $post ? \App\Models\Post::where('type', 'article')->liveIn($locale)->whereKeyNot($post->id)
        ->whereHas('categories', fn ($q) => $q->whereIn('terms.id', $post->categories->pluck('id')))
        ->orderByDesc('published_at')->limit((int) $w['count'])->get()->each->localize($locale) : collect();
@endphp
@if ($related->isNotEmpty())
<div class="b-cards {{ \App\Support\Builder\Tokens::classes('cards', $w['columns'], ['1', '2', '3']) }}">
    @foreach ($related as $item)
        <article class="b-card">
            @if ($item->featured_image)<img class="b-card-img" src="{{ cms_asset_url($item->featured_image) }}" alt="" loading="lazy" decoding="async">@endif
            <span class="b-kicker">{{ cms_date($item->published_at, 'd M Y') }}</span>
            <h3><a href="{{ $item->url() }}">{{ $item->title }}</a></h3>
            @if ($w['show_excerpt'])<p>{{ $item->excerpt ?: cms_excerpt($item->content, 120) }}</p>@endif
        </article>
    @endforeach
</div>
@elseif ($preview)
<p class="b-note">{{ __('Shows other articles in the same categories. Nothing when there are none.') }}</p>
@endif
