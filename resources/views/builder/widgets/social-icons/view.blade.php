@php
    $links = [];
    if ($w['source'] === 'settings') {
        foreach (['instagram' => 'social_instagram', 'facebook' => 'social_facebook', 'x' => 'social_x', 'linkedin' => 'social_linkedin'] as $network => $key) {
            if ($url = (string) setting($key)) $links[] = ['network' => $network, 'url' => $url];
        }
        if ($number = preg_replace('/\D+/', '', (string) setting('contact_whatsapp'))) $links[] = ['network' => 'whatsapp', 'url' => 'https://wa.me/'.$number];
        if ($email = (string) setting('contact_email')) $links[] = ['network' => 'email', 'url' => 'mailto:'.$email];
    } else {
        $links = array_values(array_filter($w['items'], fn ($i) => $i['url'] !== ''));
    }
    $names = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'github' => 'GitHub', 'email' => __('Email'), 'website' => __('Website')];
@endphp
@if ($links)
<ul class="b-social b-social-{{ $w['variant'] }} b-social-{{ $w['size'] }}{{ $w['brand_colors'] ? ' b-social-brand' : '' }}">
    @foreach ($links as $link)
        <li><a class="b-social-{{ $link['network'] }}" href="{{ $link['url'] }}" @if (! str_starts_with($link['url'], 'mailto:')) target="_blank" rel="noopener me" @endif aria-label="{{ $names[$link['network']] ?? $link['network'] }}">{{ icon(['website' => 'globe', 'email' => 'mail', 'x' => 'x-logo'][$link['network']] ?? $link['network'], 20) }}</a></li>
    @endforeach
</ul>
@elseif ($preview)
<p class="b-note">{{ __('Add your social media in Settings > Contact, or choose your own links.') }}</p>
@endif
