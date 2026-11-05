<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    private const TOTP_WAIT_SECONDS = 300;

    public function loginForm()
    {
        return view('admin.login', ['title' => __('Log in')]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);

        if (! Auth::validate($credentials + ['is_active' => true])) {
            throw ValidationException::withMessages(['email' => __('Email or password is incorrect.')]);
        }

        /** @var User $user */
        $user = Auth::getLastAttempted();
        Auth::getProvider()->rehashPasswordIfRequired($user, $credentials);

        if ($user->hasTotp()) {
            // Not logged in yet: the password only earns the chance to enter a code.
            $request->session()->regenerate();
            $request->session()->put('login.totp', [
                'id' => $user->id,
                'remember' => $request->boolean('remember'),
                'until' => time() + self::TOTP_WAIT_SECONDS,
            ]);

            return redirect()->route('login.totp');
        }

        return $this->finishLogin($request, $user, $request->boolean('remember'));
    }

    public function totpForm(Request $request)
    {
        if (! $this->pendingTotpUser($request)) {
            return redirect()->route('login');
        }

        return view('admin.login-totp', ['title' => __('Two-step verification')]);
    }

    public function totp(Request $request)
    {
        $user = $this->pendingTotpUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $data = $request->validate(['code' => 'required|string|max:32']);

        // Counted per account, so starting a new session does not give new guesses.
        $key = 'login-totp:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        if (! $user->passesTotp($data['code'])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['code' => __('The code is not valid.')]);
        }

        RateLimiter::clear($key);
        $remember = (bool) $request->session()->pull('login.totp')['remember'];

        return $this->finishLogin($request, $user, $remember);
    }

    public function logout(Request $request)
    {
        activity('logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function locale(Request $request, string $locale)
    {
        session(['locale' => $locale]);
        if (cms_installed()) {
            $request->user()?->update(['locale' => $locale]);
        }

        return back();
    }

    private function finishLogin(Request $request, User $user, bool $remember)
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        activity('login');

        return redirect()->intended(route('admin.dashboard'));
    }

    private function pendingTotpUser(Request $request): ?User
    {
        $pending = $request->session()->get('login.totp');
        if (! $pending || $pending['until'] < time()) {
            return null;
        }

        return User::where('is_active', true)->find($pending['id']);
    }
}
