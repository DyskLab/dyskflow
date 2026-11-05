<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cms:totp-reset {email}', function (string $email) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        $this->error('No user with that email.');

        return 1;
    }

    $user->disableTotp();
    activity('user.totp_disabled', $user->email);
    $this->info('Two-step verification is off for '.$user->email.'.');

    return 0;
})->purpose('Turn off two-step verification for a locked-out user');
