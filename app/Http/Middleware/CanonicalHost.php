<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Visits to the www. or bare form of the site's domain move to the form in the site address
 * (Settings > General), with a permanent redirect that keeps the path and query.
 * Other hosts, such as a test subdomain or a local copy, are left alone, and so are form posts.
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $site = parse_url((string) config('app.url'));
        $host = strtolower($site['host'] ?? '');
        $visited = strtolower($request->getHost());

        if ($host !== '' && $visited !== $host && $request->isMethodSafe() && ($visited === 'www.'.$host || 'www.'.$visited === $host)) {
            return redirect()->away(($site['scheme'] ?? $request->getScheme()).'://'.$host.(isset($site['port']) ? ':'.$site['port'] : '').$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
