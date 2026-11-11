@php
    $url = urlencode(url()->current());
    $text = urlencode(\App\Support\Builder\Context::$post?->title ?? (string) setting('site_title'));
    $all = [
        'whatsapp' => ['WhatsApp', 'https://wa.me/?text='.$text.'%20'.$url, 'messaging'],
        'telegram' => ['Telegram', 'https://t.me/share/url?url='.$url.'&text='.$text, 'messaging'],
        'facebook' => ['Facebook', 'https://www.facebook.com/sharer/sharer.php?u='.$url, 'social'],
        'x' => ['X', 'https://x.com/intent/post?url='.$url.'&text='.$text, 'social'],
        'linkedin' => ['LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url='.$url, 'social'],
        'email' => [__('Email'), 'mailto:?subject='.$text.'&body='.$url, 'messaging'],
    ];
    $links = array_filter($all, fn ($n) => $w['networks'] === 'all' || $n[2] === $w['networks']);
@endphp
<div class="b-share b-share-{{ $w['variant'] }}{{ $w['labels'] ? '' : ' b-share-nolabel' }}" data-b-share>
    @if ($w['title'] !== '')<span class="b-share-title">{{ $w['title'] }}</span>@endif
    @foreach ($links as $key => [$name, $href])
        <a class="b-share-btn b-social-{{ $key }}" href="{{ $href }}" data-b-share-net="{{ $key }}" @if ($key !== 'email') target="_blank" rel="noopener" @endif aria-label="{{ __('Share on :network', ['network' => $name]) }}">{{ icon(['email' => 'mail', 'x' => 'x-logo'][$key] ?? $key, 18) }}@if ($w['labels'])<span>{{ $name }}</span>@endif</a>
    @endforeach
    @if ($w['networks'] !== 'social')<button type="button" class="b-share-btn" data-b-copy-link data-copied="{{ __('Link copied') }}" aria-label="{{ __('Copy link') }}">{{ icon('link', 18) }}@if ($w['labels'])<span>{{ __('Copy link') }}</span>@endif</button>@endif
</div>
