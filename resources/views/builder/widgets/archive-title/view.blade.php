@php $heading = \App\Support\Builder\Context::$heading; $intro = \App\Support\Builder\Context::$intro; @endphp
@if ($heading)
<div class="stack-sm">
    <h1 class="b-heading {{ \App\Support\Builder\Tokens::classes('size', $w['size'], ['md', 'lg', 'xl', '2xl']) }}">{{ $heading }}</h1>
    @if ($w['intro'] && $intro)<p class="muted site-lead">{{ $intro }}</p>@endif
</div>
@elseif ($preview)
<p class="b-note">{{ __('Shows the title of the list. Use it in the article list or category template.') }}</p>
@endif
