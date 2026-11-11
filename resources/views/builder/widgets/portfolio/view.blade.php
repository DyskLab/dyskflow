@php
    $posts = cms_posts((int) $w['count'], $w['category'] ?: null)->filter(fn ($p) => $p->featured_image);
    $cats = $posts->flatMap(fn ($p) => $p->categories)->unique('id')->sortBy('name');
@endphp
@if ($posts->isNotEmpty())
<div class="b-portfolio" data-b-portfolio>
    @if ($w['filter'] && $cats->count() > 1)
        <div class="b-portfolio-filter" role="group" aria-label="{{ __('Filter by category') }}">
            <button type="button" aria-pressed="true" data-b-filter="">{{ __('All') }}</button>
            @foreach ($cats as $cat)<button type="button" aria-pressed="false" data-b-filter="{{ $cat->id }}">{{ $cat->name }}</button>@endforeach
        </div>
    @endif
    <div class="b-gallery b-ratio-{{ str_replace(':', '-', $w['ratio']) }} {{ \App\Support\Builder\Tokens::classes('cards', $w['columns'], ['2', '3', '4']) }}">
        @foreach ($posts as $post)
            <a class="b-portfolio-item" href="{{ $post->url() }}" data-b-cats="{{ $post->categories->pluck('id')->implode(' ') }}">
                <img src="{{ cms_asset_url($post->featured_image) }}" alt="" loading="lazy" decoding="async">
                @if ($w['show_title'])<span class="b-portfolio-title">{{ $post->title }}</span>@else<span class="sr-only">{{ $post->title }}</span>@endif
            </a>
        @endforeach
    </div>
</div>
@elseif ($preview)
<p class="b-note">{{ __('Shows published articles that have a featured image.') }}</p>
@endif
