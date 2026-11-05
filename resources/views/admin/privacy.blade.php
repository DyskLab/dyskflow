@extends('admin.layout')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ $title }}</h1>
            <p class="muted">{{ __('Write a privacy policy that matches what this site really does.') }}</p>
        </div>
    </div>

    <div class="stack" style="max-width:720px">
        <div class="card">
            <div class="card-header">
                <div class="row between">
                    <h2 class="card-title">{{ __('Privacy policy page') }}</h2>
                    @if ($page?->isLive())
                        <span class="badge badge-success">{{ __('Published') }}</span>
                    @elseif ($page)
                        <span class="badge badge-warning">{{ __('Draft') }}</span>
                    @else
                        <span class="badge badge-warning">{{ __('Not set') }}</span>
                    @endif
                </div>
                <p class="muted">
                    @if ($page)
                        {{ $page->title }}. {{ __('Once published, it is linked in the site footer.') }}
                    @else
                        {{ __('Start from a draft built from the facts below, or choose an existing page.') }}
                    @endif
                </p>
            </div>
            <div class="card-content">
                <div class="row">
                    @if ($page)
                        <a class="btn" href="{{ route('admin.pages.edit', $page) }}">{{ __('Edit page') }}</a>
                    @else
                        <form method="post" action="{{ route('admin.settings.privacy.draft') }}">@csrf<button class="btn" type="submit">{{ __('Create draft') }}</button></form>
                    @endif
                    <a class="btn btn-outline" href="{{ route('admin.settings.edit', 'pages') }}">{{ __('Choose another page') }}</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">{{ __('What this site collects') }}</h2>
                <p class="muted">{{ __('Based on your current settings and active plugins.') }}</p>
            </div>
            <div class="card-content stack">
                @foreach ($facts as $fact)
                    <div>
                        <div style="font-weight:500">{{ $fact['title'] }}</div>
                        <p class="muted">{{ $fact['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">{{ __('What a policy should cover') }}</h2>
                <p class="muted">{{ __('This guide is a starting point, not legal advice. Check the rules that apply to you, such as Indonesia\'s Personal Data Protection Law (UU PDP) or the GDPR.') }}</p>
            </div>
            <div class="card-content">
                <ul class="privacy-list">
                    @foreach ($checklist as $item)<li>{{ icon('check') }} <span>{{ $item }}</span></li>@endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection
