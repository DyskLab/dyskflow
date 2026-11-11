@php $post = \App\Support\Builder\Context::$post; @endphp
@if ($post && $post->tags->isNotEmpty())
<div class="row b-post-tags">@foreach ($post->tags as $tag)<a class="badge" href="{{ $tag->url() }}">{{ $tag->name }}</a>@endforeach</div>
@elseif ($preview && ! $post)
<p class="b-note">{{ __('Shows the tags of the article.') }}</p>
@endif
