<?php

use App\Http\Middleware\CacheablePublicPages;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Prepended so they run after the session and CSRF cookies are added.
        $middleware->web(
            append: [EnsureInstalled::class, EnsureActiveUser::class, SetLocale::class],
            prepend: [SecurityHeaders::class, CacheablePublicPages::class],
        );
        // Choosing a language changes nothing but a preference, and public pages stay free of tokens.
        $middleware->validateCsrfTokens(except: ['locale/*']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
