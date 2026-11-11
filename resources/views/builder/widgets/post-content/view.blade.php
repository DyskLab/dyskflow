@if (\App\Support\Builder\Context::$post)
<div class="prose">{!! \App\Support\Builder\Context::$content !!}</div>
@elseif ($preview)
<p class="b-note">{{ __('Shows the text of the article. Use it in the single article template.') }}</p>
@endif
