@php
    $crumbs = [[$w['home'] ?: __('Home'), site_route('home')]];
    $post = \App\Support\Builder\Context::$post;
    $term = \App\Support\Builder\Context::$term;
    if ($post && $post->type === 'article') {
        $crumbs[] = [__('Blog'), site_route('blog')];
        if ($category = $post->categories->first()) $crumbs[] = [$category->name, $category->url()];
        if ($w['current']) $crumbs[] = [$post->title, null];
    } elseif ($term) {
        if ($w['current']) $crumbs[] = [$term->name, null];
    } elseif (\App\Support\Builder\Context::$heading && $w['current']) {
        $crumbs[] = [\App\Support\Builder\Context::$heading, null];
    }
    $schema = $w['schema'] && count($crumbs) > 1 ? json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_map(fn ($c, $i) => array_filter(['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]]), $crumbs, array_keys($crumbs))], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) : null;
@endphp
@if (count($crumbs) > 1)
<nav class="b-breadcrumbs" aria-label="{{ __('Breadcrumb') }}">
    <ol>
        @foreach ($crumbs as [$name, $href])
            <li>@if ($href)<a href="{{ $href }}">{{ $name }}</a>@else<span aria-current="page">{{ $name }}</span>@endif</li>
        @endforeach
    </ol>
</nav>
@if ($schema)<script type="application/ld+json">{!! $schema !!}</script>@endif
@elseif ($preview)
<p class="b-note">{{ __('Shows the path to the current article or category. Use it in the article or category template.') }}</p>
@endif
