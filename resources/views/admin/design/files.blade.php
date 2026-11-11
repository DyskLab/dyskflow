@extends('admin.layout')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ $title }}</h1>
            <p class="muted">{{ __('All code in one place: landing pages, the theme, plugins, and the custom CSS and JavaScript. Updates never overwrite these folders.') }}</p>
        </div>
        @if ($actions)
            <div class="row">
                @foreach ($actions as $action)
                    @include('admin.partials.page-action', ['action' => $action])
                @endforeach
            </div>
        @endif
    </div>

    @foreach ($unwritable as $folder)
        <div class="alert alert-warning" role="status">{{ __('The :folder folder is not writable. Check the folder permissions on the server.', ['folder' => $folder.'/']) }}</div>
    @endforeach

    @if ($dir === '')
        <div class="alert" role="note" style="margin-bottom:16px">
            <div>{{ __('Editing PHP or Blade gives the same access as the server itself. Give the Edit code permission only to people you trust.') }}</div>
            @unless ($canCode)
                <div class="muted small">{{ __('You can open styles, scripts and other text files. Templates and PHP need the Edit code permission.') }}</div>
            @endunless
        </div>
    @endif

    @include('admin.design.path', ['crumbs' => $crumbs])
    {{ $table->render() }}
@endsection
