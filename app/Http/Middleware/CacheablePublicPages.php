<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A first-time visitor needs no cookie unless the page has a form with a CSRF token,
 * such as the comment form. Without cookies, a CDN or proxy may cache the page.
 */
class CacheablePublicPages
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->applies($request) || $this->hasCsrfToken($response)) {
            return $response;
        }

        foreach ($response->headers->getCookies() as $cookie) {
            if (in_array($cookie->getName(), [config('session.cookie'), 'XSRF-TOKEN'], true)) {
                $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }
        }

        if ($response->getStatusCode() === 200 && ! $response->headers->hasCacheControlDirective('public')) {
            // Browsers revalidate, so changing language or logging in shows at once.
            $response->headers->set('Cache-Control', 'public, max-age=0, s-maxage=300');
            $response->setVary('Cookie', false);
        }

        return $response;
    }

    private function applies(Request $request): bool
    {
        return $request->isMethodCacheable()
            && cms_installed()
            && ! $request->cookies->has(config('session.cookie'))
            && ! $request->is('admin', 'admin/*', 'install', 'install/*');
    }

    private function hasCsrfToken(Response $response): bool
    {
        $content = $response->getContent();

        return is_string($content) && (str_contains($content, 'name="_token"') || str_contains($content, 'name="csrf-token"'));
    }
}
