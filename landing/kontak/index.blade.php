@php
    $link = fn (?string $url) => $url && preg_match('#^https?://#i', $url) ? $url : null;
    $email = setting('contact_email');
    $phone = setting('contact_phone');
    $whatsapp = preg_replace('/\D+/', '', (string) setting('contact_whatsapp'));
    $address = setting('contact_address');
    $map = $link(setting('contact_map_url'));

    $channels = array_values(array_filter([
        $whatsapp ? ['icon' => 'comment', 'label' => 'WhatsApp', 'value' => '+'.$whatsapp, 'url' => 'https://wa.me/'.$whatsapp, 'external' => true] : null,
        $email ? ['icon' => 'mail', 'label' => __('Email'), 'value' => $email, 'url' => 'mailto:'.$email] : null,
        $phone ? ['icon' => 'phone', 'label' => __('Phone'), 'value' => $phone, 'url' => 'tel:'.preg_replace('/[^\d+]/', '', $phone)] : null,
    ]));
    $socials = array_filter([
        'Instagram' => $link(setting('social_instagram')),
        'Facebook' => $link(setting('social_facebook')),
        'X' => $link(setting('social_x')),
        'LinkedIn' => $link(setting('social_linkedin')),
        'YouTube' => $link(setting('social_youtube')),
        'TikTok' => $link(setting('social_tiktok')),
    ]);
    $empty = ! $channels && ! $address && ! $socials;
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fbf9f7">
    {!! $seo !!}
    @include('site.partials.theme-script')
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}?v={{ config('cms.version') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ config('cms.version') }}">
    <link rel="stylesheet" href="{{ landing_asset('assets/style.css') }}">
</head>
<body>
    <a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
    @include('site.partials.header')

    <main class="site-wrap wide site-main" id="main" tabindex="-1">
        <div class="contact-head">
            <h1 class="site-title">{{ __('Contact') }}</h1>
            <p class="muted site-lead">{{ $empty ? __('Our contact details will be here soon.') : __('Choose whichever way is easiest for you.') }}</p>
        </div>

        @if ($channels)
            <div class="contact-grid">
                @foreach ($channels as $channel)
                    <a class="contact-card" href="{{ $channel['url'] }}" @if (! empty($channel['external'])) target="_blank" rel="noopener" @endif>
                        <span class="contact-icon">{{ icon($channel['icon'], 20) }}</span>
                        <span class="muted contact-label">{{ $channel['label'] }}</span>
                        <strong class="contact-value">{{ $channel['value'] }}</strong>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($address || $socials)
            <div class="contact-more">
                @if ($address)
                    <section>
                        <h2>{{ __('Address') }}</h2>
                        <p class="contact-address">{{ $address }}</p>
                        @if ($map)<a class="contact-link" href="{{ $map }}" target="_blank" rel="noopener">{{ __('Open the map') }} {{ icon('external', 16) }}</a>@endif
                    </section>
                @endif
                @if ($socials)
                    <section>
                        <h2>{{ __('Social media') }}</h2>
                        <ul class="contact-socials">
                            @foreach ($socials as $name => $url)
                                <li><a class="contact-link" href="{{ $url }}" target="_blank" rel="noopener">{{ $name }} {{ icon('external', 16) }}</a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        @endif
    </main>

    @include('site.partials.footer')
    <script src="{{ asset('js/site.js') }}?v={{ config('cms.version') }}"></script>
</body>
</html>
