@extends('admin.layout')

@section('content')
    <div class="page-head">
        <div><h1 class="page-title">{{ $title }}</h1></div>
    </div>

    <div class="stack" style="max-width:640px">
        @if ($recoveryCodes)
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ __('Save your recovery codes') }}</h2>
                    <p class="muted">{{ __('Each code works once if you lose your phone. They are shown only now.') }}</p>
                </div>
                <div class="card-content"><ul class="totp-codes">@foreach ($recoveryCodes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul></div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <div class="row between">
                    <h2 class="card-title">{{ __('Two-step verification (TOTP)') }}</h2>
                    @if ($user->hasTotp())
                        <span class="badge badge-success">{{ __('On') }}</span>
                    @else
                        <span class="badge badge-warning">{{ __('Off') }}</span>
                    @endif
                </div>
                <p class="muted">{{ __('Besides the password, logging in asks for a code from an app on your phone.') }}</p>
            </div>
            <div class="card-content">
                <div class="row">
                    @if ($user->hasTotp())
                        <button class="btn btn-outline" type="button" data-sheet-url="{{ route('admin.account.security.codes.form') }}">{{ __('New recovery codes') }}</button>
                        <button class="btn btn-outline" type="button" data-sheet-url="{{ route('admin.account.security.disable.form') }}">{{ __('Turn off') }}</button>
                    @else
                        <button class="btn" type="button" data-sheet-url="{{ route('admin.account.security.setup') }}">{{ __('Turn on') }}</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
