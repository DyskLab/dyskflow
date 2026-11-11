<div class="site-foot-in b-site-copyright">
    <span>© {{ date('Y') }} {{ setting('site_title') }}@if ($w['tagline'] && \App\Support\Locales::text('tagline')) · {{ \App\Support\Locales::text('tagline') }}@endif</span>
    @if ($w['links'])
        <span class="site-foot-links">
            @if ($privacyPage = cms_privacy_page())<a href="{{ $privacyPage->url() }}">{{ $privacyPage->title }}</a>@endif
            @if (setting('contact_email'))<a href="mailto:{{ setting('contact_email') }}">{{ setting('contact_email') }}</a>@endif
        </span>
    @endif
</div>
