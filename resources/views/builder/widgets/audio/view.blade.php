@if ($w['file'] !== '')
<div class="b-audio">
    @if ($w['cover'] !== '')<img src="{{ $w['cover'] }}" alt="" width="88" height="88" loading="lazy">@endif
    <div class="b-audio-body">
        @if ($w['title'] !== '')<strong>{{ $w['title'] }}</strong>@endif
        @if ($w['artist'] !== '')<span>{{ $w['artist'] }}</span>@endif
        <audio controls preload="none" src="{{ $w['file'] }}" @if ($w['title'] !== '') aria-label="{{ $w['title'] }}" @endif></audio>
        @if ($w['download'])<a class="b-audio-download" href="{{ $w['file'] }}" download>{{ icon('download', 14) }} {{ __('Download') }}</a>@endif
    </div>
</div>
@elseif ($preview)
<p class="b-note">{{ __('Upload an mp3, m4a, ogg or wav file.') }}</p>
@endif
