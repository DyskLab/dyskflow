@extends('admin.layout')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ $title }}</h1>
            <p class="muted">{{ $schema['description'] }}</p>
        </div>
    </div>

    <form class="card form-narrow" method="post" action="{{ route('admin.settings.update', $section) }}">
        @csrf
        <div class="card-content stack">
            @foreach ($schema['fields'] as $field)
                @include('admin.partials.field', ['field' => $field, 'value' => $values[$field['name']] ?? null])
            @endforeach
        </div>
        <div class="card-footer"><button class="btn" type="submit">{{ __('Save changes') }}</button></div>
    </form>

    @if ($section === 'email')
        <form class="card form-narrow" method="post" action="{{ route('admin.settings.email.test') }}">
            @csrf
            <div class="card-header"><h2 class="card-title">{{ __('Send a test email') }}</h2></div>
            <div class="card-content field">
                <label class="label" for="to">{{ __('Send to') }}</label>
                <input class="input" id="to" type="email" name="to" value="{{ auth()->user()->email }}" required autocomplete="email" spellcheck="false">
            </div>
            <div class="card-footer"><button class="btn btn-outline" type="submit">{{ icon('mail') }} {{ __('Send test') }}</button></div>
        </form>
    @endif
@endsection
