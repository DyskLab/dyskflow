<nav class="breadcrumb" aria-label="{{ __('Breadcrumb') }}">
    <ol>
        @foreach (\App\Support\Breadcrumbs::current($title ?? null) as $crumb)
            <li>
                @if ($crumb['url'])
                    <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                @else
                    <span aria-current="page">{{ $crumb['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
