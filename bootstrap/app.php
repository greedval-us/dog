<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveRequestLocale;
use App\Http\Middleware\SynchronizePetLifecycle;
use App\Modules\Pets\Exceptions\PendingGameEventRegistration;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            ResolveRequestLocale::class,
            SynchronizePetLifecycle::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([PendingGameEventRegistration::class]);
        $exceptions->render(function (PendingGameEventRegistration $exception, Request $request): Response {
            $message = __($exception->getMessage());
            if ($request->header('X-Inertia') && ! $request->isMethodSafe()) {
                Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

                return redirect()->back(303)->withErrors(['event' => $message]);
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 503, ['Retry-After' => '60']);
            }

            return response($message, 503, ['Retry-After' => '60', 'Content-Type' => 'text/plain; charset=UTF-8']);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
