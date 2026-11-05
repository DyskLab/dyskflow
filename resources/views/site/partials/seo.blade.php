<title>{{ $seo['title'] }}</title>
@if (! empty($seo['description']))<meta name="description" content="{{ $seo['description'] }}">
@endif
<link rel="canonical" href="{{ $seo['canonical'] }}">
@foreach (\App\Support\Seo::hreflang($seo) as $language => $href)
<link rel="alternate" hreflang="{{ $language }}" href="{{ $href }}">
@endforeach
@if (! empty($seo['noindex']))<meta name="robots" content="noindex">
@endif
<meta property="og:site_name" content="{{ setting('site_title') }}">
<meta property="og:type" content="{{ $seo['type'] }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
@if (! empty($seo['description']))<meta property="og:description" content="{{ $seo['description'] }}">
@endif
@if (! empty($seo['image']))<meta property="og:image" content="{{ $seo['image'] }}">
<meta name="twitter:card" content="summary_large_image">
@endif
@if (setting('favicon'))<link rel="icon" href="{{ cms_asset_url(setting('favicon')) }}">
@endif
@if (setting('seo_google_verification'))<meta name="google-site-verification" content="{{ setting('seo_google_verification') }}">
@endif
<link rel="alternate" type="application/rss+xml" title="{{ setting('site_title') }}" href="{{ site_route('feed') }}">
@if (! empty($seo['schema']))<script type="application/ld+json">{!! json_encode(array_filter($seo['schema']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
