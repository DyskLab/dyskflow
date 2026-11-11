<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Before installing there is no users table: an old "remember me" cookie must not be looked up.
        $installed = cms_installed();
        $locale = session('locale')
            ?: ($installed ? $request->user()?->locale : null)
            ?: ($installed ? setting('locale', 'id') : 'id');

        app()->setLocale(in_array($locale, ['en', 'id'], true) ? $locale : 'id');

        return $next($request);
    }
}
