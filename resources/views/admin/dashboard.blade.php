@extends('admin.layout')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h1>
            <p class="muted">{{ __('Here is what is happening on your site.') }}</p>
        </div>
        @can('articles.write')
            <a class="btn" href="{{ route('admin.articles.create') }}">{{ icon('plus') }} {{ __('New article') }}</a>
        @endcan
    </div>

    <div class="stats">
        @foreach ($stats as [$label, $value])
            <div class="card card-content">
                <div class="muted small">{{ $label }}</div>
                <div class="stat-value">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    <div class="settings-grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Recently updated') }}</h2></div>
            <div class="card-content stack-sm">
                @forelse ($recent as $post)
                    <div class="row between">
                        <a class="truncate" style="max-width:70%" href="{{ route('admin.articles.edit', $post) }}">{{ $post->title }}</a>
                        <span class="muted small">{{ cms_date($post->updated_at) }}</span>
                    </div>
                @empty
                    <p class="muted">{{ __('No articles yet.') }}</p>
                @endforelse
            </div>
        </div>
        @if ($activities->isNotEmpty())
            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ __('Latest activity') }}</h2></div>
                <div class="card-content stack-sm">
                    @foreach ($activities as $activity)
                        <div class="row between">
                            <span class="truncate" style="max-width:70%">{{ $activity->user_name }} · {{ $activity->action }}@if ($activity->subject) · {{ $activity->subject }}@endif</span>
                            <span class="muted small">{{ cms_date($activity->created_at) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
