{{-- DyskFlow logo. Vars: tagline (bool). --}}
<span class="logo">
    <img class="logo-mark" src="{{ asset('img/logo-mark.svg') }}" alt="" width="28" height="28">
    <span class="logo-text">
        <span class="logo-word">Dysk<span class="logo-accent">Flow</span></span>
        @if (! empty($tagline))<span class="logo-tagline">{{ __('Laravel-based CMS') }}</span>@endif
    </span>
</span>
