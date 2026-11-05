@extends('admin.layout')

@section('content')
    @php
        $critical = $counts['critical'] ?? 0;
        $warnings = $counts['warning'] ?? 0;
        $overall = $critical ? 'critical' : ($warnings ? 'warning' : 'good');
    @endphp
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ $title }}</h1>
            <p class="muted">{{ __('Checked just now. Nothing is changed by opening this page.') }}</p>
        </div>
    </div>

    <div class="stack" style="max-width:820px">
        <div class="card health-summary health-{{ $overall }}">
            <span class="health-icon">{{ icon($overall === 'good' ? 'check' : 'alert', 22) }}</span>
            <div>
                <strong class="health-headline">
                    {{ ['critical' => __('Needs attention now'), 'warning' => __('Works, with things to improve'), 'good' => __('Everything looks good')][$overall] }}
                </strong>
                <div class="muted small">{{ __(':critical critical, :warnings to improve, :good good', ['critical' => $critical, 'warnings' => $warnings, 'good' => $counts['good'] ?? 0]) }}</div>
            </div>
        </div>

        <ul class="health-list card">
            @foreach ($checks as $check)
                <li class="health-row health-{{ $check['status'] }}">
                    <span class="health-icon">{{ icon($check['status'] === 'good' ? 'check' : 'alert') }}</span>
                    <div class="health-text">
                        <div style="font-weight:500">{{ $check['title'] }}</div>
                        <div class="muted small">{{ $check['message'] }}</div>
                    </div>
                    @if ($check['action'])
                        <a class="btn btn-outline btn-sm" href="{{ $check['action']['url'] }}">{{ $check['action']['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endsection
