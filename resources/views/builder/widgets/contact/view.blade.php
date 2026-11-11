@php
    $whatsapp = preg_replace('/\D+/', '', (string) setting('contact_whatsapp'));
    $email = (string) setting('contact_email');
    $phone = (string) setting('contact_phone');
    $address = (string) setting('contact_address');
    $channels = array_values(array_filter([
        $w['show_whatsapp'] && $whatsapp ? ['icon' => 'comment', 'label' => 'WhatsApp', 'value' => '+'.$whatsapp, 'url' => 'https://wa.me/'.$whatsapp] : null,
        $w['show_email'] && $email ? ['icon' => 'mail', 'label' => __('Email'), 'value' => $email, 'url' => 'mailto:'.$email] : null,
        $w['show_phone'] && $phone ? ['icon' => 'phone', 'label' => __('Phone'), 'value' => $phone, 'url' => 'tel:'.preg_replace('/[^\d+]/', '', $phone)] : null,
        $w['show_address'] && $address ? ['icon' => 'globe', 'label' => __('Address'), 'value' => $address, 'url' => null] : null,
    ]));
@endphp
@if ($channels)
<ul class="b-contact b-contact-{{ $w['layout'] }}">
    @foreach ($channels as $c)
        <li>
            <span class="b-contact-icon">{{ icon($c['icon'], 18) }}</span>
            <span><small>{{ $c['label'] }}</small>
                @if ($c['url'])<a href="{{ $c['url'] }}"@if (str_starts_with($c['url'], 'https:')) target="_blank" rel="noopener"@endif>{{ $c['value'] }}</a>@else<span class="b-contact-text">{{ $c['value'] }}</span>@endif
            </span>
        </li>
    @endforeach
</ul>
@elseif ($preview)
<p class="b-note">{{ __('Fill in Settings > Contact to show contact details here.') }}</p>
@endif
