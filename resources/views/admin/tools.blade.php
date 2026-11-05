@extends('admin.layout')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ $title }}</h1>
        </div>
    </div>

    <form class="card form-narrow" method="post" action="{{ route('admin.settings.tools.export') }}" data-no-loading>
        @csrf
        <div class="card-header">
            <h2 class="card-title">{{ __('Export') }}</h2>
            <p class="muted">{{ __('Download content as a JSON file. Passwords are never included.') }}</p>
        </div>
        <div class="card-content check-grid">
            @foreach (['articles' => __('Articles'), 'pages' => __('Pages'), 'comments' => __('Comments'), 'settings' => __('Settings')] as $key => $label)
                <label class="check-row"><input class="checkbox" type="checkbox" name="include[]" value="{{ $key }}" @checked($key !== 'settings')> {{ $label }}</label>
            @endforeach
        </div>
        <div class="card-footer"><button class="btn" type="submit">{{ icon('download') }} {{ __('Export') }}</button></div>
    </form>

    <form class="card form-narrow" method="post" action="{{ route('admin.settings.tools.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="card-header">
            <h2 class="card-title">{{ __('Import') }}</h2>
            <p class="muted">{{ __('Items with the same slug are updated, others are added.') }}</p>
        </div>
        <div class="card-content"><input class="input" type="file" name="file" accept=".json,application/json" required aria-label="{{ __('Export file') }}"></div>
        <div class="card-footer"><button class="btn btn-outline" type="submit" data-confirm="{{ __('Existing items with the same slug will be overwritten.') }}">{{ icon('upload') }} {{ __('Import') }}</button></div>
    </form>
@endsection
