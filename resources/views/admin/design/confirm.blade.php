@extends('admin.layout')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ $title }}</h1>
            <p class="muted">{{ __('PHP, Blade and SVG run on the server or in visitors\' browsers. Confirm it is you. This lasts :minutes minutes.', ['minutes' => \App\Http\Controllers\Admin\FileController::CONFIRM_MINUTES]) }}</p>
        </div>
    </div>
    <form class="card" method="post" action="{{ route('admin.design.confirm') }}" style="max-width:420px">
        @csrf
        <input type="hidden" name="return" value="{{ $return }}">
        <div class="card-content stack">
            <div class="field">
                @if ($totp)
                    <label class="label" for="secret">{{ __('Code from your authenticator app') }}</label>
                    <input class="input" id="secret" name="secret" required data-autofocus autocomplete="one-time-code" inputmode="numeric" spellcheck="false" autocapitalize="off">
                @else
                    <label class="label" for="secret">{{ __('Your password') }}</label>
                    <input class="input" id="secret" type="password" name="secret" required data-autofocus autocomplete="current-password">
                @endif
            </div>
        </div>
        <div class="card-footer"><button class="btn" type="submit">{{ __('Confirm') }}</button></div>
    </form>
@endsection
