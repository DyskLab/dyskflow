{{-- The folder path above the file list and the editor. Vars: crumbs [label, url]. --}}
<nav class="file-path" aria-label="{{ __('Folder') }}">
    @foreach ($crumbs as $crumb)
        @if (! $loop->first)<span class="muted" aria-hidden="true">/</span>@endif
        @if ($loop->last && empty($linkLast))
            <span class="mono" aria-current="location">{{ $crumb['label'] }}</span>
        @else
            <a class="mono" href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
        @endif
    @endforeach
</nav>
