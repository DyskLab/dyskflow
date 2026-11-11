@php
    $embed = null;
    $poster = $w['poster'];
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $w['url'], $m)) {
        $embed = 'https://www.youtube-nocookie.com/embed/'.$m[1].'?autoplay=1';
        $poster = $w['poster'] ?: 'https://i.ytimg.com/vi/'.$m[1].'/hqdefault.jpg';
    } elseif (preg_match('~vimeo\.com/(?:video/)?(\d{4,12})~', $w['url'], $m)) {
        $embed = 'https://player.vimeo.com/video/'.$m[1].'?autoplay=1&dnt=1';
    }
    $file = $embed ? '' : $w['file'];
    $type = str_contains(strtolower((string) parse_url($file, PHP_URL_PATH)), '.webm') ? 'video/webm' : 'video/mp4';
@endphp
@if ($embed)
<div class="b-video" data-b-video="{{ $embed }}" data-b-title="{{ $w['title'] ?: __('Video') }}">
    @if ($poster)<img src="{{ $poster }}" alt="" loading="lazy" decoding="async">@endif
    <button type="button" class="b-video-play" aria-label="{{ __('Play video') }}{{ $w['title'] !== '' ? ': '.$w['title'] : '' }}">{{ icon('play', 28) }}</button>
</div>
@elseif ($file !== '')
<div class="b-video b-video-file">
    <video {{ $w['loop'] ? 'autoplay muted loop playsinline' : 'controls' }}{!! $w['loop'] ? '' : ' preload="none"' !!}@if ($poster) poster="{{ $poster }}"@endif @if ($w['title'] !== '')aria-label="{{ $w['title'] }}"@endif>
        <source src="{{ $file }}" type="{{ $type }}">
    </video>
</div>
@elseif ($preview)
<p class="b-note">{{ __('Use a YouTube or Vimeo link, or an mp4 or webm file.') }}</p>
@endif
