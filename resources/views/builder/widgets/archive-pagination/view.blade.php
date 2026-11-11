@php $posts = \App\Support\Builder\Context::$posts; @endphp
@if ($posts && $posts->hasPages())
<nav class="site-pagination" aria-label="{{ __('Page navigation') }}">
    @if ($posts->previousPageUrl())<a class="btn btn-outline" href="{{ $posts->previousPageUrl() }}" rel="prev">{{ __('Newer') }}</a>@else<span></span>@endif
    @if ($posts->nextPageUrl())<a class="btn btn-outline" href="{{ $posts->nextPageUrl() }}" rel="next">{{ __('Older') }}</a>@endif
</nav>
@elseif ($preview && ! $posts)
<p class="b-note">{{ __('Shows links to newer and older pages of the list.') }}</p>
@endif
