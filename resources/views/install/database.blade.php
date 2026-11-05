@extends('install.layout')

@section('content')
    <form method="post">
        @csrf
        <div class="card-content stack">
            <div class="field">
                <label class="label" for="driver">{{ __('Database type') }}</label>
                <select class="select" id="driver" name="driver">
                    <option value="mysql" @selected(old('driver', $db['driver'] ?? 'mysql') === 'mysql')>MySQL / MariaDB</option>
                    <option value="sqlite" @selected(old('driver', $db['driver'] ?? '') === 'sqlite')>SQLite ({{ __('no setup needed') }})</option>
                </select>
            </div>
            <div class="stack" id="mysql-fields">
                <div class="field"><label class="label" for="host">{{ __('Host') }}</label><input class="input" id="host" name="host" value="{{ old('host', $db['host'] ?? 'localhost') }}"></div>
                <div class="field"><label class="label" for="port">{{ __('Port') }}</label><input class="input" id="port" type="number" name="port" value="{{ old('port', $db['port'] ?? 3306) }}"></div>
                <div class="field"><label class="label" for="database">{{ __('Database name') }}</label><input class="input" id="database" name="database" value="{{ old('database', $db['database'] ?? '') }}"></div>
                <div class="field"><label class="label" for="username">{{ __('Username') }}</label><input class="input" id="username" name="username" value="{{ old('username', $db['username'] ?? '') }}" autocomplete="off"></div>
                <div class="field"><label class="label" for="password">{{ __('Password') }}</label><input class="input" id="password" type="password" name="password" autocomplete="new-password"></div>
            </div>
        </div>
        <div class="card-footer">
            <a class="btn btn-outline" href="{{ route('install') }}">{{ __('Back') }}</a>
            <button class="btn" type="submit">{{ __('Test and continue') }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    var driver = document.getElementById('driver'), box = document.getElementById('mysql-fields');
    var sync = function () { box.hidden = driver.value !== 'mysql'; };
    driver.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
