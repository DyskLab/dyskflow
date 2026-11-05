@extends('admin.layout')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ $title }}</h1>
            <p class="muted">{{ __('Code comes from the latest release of your public repository. Dependencies (vendor) are replaced only when composer.lock changes.') }}</p>
        </div>
    </div>

    <form class="card form-narrow" method="post" action="{{ route('admin.settings.update.repo') }}">
        @csrf
        <div class="card-content field">
            <label class="label" for="update_repo">{{ __('Public GitHub repository') }}</label>
            <input class="input" id="update_repo" name="update_repo" value="{{ old('update_repo', $repo) }}" placeholder="username/template-cms">
        </div>
        <div class="card-footer"><button class="btn btn-outline" type="submit">{{ __('Save') }}</button></div>
    </form>

    <div class="card form-narrow">
        <div class="card-header">
            <h2 class="card-title">{{ __('Current version: :version', ['version' => config('cms.version')]) }}</h2>
        </div>
        <div class="card-content stack-sm">
            @if ($error)
                <div class="alert alert-danger" role="alert">{{ $error }}</div>
            @elseif ($result && $result['available'])
                <p><span class="badge badge-warning">{{ __('Update available') }}</span> <strong>{{ $result['latest'] }}</strong></p>
                @if ($result['notes'])<pre class="mono muted" style="white-space:pre-wrap;margin:0">{{ $result['notes'] }}</pre>@endif
                <p class="help">{{ __('A database backup is made first. The landing, plugins and uploads folders and .env are never changed.') }}</p>
            @elseif ($result)
                <p><span class="badge badge-success">{{ __('You are up to date') }}</span> {{ __('Latest release: :version', ['version' => $result['latest']]) }}</p>
            @else
                <p class="muted">{{ __('Check whether a new version has been released.') }}</p>
            @endif
        </div>
        <div class="card-footer">
            <a class="btn btn-outline" href="{{ route('admin.settings.update.index', ['check' => 1]) }}">{{ icon('refresh') }} {{ __('Check for updates') }}</a>
            @if ($result && $result['available'])
                <button class="btn" type="button" data-post-url="{{ route('admin.settings.update.run') }}" data-confirm="{{ __('Update to version :version now?', ['version' => $result['latest']]) }}">{{ __('Update now') }}</button>
            @endif
        </div>
    </div>
@endsection
