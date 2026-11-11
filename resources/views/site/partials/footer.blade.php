{{-- The site footer, shared by the default theme and landing pages that want it. A Footer template (Design > Templates) replaces it. --}}
@if ($footerTemplate = \App\Support\Builder\Templates::part('footer', $languageLink ?? null))
{{ $footerTemplate }}
@else
<footer class="site-footer">
    <div class="site-wrap wide site-foot-in">
        <span>© {{ date('Y') }} {{ setting('site_title') }}@if (\App\Support\Locales::text('tagline')) · {{ \App\Support\Locales::text('tagline') }}@endif</span>
        <span class="site-foot-links">
            @if ($privacyPage = cms_privacy_page())<a href="{{ $privacyPage->url() }}">{{ $privacyPage->title }}</a>@endif
            @if (setting('contact_email'))<a href="mailto:{{ setting('contact_email') }}">{{ setting('contact_email') }}</a>@endif
        </span>
    </div>
</footer>
@endif
{{-- The site's custom JavaScript for landing pages; layouts print it after site.js instead. --}}
@unless ($customJsLater ?? false){!! \App\Support\Theme::customTag('js') !!}@endunless
