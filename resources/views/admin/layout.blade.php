@php
    $user = auth()->user();
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    @include('admin.partials.head', ['title' => ($title ?? '').' · '.setting('site_title', 'CMS')])
</head>
{{-- A page can ask for a body class, such as the full-screen builder: @section('body_class', 'bld-full') --}}
<body class="@yield('body_class')">
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
<div class="shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('admin.dashboard') }}" aria-label="DyskFlow">
            @include('admin.partials.logo')
        </a>
        <nav class="nav" aria-label="{{ __('Main menu') }}">
            {{-- A settings page with its own sidebar item (Colors & fonts) lights that item, not Settings too. --}}
            @php $menuMatched = false; @endphp
            @foreach (\App\Support\AdminMenu::groups() as $groupLabel => $items)
                <div class="nav-group">
                    @if ($groupLabel !== '')<div class="nav-label">{{ $groupLabel }}</div>@endif
                    @foreach ($items as $item)
                        @php $itemActive = request()->is(...(array) ($item['match'] ?? '')); $menuMatched = $menuMatched || $itemActive; @endphp
                        <a class="nav-item {{ $itemActive ? 'active' : '' }}" href="{{ $item['url'] }}" title="{{ $item['label'] }}" @if ($itemActive) aria-current="page" @endif>
                            {{ icon($item['icon'] ?? 'plug') }} <span class="nav-text">{{ $item['label'] }}</span>
                            @if (! empty($item['count']))<span class="nav-count">{{ $item['count'] }}</span>@endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
        <div class="sidebar-foot">
            <nav class="nav" style="padding:0" aria-label="{{ __('System') }}">
                <a class="nav-item {{ request()->is('admin/settings*') && ! $menuMatched ? 'active' : '' }}" href="{{ route('admin.settings.index') }}" title="{{ __('Settings') }}" @if (request()->is('admin/settings*') && ! $menuMatched) aria-current="page" @endif>{{ icon('settings') }} <span class="nav-text">{{ __('Settings') }}</span></a>
                <a class="nav-item" href="{{ url('/') }}" target="_blank" rel="noopener" title="{{ __('View site') }}">{{ icon('external') }} <span class="nav-text">{{ __('View site') }}</span></a>
            </nav>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <button class="btn btn-ghost btn-icon btn-sm menu-toggle" type="button" aria-label="{{ __('Minimize or expand the sidebar') }}" title="{{ __('Minimize or expand the sidebar') }}" aria-expanded="true">{{ icon('panel') }}</button>
            @include('admin.partials.global-search')
            <div class="topbar-actions">
                @include('admin.partials.notifications')
                @include('admin.partials.top-controls')
                <span class="topbar-sep" aria-hidden="true"></span>
                <a class="topbar-user" href="{{ route('admin.account.security.index') }}" title="{{ __('Account security') }}">
                    <span class="topbar-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                    <span class="topbar-user-name truncate">{{ $user->name }}</span>
                </a>
                <form method="post" action="{{ route('logout') }}">@csrf
                    <button class="btn btn-ghost btn-icon btn-sm" type="submit" aria-label="{{ __('Log out') }}" title="{{ __('Log out') }}">{{ icon('logout') }}</button>
                </form>
            </div>
        </header>
        <main class="content" id="main" tabindex="-1">
            @include('admin.partials.breadcrumb')
            @if (! $user->hasTotp() && ! request()->routeIs('admin.account.security.*'))
                <div class="alert alert-warning totp-alert" role="status">
                    <span>{{ icon('shield') }} {{ __('Your account is protected by a password only. Turn on two-step verification to keep it safe.') }}</span>
                    <a class="btn btn-sm btn-outline" href="{{ route('admin.account.security.index') }}">{{ __('Turn on') }}</a>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger" role="alert" data-error-summary>
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            @yield('content')
        </main>
        <footer class="admin-footer">
            <span>DyskFlow {{ config('cms.version') }}</span>
            <span>{{ __('Built by') }} <a href="https://dysklab.fanny.dev" target="_blank" rel="noopener">DyskLab</a> · <a href="https://buymeacoffee.com/fannydevz" target="_blank" rel="noopener">{{ __('Support DyskFlow') }}</a></span>
        </footer>
    </div>
</div>

{{-- Right sheet, filled from the server on demand --}}
<div class="sheet-backdrop" id="sheet-backdrop"></div>
<aside class="sheet" id="sheet" role="dialog" aria-modal="true" aria-hidden="true">
    <button class="btn btn-ghost btn-icon btn-sm sheet-close" type="button" data-sheet-close aria-label="{{ __('Close') }}">{{ icon('x') }}</button>
    <div class="sheet-body" id="sheet-body"></div>
</aside>

{{-- Confirmation modal for delete and other data-confirm actions --}}
<div class="modal-backdrop" id="confirm-backdrop"></div>
<div class="modal" id="confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message">
    <h2 class="modal-title" id="confirm-title">{{ __('Are you sure?') }}</h2>
    <p class="muted" id="confirm-message"></p>
    <div class="modal-footer">
        <button class="btn btn-outline" type="button" data-confirm-close>{{ __('Cancel') }}</button>
        <button class="btn" type="button" id="confirm-yes">{{ __('Continue') }}</button>
    </div>
</div>

<form method="post" id="post-form" hidden>@csrf</form>

<div class="toasts" aria-live="polite">
    @if (session('success'))<div class="toast">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="toast toast-error">{{ session('error') }}</div>@endif
</div>

<script>
window.CMS_I18N = {!! json_encode(['loading' => __('Loading…'), 'loadFailed' => __('Could not load. Please try again.'), 'uploading' => __('Uploading…'), 'noResults' => __('No results.'),
    'uploaded' => __('Uploaded'), 'failed' => __('Failed'), 'processing' => __('Processing…'), 'uploadFailed' => __('Upload failed.'),
    'typeNotAllowed' => __('File type :type is not allowed. Allowed: :types.'), 'tooLarge' => __('This file is :size, larger than the :max limit.'),
    'tooLargeHost' => __('This upload is larger than this hosting accepts. Upload a smaller file, or ask your host to raise post_max_size.'),
    'sessionExpired' => __('Your session expired. Reload the page and try again.'), 'noPermission' => __('You do not have permission to upload files.'),
    'serverError' => __('The server could not process this file. Check the error log, or try a smaller file.'), 'network' => __('The connection was lost. Check your internet and try again.')], JSON_HEX_TAG) !!};
window.CMS_MEDIA_PICKER = @json($user->can('media.manage') ? route('admin.media.picker') : null);
</script>
@stack('scripts')
@include('admin.partials.form-errors')
<script src="{{ asset_v('js/admin.js') }}"></script>
</body>
</html>
