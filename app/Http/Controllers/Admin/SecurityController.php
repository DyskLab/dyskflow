<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** A user's own two-step verification. Every change asks for the password again. */
class SecurityController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.security', [
            'title' => __('Account security'),
            'user' => $request->user(),
            'recoveryCodes' => session('recovery_codes'),
        ]);
    }

    public function setup(Request $request)
    {
        abort_if($request->user()->hasTotp(), 404);

        // Kept in the session until confirmed, so a wrong code does not change the QR code to scan.
        $secret = session('totp.pending') ?: Totp::generateSecret();
        session(['totp.pending' => $secret]);

        return view('admin.partials.totp-setup', [
            'secret' => $secret,
            'qr' => Totp::qrSvg(Totp::uri((string) setting('site_title', 'DyskFlow'), $request->user()->email, $secret)),
        ]);
    }

    public function confirm(Request $request)
    {
        $user = $request->user();
        abort_if($user->hasTotp(), 404);
        $data = $request->validate(['password' => 'required|string', 'code' => 'required|string']);
        $this->checkPassword($request, $data['password']);

        $secret = (string) session('totp.pending');
        $step = $secret === '' ? null : Totp::verify($secret, trim($data['code']));
        if ($step === null) {
            throw ValidationException::withMessages(['code' => __('The code is not valid.')]);
        }

        $user->forceFill(['totp_secret' => $secret, 'totp_confirmed_at' => now(), 'totp_last_step' => $step])->save();
        session()->forget('totp.pending');
        activity('user.totp_enabled', $user->email);

        return redirect()->route('admin.account.security.index')
            ->with('recovery_codes', $user->newRecoveryCodes())
            ->with('success', __('Two-step verification is on.'));
    }

    public function disableForm()
    {
        return view('admin.partials.sheet-form', [
            'title' => __('Turn off two-step verification'),
            'description' => __('Your account will be protected by the password alone.'),
            'action' => route('admin.account.security.disable'),
            'submit' => __('Turn off'),
            'fields' => [
                ['name' => 'password', 'label' => __('Current password'), 'type' => 'password', 'required' => true],
                ['name' => 'code', 'label' => __('Code or recovery code'), 'required' => true],
            ],
        ]);
    }

    public function disable(Request $request)
    {
        $data = $request->validate(['password' => 'required|string', 'code' => 'required|string']);
        $this->checkPassword($request, $data['password']);
        if (! $request->user()->passesTotp($data['code'])) {
            throw ValidationException::withMessages(['code' => __('The code is not valid.')]);
        }

        $request->user()->disableTotp();
        activity('user.totp_disabled', $request->user()->email);

        return redirect()->route('admin.account.security.index')->with('success', __('Two-step verification is off.'));
    }

    public function codesForm()
    {
        return view('admin.partials.sheet-form', [
            'title' => __('New recovery codes'),
            'description' => __('The old codes stop working.'),
            'action' => route('admin.account.security.codes'),
            'submit' => __('Create new codes'),
            'fields' => [
                ['name' => 'password', 'label' => __('Current password'), 'type' => 'password', 'required' => true],
            ],
        ]);
    }

    public function codes(Request $request)
    {
        $data = $request->validate(['password' => 'required|string']);
        $this->checkPassword($request, $data['password']);
        abort_unless($request->user()->hasTotp(), 404);
        activity('user.totp_codes_renewed', $request->user()->email);

        return redirect()->route('admin.account.security.index')->with('recovery_codes', $request->user()->newRecoveryCodes());
    }

    private function checkPassword(Request $request, string $password): void
    {
        if (! Hash::check($password, $request->user()->password)) {
            throw ValidationException::withMessages(['password' => __('The password is incorrect.')]);
        }
    }
}
