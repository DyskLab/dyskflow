<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale')
            ?: $request->user()?->locale
            ?: (cms_installed() ? setting('locale', 'id') : 'id');

        app()->setLocale(in_array($locale, ['en', 'id'], true) ? $locale : 'id');

        return $next($request);
    }
}
