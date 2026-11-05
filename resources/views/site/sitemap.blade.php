{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($entries as $entry)
    <url>
        <loc>{{ $entry['loc'] }}</loc>
@if ($entry['lastmod'])
        <lastmod>{{ $entry['lastmod']->toAtomString() }}</lastmod>
@endif
@foreach ($entry['links'] as $language => $href)
        <xhtml:link rel="alternate" hreflang="{{ $language }}" href="{{ $href }}"/>
@endforeach
    </url>
@endforeach
</urlset>
