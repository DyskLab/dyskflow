<nav class="b-toc{{ $w['sticky'] ? ' b-toc-sticky' : '' }}" data-b-toc="{{ $w['levels'] }}" aria-label="{{ $w['title'] ?: __('Table of contents') }}">
    @if ($w['collapsible'])
        <details open><summary class="b-toc-title">{{ $w['title'] ?: __('Table of contents') }}</summary><{{ $w['numbered'] ? 'ol' : 'ul' }} class="b-toc-list" data-b-toc-list></{{ $w['numbered'] ? 'ol' : 'ul' }}></details>
    @else
        <p class="b-toc-title">{{ $w['title'] ?: __('Table of contents') }}</p><{{ $w['numbered'] ? 'ol' : 'ul' }} class="b-toc-list" data-b-toc-list></{{ $w['numbered'] ? 'ol' : 'ul' }}>
    @endif
    <p class="b-toc-empty" hidden>{{ __('Headings on this page appear here.') }}</p>
</nav>
