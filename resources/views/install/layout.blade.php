<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    @include('admin.partials.head', ['title' => $title])
</head>
<body>
<div class="center-page">
    <div class="center-box wide">
        <div class="row between">
            <div class="row">@include('admin.partials.logo', ['tagline' => true])<span class="muted small">· {{ __('Install wizard') }}</span></div>
            <div class="row">@include('admin.partials.top-controls')</div>
        </div>
        <div class="steps" role="progressbar" aria-valuemin="1" aria-valuemax="4" aria-valuenow="{{ $step }}">
            @foreach (range(1, 4) as $number)<span class="step {{ $number <= $step ? 'done' : '' }}"></span>@endforeach
        </div>
        @if (session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert" data-error-summary>@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif
        <div class="card">
            <div class="card-header"><h1 class="card-title">{{ $title }}</h1></div>
            @yield('content')
        </div>
    </div>
</div>
@include('admin.partials.form-errors')
<script src="{{ asset('js/admin.js') }}?v={{ config('cms.version') }}"></script>
@stack('scripts')
</body>
</html>
