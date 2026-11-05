@extends('site.layout')

@section('content')
    <article class="stack">
        <h1 class="site-title">{{ $post->title }}</h1>
        @if ($post->featured_image)
            @include('site.partials.cover')
        @endif
        <div class="prose">{!! $content !!}</div>
    </article>
@endsection
