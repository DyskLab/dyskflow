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
                <h1 class="card-title">{{ setting('site_title', 'CMS') }}</h1>
                <p class="muted">{{ __('Log in to manage your site.') }}</p>
            </div>
            <div class="card-content stack">
                @if ($errors->any())<div class="alert alert-danger" role="alert" data-error-summary>{{ $errors->first() }}</div>@endif
                <div class="field"><label class="label" for="email">{{ __('Email') }}</label><input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required data-autofocus autocomplete="email" spellcheck="false"></div>
                <div class="field"><label class="label" for="password">{{ __('Password') }}</label><input class="input" id="password" type="password" name="password" required autocomplete="current-password"></div>
                <label class="check-row"><input class="checkbox" type="checkbox" name="remember" value="1"> {{ __('Remember me') }}</label>
                <button class="btn btn-block" type="submit">{{ __('Log in') }}</button>
            </div>
        </form>
    </div>
</div>
@include('admin.partials.form-errors')
<script src="{{ asset_v('js/admin.js') }}"></script>
</body>
</html>
