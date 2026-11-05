@php $coverMedia = \App\Models\Media::where('path', $post->featured_image)->first(['width', 'height', 'alt']); @endphp
<img class="post-cover" src="{{ cms_asset_url($post->featured_image) }}" alt="{{ $coverMedia?->alt ?: $post->title }}" fetchpriority="high" @if ($coverMedia?->width && $coverMedia?->height) width="{{ $coverMedia->width }}" height="{{ $coverMedia->height }}" @endif>
