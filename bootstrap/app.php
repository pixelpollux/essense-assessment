<?php

use App\Exceptions\RenderUserFacingErrors;
use App\Http\Middleware\HandleInertiaRequests;
use App\Services\Essense\Exceptions\EssenseMisconfigured;
use App\Services\Essense\Exceptions\EssenseUnavailable;
use App\Services\Essense\Exceptions\GameNotFound;
use App\Services\Library\Exceptions\LibraryRuleViolation;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(new RenderUserFacingErrors);

        // Expected outcomes, not errors: don't fill the log with them. The Essense client already
        // logs its own failures (warning / critical) with context and without secrets.
        $exceptions->dontReport([
            LibraryRuleViolation::class,
            GameNotFound::class,
            EssenseUnavailable::class,
            EssenseMisconfigured::class,
            LockTimeoutException::class,
        ]);
    })->create();
