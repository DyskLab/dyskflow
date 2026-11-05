<div class="sheet-header">
    <h2 class="sheet-title">{{ __('Turn on two-step verification') }}</h2>
    <p class="muted small">{{ __('Scan the QR code with an authenticator app such as Google Authenticator, Authy or 1Password.') }}</p>
</div>
<form class="sheet-form" method="post" action="{{ route('admin.account.security.confirm') }}">
    @csrf
    <div class="sheet-content">
        <div class="totp-qr">{!! $qr !!}</div>
        <div class="field">
            <span class="label">{{ __('Or enter this key by hand') }}</span>
            <code class="totp-secret">{{ $secret }}</code>
        </div>
        <div class="field">
            <label class="label" for="totp-code">{{ __('Code from the app') }} <span class="muted">*</span></label>
            <input class="input" id="totp-code" name="code" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" maxlength="6" required>
        </div>
        @include('admin.partials.field', ['field' => ['name' => 'password', 'label' => __('Current password'), 'type' => 'password', 'required' => true], 'value' => null])
    </div>
    <div class="sheet-footer">
        <button class="btn btn-outline" type="button" data-sheet-close>{{ __('Cancel') }}</button>
        <button class="btn" type="submit">{{ __('Turn on') }}</button>
    </div>
</form>
