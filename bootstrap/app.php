<?php

use App\Http\Middleware\CacheablePublicPages;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Support\MediaUploader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Support\Number;

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
        $middleware->validateCsrfTokens(except: ['locale/*', 'form']);
        // File and builder content is saved exactly as typed: spaces and empty strings are content there.
        $verbatim = fn (Request $request) => $request->is('admin/design/files/save', 'admin/pages/*/builder/*', 'admin/design/templates/*/builder/*', 'admin/design/blocks/*/builder/*', 'admin/design/blocks/library');
        $middleware->trimStrings(except: [$verbatim]);
        $middleware->convertEmptyStringsToNull(except: [$verbatim]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Uploads larger than post_max_size arrive empty; the uploader shows this instead of a bare 413.
        $exceptions->render(fn (PostTooLargeException $e, Request $request) => $request->expectsJson()
            ? response()->json(['saved' => [], 'errors' => [], 'message' => __('This upload is larger than this hosting accepts (:max). Upload fewer or smaller files, or ask your host to raise post_max_size.', [
                'max' => Number::fileSize(MediaUploader::iniBytes((string) ini_get('post_max_size'))),
            ])], 413)
            : null);
    })->create();
