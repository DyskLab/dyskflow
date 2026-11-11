@php
    $locale = app()->getLocale();
    $pages = $w['pages'] ? \App\Models\Post::where('type', 'page')->liveIn($locale)->orderBy('title')->limit(100)->get()->each->localize($locale) : collect();
    $categories = $w['categories'] ? \App\Models\Term::where('taxonomy', 'category')->orderBy('name')->limit(100)->get() : collect();
    $articles = (int) $w['articles'] > 0 ? cms_posts((int) $w['articles']) : collect();
@endphp
@if ($pages->isNotEmpty() || $categories->isNotEmpty() || $articles->isNotEmpty())
<div class="b-sitemap b-cards {{ \App\Support\Builder\Tokens::classes('cards', $w['columns'], ['1', '2', '3']) }}">
    @if ($pages->isNotEmpty())<section><h3>{{ __('Pages') }}</h3><ul>@foreach ($pages as $page)<li><a href="{{ $page->url() }}">{{ $page->title }}</a></li>@endforeach</ul></section>@endif
    @if ($categories->isNotEmpty())<section><h3>{{ __('Categories') }}</h3><ul>@foreach ($categories as $category)<li><a href="{{ $category->url() }}">{{ $category->name }}</a></li>@endforeach</ul></section>@endif
    @if ($articles->isNotEmpty())<section><h3>{{ __('Newest articles') }}</h3><ul>@foreach ($articles as $article)<li><a href="{{ $article->url() }}">{{ $article->title }}</a></li>@endforeach</ul></section>@endif
</div>
@endif
