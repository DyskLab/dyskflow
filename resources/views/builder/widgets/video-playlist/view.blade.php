@php
    $videos = [];
    foreach ($w['items'] as $item) {
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $item['url'], $m)) {
            $videos[] = $item + ['embed' => 'https://www.youtube-nocookie.com/embed/'.$m[1].'?autoplay=1', 'poster' => $item['thumb'] ?: 'https://i.ytimg.com/vi/'.$m[1].'/hqdefault.jpg'];
        } elseif (preg_match('~vimeo\.com/(?:video/)?(\d{4,12})~', $item['url'], $m)) {
            $videos[] = $item + ['embed' => 'https://player.vimeo.com/video/'.$m[1].'?autoplay=1&dnt=1', 'poster' => $item['thumb']];
        }
    }
@endphp
@if ($videos)
<div class="b-playlist b-playlist-{{ $w['layout'] }}" data-b-playlist>
    <div class="b-video" data-b-video="{{ $videos[0]['embed'] }}" data-b-title="{{ $videos[0]['title'] }}">
        @if ($videos[0]['poster'])<img src="{{ $videos[0]['poster'] }}" alt="" loading="lazy" decoding="async">@endif
        <button type="button" class="b-video-play" aria-label="{{ __('Play video') }}: {{ $videos[0]['title'] }}">{{ icon('play', 28) }}</button>
    </div>
    <div class="b-playlist-list">
        @if ($w['title'] !== '')<p class="b-playlist-title">{{ $w['title'] }} <span>{{ count($videos) }} {{ __('videos') }}</span></p>@endif
        <ol>
            @foreach ($videos as $i => $video)
                <li><button type="button" data-b-play="{{ $video['embed'] }}" data-poster="{{ $video['poster'] }}" data-title="{{ $video['title'] }}" aria-current="{{ $i === 0 ? 'true' : 'false' }}">
                    @if ($video['poster'])<img src="{{ $video['poster'] }}" alt="" width="96" height="54" loading="lazy">@endif
                    <span>{{ $video['title'] }}@if ($video['duration'] !== '')<small>{{ $video['duration'] }}</small>@endif</span>
                </button></li>
            @endforeach
        </ol>
    </div>
</div>
@elseif ($preview)
<p class="b-note">{{ __('Add YouTube or Vimeo links.') }}</p>
@endif
