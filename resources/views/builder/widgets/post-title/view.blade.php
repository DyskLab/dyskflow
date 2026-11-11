@php $post = \App\Support\Builder\Context::$post; @endphp
@if ($post)
<{{ $w['tag'] }} class="b-heading {{ \App\Support\Builder\Tokens::classes('size', $w['size'], ['md', 'lg', 'xl', '2xl']) }}">{{ $post->title }}</{{ $w['tag'] }}>
@elseif ($preview)
<p class="b-note">{{ __('Shows the article title. Use it in the single article template.') }}</p>
@endif
