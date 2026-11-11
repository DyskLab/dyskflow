<form class="b-search b-search-{{ $w['size'] }}" action="{{ site_route('search') }}" method="get" role="search">
    <input class="input" type="search" name="q" autocomplete="off" value="{{ is_string(request('q')) ? request('q') : '' }}" placeholder="{{ $w['placeholder'] ?: __('Search…') }}" aria-label="{{ __('Search') }}">
    @if ($w['button'] === 'icon')<button class="btn" type="submit" aria-label="{{ __('Search') }}">{{ icon('search', 18) }}</button>
    @elseif ($w['button'] === 'text')<button class="btn" type="submit">{{ $w['button_label'] ?: __('Search') }}</button>@endif
</form>
