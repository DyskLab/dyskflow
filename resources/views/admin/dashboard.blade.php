@extends('admin.layout')

@php
    $statusBadge = function ($post) {
        if ($post->status === 'draft') {
            return ['badge-muted', __('Draft')];
        }

        return $post->isLive() ? ['badge-success', __('Published')] : ['badge-warning', __('Scheduled')];
    };
    $editUrl = fn ($post) => route($post->type === 'page' ? 'admin.pages.edit' : 'admin.articles.edit', $post);
    $commentBadges = ['pending' => ['badge-warning', __('Pending')], 'approved' => ['badge-success', __('Approved')], 'spam' => ['badge-danger', __('Spam')]];
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h1>
            <p class="muted">{{ __('Here is what is happening on your site.') }}</p>
        </div>
        <div class="row">
            @foreach ($actions as $action)
                @include('admin.partials.page-action', ['action' => $action])
            @endforeach
        </div>
    </div>

    <div class="stack dash">
        <section class="card" aria-labelledby="dash-attention">
            <div class="card-header"><h2 class="card-title" id="dash-attention">{{ __('Needs your attention') }}</h2></div>
            @if ($attention)
                <ul class="dash-attention">
                    @foreach ($attention as $item)
                        <li class="dash-tone-{{ $item['tone'] }}">
                            <span class="dash-dot" aria-hidden="true"></span>
                            <span class="dash-attention-text">{{ $item['text'] }}</span>
                            <a class="btn btn-outline btn-sm" href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="card-content dash-clear">{{ icon('check') }} {{ __('Nothing needs your attention right now.') }}</div>
            @endif
        </section>

        <div class="card dash-stats">
            @foreach ($stats as $stat)
                @if ($stat['url'])
                    <a class="dash-stat dash-stat-link" href="{{ $stat['url'] }}">
                @else
                    <div class="dash-stat">
                @endif
                    <span class="dash-stat-head"><span class="stat-value">{{ number_format($stat['value']) }}</span>{{ icon($stat['icon']) }}</span>
                    <span class="dash-stat-label">{{ $stat['label'] }}</span>
                    <span class="muted small">{{ $stat['hint'] }}</span>
                @if ($stat['url'])</a>@else</div>@endif
            @endforeach
        </div>

        <div class="dash-grid">
            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ __('Recently updated') }}</h2></div>
                <div class="card-content stack-sm">
                    @forelse ($recent as $post)
                        @php [$badgeClass, $badgeText] = $statusBadge($post); @endphp
                        <div class="dash-row">
                            <a class="dash-row-main dash-stacked" href="{{ $editUrl($post) }}">
                                <span class="truncate">{{ $post->title }}</span>
                                <span class="muted small">{{ cms_date($post->updated_at, 'd M Y') }}</span>
                            </a>
                            <span class="badge {{ $badgeClass }}">{{ $badgeText }}</span>
                        </div>
                    @empty
                        <p class="muted">{{ __('No articles yet.') }}</p>
                    @endforelse
                </div>
            </section>

            @if ($scheduled->isNotEmpty())
                <section class="card">
                    <div class="card-header"><h2 class="card-title">{{ __('Scheduled to publish') }}</h2></div>
                    <div class="card-content stack-sm">
                        @foreach ($scheduled as $post)
                            <div class="dash-row">
                                <a class="dash-row-main truncate" href="{{ $editUrl($post) }}">{{ $post->title }}</a>
                                <span class="muted small dash-row-side">{{ cms_date($post->published_at) }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @can('comments.manage')
                <section class="card">
                    <div class="card-header"><h2 class="card-title">{{ __('Latest comments') }}</h2></div>
                    <div class="card-content stack-sm">
                        @forelse ($comments as $comment)
                            <div class="dash-row">
                                <a class="dash-row-main dash-stacked" href="{{ route('admin.comments.index') }}">
                                    <span class="truncate"><strong>{{ $comment->author_name }}</strong>@if ($comment->post) · <span class="muted">{{ $comment->post->title }}</span>@endif</span>
                                    <span class="muted small truncate">{{ \Illuminate\Support\Str::limit($comment->content, 80) }}</span>
                                </a>
                                <span class="badge {{ $commentBadges[$comment->status][0] }}">{{ $commentBadges[$comment->status][1] }}</span>
                            </div>
                        @empty
                            <p class="muted">{{ __('No comments yet.') }}</p>
                        @endforelse
                    </div>
                </section>
            @endcan

            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ __('Most viewed') }}</h2></div>
                <div class="card-content stack-sm">
                    @forelse ($popular as $post)
                        <div class="dash-row">
                            <a class="dash-row-main truncate" href="{{ $editUrl($post) }}">{{ $post->title }}</a>
                            <span class="muted small dash-row-side">{{ __('Views: :count', ['count' => number_format($post->view_count)]) }}</span>
                        </div>
                    @empty
                        <p class="muted">{{ __('No views yet. Visits by logged-in users and bots do not count.') }}</p>
                    @endforelse
                </div>
            </section>

            @if ($health !== null)
                @php
                    $overall = ($health['critical'] ?? 0) ? 'critical' : (($health['warning'] ?? 0) ? 'warning' : 'good');
                @endphp
                <section class="card">
                    <div class="card-header"><h2 class="card-title">{{ __('Site status') }}</h2></div>
                    <div class="card-content stack-sm">
                        <div class="dash-row">
                            <span class="dash-row-main">{{ __('Site health') }}</span>
                            <a class="badge {{ ['critical' => 'badge-danger', 'warning' => 'badge-warning', 'good' => 'badge-success'][$overall] }}" style="text-decoration:none" href="{{ route('admin.settings.health.index') }}">{{ ['critical' => __('Critical'), 'warning' => __('Can be improved'), 'good' => __('Good')][$overall] }}</a>
                        </div>
                        <div class="dash-row">
                            <span class="dash-row-main">{{ __('DyskFlow version') }}</span>
                            <span class="muted small dash-row-side">{{ config('cms.version') }}</span>
                        </div>
                        <div class="dash-row">
                            <span class="dash-row-main">PHP</span>
                            <span class="muted small dash-row-side">{{ PHP_VERSION }}</span>
                        </div>
                        @can('backups.manage')
                            <div class="dash-row">
                                <span class="dash-row-main">{{ __('Latest backup') }}</span>
                                <a class="muted small dash-row-side" href="{{ route('admin.settings.backups.index') }}">{{ $latestBackup ? cms_date(now()->setTimestamp($latestBackup)) : __('None yet') }}</a>
                            </div>
                        @endcan
                    </div>
                </section>
            @endif

            @if ($activities->isNotEmpty())
                <section class="card">
                    <div class="card-header"><h2 class="card-title">{{ __('Latest activity') }}</h2></div>
                    <div class="card-content stack-sm">
                        @foreach ($activities as $activity)
                            <div class="dash-row">
                                <span class="dash-row-main truncate">{{ $activity->user_name }} · {{ $activity->action }}@if ($activity->subject) · {{ $activity->subject }}@endif</span>
                                <span class="muted small dash-row-side">{{ cms_date($activity->created_at) }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
