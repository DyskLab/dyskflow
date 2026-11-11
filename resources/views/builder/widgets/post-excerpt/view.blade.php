@php $post = \App\Support\Builder\Context::$post; $text = $post ? ($post->excerpt ?: cms_excerpt($post->content, (int) $w['length'])) : ''; @endphp
@if ($text !== '')
<p class="b-text b-text-{{ $w['size'] }} muted">{{ $text }}</p>
@elseif ($preview)
<p class="b-note">{{ __('Shows the article summary. Use it in the single article template.') }}</p>
@endif
