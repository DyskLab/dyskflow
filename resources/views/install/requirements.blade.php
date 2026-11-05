@extends('install.layout')

@section('content')
    <div class="card-content">
        @foreach ($checks as [$label, $passed, $detail])
            <div class="req">
                <span>{{ $label }} @if ($detail)<span class="muted small">({{ $detail }})</span>@endif</span>
                <span class="badge {{ $passed ? 'badge-success' : 'badge-danger' }}">{{ $passed ? __('OK') : __('Fix this') }}</span>
            </div>
        @endforeach
        @unless ($gd)
            <p class="help" style="margin-top:8px">{{ __('The GD extension is optional. Without it, large images are not resized.') }}</p>
        @endunless
    </div>
    <div class="card-footer">
        @if ($ok)
            <a class="btn" href="{{ route('install.database') }}">{{ __('Continue') }}</a>
        @else
            <a class="btn btn-outline" href="{{ route('install') }}">{{ __('Check again') }}</a>
        @endif
    </div>
@endsection
