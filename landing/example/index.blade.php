<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {!! $seo !!}
    <link rel="stylesheet" href="{{ landing_asset('assets/style.css') }}">
</head>
<body>
    <header class="hero">
        <p class="eyebrow">{{ setting('site_title') }}</p>
        <h1>{{ $page->title ?? setting('tagline', setting('site_title')) }}</h1>
        <p class="lead">{{ $page->excerpt ?? setting('seo_description') }}</p>
        @if (setting('contact_whatsapp'))
            <a class="cta" href="https://wa.me/{{ setting('contact_whatsapp') }}">WhatsApp</a>
        @elseif (setting('contact_email'))
            <a class="cta" href="mailto:{{ setting('contact_email') }}">{{ setting('contact_email') }}</a>
        @endif
    </header>

    @php $posts = cms_posts(3); @endphp
    @if ($posts->isNotEmpty())
        <section class="posts">
            @foreach ($posts as $post)
                <a class="post" href="{{ $post->url() }}">
                    <h2>{{ $post->title }}</h2>
                    <p>{{ $post->excerpt }}</p>
                </a>
            @endforeach
        </section>
    @endif
</body>
</html>
