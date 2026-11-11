@php
    $post = \App\Support\Builder\Context::$post;
    $parts = [];
    if ($post) {
        if ($w['date'] && $post->published_at) $parts[] = e(cms_date($post->published_at, 'd M Y'));
        if ($w['author'] && $post->author) $parts[] = e($post->author->name);
        if ($w['views'] && setting('show_view_count', '1')) $parts[] = e(__('Views: :count', ['count' => number_format($post->view_count)]));
        if ($w['categories']) foreach ($post->categories as $category) $parts[] = '<a href="'.e($category->url()).'">'.e($category->name).'</a>';
    }
@endphp
@if ($parts)
<div class="post-meta">{!! implode(' · ', $parts) !!}</div>
@elseif ($preview && ! $post)
<p class="b-note">{{ __('Shows the date, author and categories. Use it in the single article template.') }}</p>
@endif
