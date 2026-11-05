<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Notifier;
use App\Support\Plugins;
use App\Support\SettingsSchema;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(fn (User $user, string $ability) => $user->hasCapability($ability) ?: null);

        if (! cms_installed()) {
            return;
        }

        Event::listen(MessageLogged::class, [Notifier::class, 'logged']);

        // A database that is briefly down should show an error page, not break artisan or routing.
        try {
            SettingsSchema::applyRuntime();
            Plugins::boot();
        } catch (Throwable $e) {
            Log::error('CMS boot failed: '.$e->getMessage());
        }

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
