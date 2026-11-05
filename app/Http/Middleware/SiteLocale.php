<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** On public pages the URL alone decides the language, so every address has one language and no cookie is needed. */
class SiteLocale
{
    public function handle(Request $request, Closure $next, ?string $prefix = null): Response
    {
        if ($prefix && Locales::isDefault($prefix)) {
            // The default language has one address: without the prefix.
            $query = $request->getQueryString();

            return redirect(url(substr($request->path(), strlen($prefix) + 1) ?: '/').($query ? '?'.$query : ''), 301);
        }

        app()->setLocale($prefix ?? Locales::default());

        return $next($request);
    }
}
