{{-- Generic list page. Vars: title, description, table, actions (array). --}}
@extends('admin.layout')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ $title }}</h1>
            @isset($description)<p class="muted">{{ $description }}</p>@endisset
        </div>
        @isset($actions)
            <div class="row">
                @foreach ($actions as $action)
                    @include('admin.partials.page-action', ['action' => $action])
                @endforeach
            </div>
        @endisset
    </div>
    {{ $table->render() }}
@endsection
