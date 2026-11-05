<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $onInstaller = $request->is('install') || $request->is('install/*');

        if (! cms_installed() && ! $onInstaller && ! $request->is('locale/*')) {
            return redirect('install');
        }
        // /install/done is still allowed so the wizard can show its final step.
        if (cms_installed() && $onInstaller && ! $request->is('install/done')) {
            abort(404);
        }

        return $next($request);
    }
}
