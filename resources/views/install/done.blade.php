@extends('install.layout')

@section('content')
    <div class="card-content"><p class="muted">{{ __('Your site is ready. You are logged in as administrator.') }}</p></div>
    <div class="card-footer">
        <a class="btn btn-outline" href="{{ url('/') }}">{{ __('View site') }}</a>
        <a class="btn" href="{{ route('admin.dashboard') }}">{{ __('Open dashboard') }}</a>
    </div>
@endsection
