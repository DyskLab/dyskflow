<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    @include('admin.partials.head', ['title' => $title.' · '.setting('site_title', 'CMS')])
</head>
<body>
<div class="center-page">
    <div class="center-box" style="max-width:380px">
        <div class="row between">@include('admin.partials.logo', ['tagline' => true])<div class="row">@include('admin.partials.top-controls')</div></div>
        <form class="card" method="post">
            @csrf
            <div class="card-header">
                <h1 class="card-title">{{ $title }}</h1>
                <p class="muted">{{ __('Enter the code from your authenticator app, or a recovery code.') }}</p>
            </div>
            <div class="card-content stack">
                @if ($errors->any())<div class="alert alert-danger" role="alert" data-error-summary>{{ $errors->first() }}</div>@endif
                <div class="field"><label class="label" for="code">{{ __('Code') }}</label><input class="input" id="code" name="code" required data-autofocus autocomplete="one-time-code" spellcheck="false" autocapitalize="off"></div>
                <button class="btn btn-block" type="submit">{{ __('Continue') }}</button>
                <a class="btn btn-ghost btn-block" href="{{ route('login') }}">{{ __('Log in again') }}</a>
            </div>
        </form>
    </div>
</div>
@include('admin.partials.form-errors')
<script src="{{ asset('js/admin.js') }}?v={{ config('cms.version') }}"></script>
</body>
</html>
