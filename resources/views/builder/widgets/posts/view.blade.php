@php $posts = cms_posts((int) $w['count'], $w['category'] ?: null); @endphp
@if ($posts->isNotEmpty())
<div class="b-cards {{ \App\Support\Builder\Tokens::classes('cards', $w['columns'], ['1', '2', '3']) }}">
    @foreach ($posts as $post)
        <article class="b-card">
            @if ($post->featured_image)<img class="b-card-img" src="{{ cms_asset_url($post->featured_image) }}" alt="" loading="lazy" decoding="async">@endif
            <span class="b-kicker">{{ cms_date($post->published_at, 'd M Y') }}</span>
            <h3><a href="{{ $post->url() }}">{{ $post->title }}</a></h3>
            @if ($w['show_excerpt'])<p>{{ $post->excerpt ?: cms_excerpt($post->content, 120) }}</p>@endif
        </article>
    @endforeach
</div>
@elseif ($preview)
<p class="b-note">{{ __('No published articles yet.') }}</p>
@endif
