@extends('install.layout')

@section('content')
    <form method="post">
        @csrf
        <div class="card-content stack">
            <div class="field"><label class="label" for="site_title">{{ __('Site title') }}</label><input class="input" id="site_title" name="site_title" value="{{ old('site_title') }}" required></div>
            <div class="field"><label class="label" for="tagline">{{ __('Tagline') }}</label><input class="input" id="tagline" name="tagline" value="{{ old('tagline') }}"></div>
            <div class="field"><label class="label" for="site_url">{{ __('Site address (URL)') }}</label><input class="input" id="site_url" type="url" name="site_url" value="{{ old('site_url', $siteUrl) }}" required></div>
            <div class="field">
                <label class="label" for="timezone">{{ __('Timezone') }}</label>
                <select class="select" id="timezone" name="timezone">
                    @foreach (timezone_identifiers_list() as $zone)
                        <option value="{{ $zone }}" @selected(old('timezone', 'Asia/Jakarta') === $zone)>{{ $zone }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="label" for="locale">{{ __('Default language') }}</label>
                <select class="select" id="locale" name="locale">
                    <option value="id" @selected(old('locale', app()->getLocale()) === 'id')>Bahasa Indonesia</option>
                    <option value="en" @selected(old('locale', app()->getLocale()) === 'en')>English</option>
                </select>
            </div>
            <hr style="width:100%;border:0;border-top:1px solid var(--border);margin:0">
            <div class="field"><label class="label" for="name">{{ __('Administrator name') }}</label><input class="input" id="name" name="name" value="{{ old('name') }}" required autocomplete="name"></div>
            <div class="field"><label class="label" for="email">{{ __('Email') }}</label><input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" spellcheck="false"></div>
            <div class="field"><label class="label" for="password">{{ __('Password') }}</label><input class="input" id="password" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
            <div class="field"><label class="label" for="password_confirmation">{{ __('Confirm password') }}</label><input class="input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
        </div>
        <div class="card-footer">
            <a class="btn btn-outline" href="{{ route('install.database') }}">{{ __('Back') }}</a>
            <button class="btn" type="submit">{{ __('Install') }}</button>
        </div>
    </form>
@endsection
