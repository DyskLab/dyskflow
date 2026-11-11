@php $post = \App\Support\Builder\Context::$post; @endphp
@if ($post && $post->featured_image)
@php $coverMedia = \App\Models\Media::where('path', $post->featured_image)->first(['width', 'height', 'alt']); @endphp
<img class="post-cover b-post-image" src="{{ cms_asset_url($post->featured_image) }}" alt="{{ $coverMedia?->alt ?: $post->title }}" fetchpriority="high" @if ($w['ratio'] !== 'original') style="aspect-ratio: {{ str_replace(':', ' / ', $w['ratio']) }}; max-height: none" @elseif ($coverMedia?->width && $coverMedia?->height) width="{{ $coverMedia->width }}" height="{{ $coverMedia->height }}" @endif>
@elseif ($preview)
<p class="b-note">{{ __('Shows the featured image of the article, when it has one.') }}</p>
@endif
