{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<rss version="2.0">
<channel>
    <title>{{ setting('site_title') }}</title>
    <link>{{ site_route('home') }}</link>
    <description>{{ \App\Support\Locales::text('tagline') }}</description>
    <language>{{ app()->getLocale() }}</language>
@foreach ($posts as $post)
    <item>
        <title>{{ $post->title }}</title>
        <link>{{ $post->url() }}</link>
        <guid>{{ $post->url() }}</guid>
        <pubDate>{{ $post->published_at?->toRfc2822String() }}</pubDate>
        <description>{{ $post->excerpt ?: cms_excerpt($post->content, 300) }}</description>
    </item>
@endforeach
</channel>
</rss>
