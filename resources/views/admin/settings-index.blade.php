@extends('admin.layout')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ __('Settings') }}</h1>
            <p class="muted">{{ __('Manage your site and system.') }}</p>
        </div>
    </div>
    <div class="settings-grid">
        @foreach ($cards as $card)
            <a class="card setting-card" href="{{ $card['url'] }}">
                <span class="setting-icon">{{ icon($card['icon'], 18) }}</span>
                <span>
                    <span class="card-title" style="display:block">{{ $card['title'] }}</span>
                    <span class="muted small">{{ $card['description'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
@endsection
