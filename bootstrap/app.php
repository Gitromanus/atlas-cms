<?php

use App\Http\Middleware\CaptureUtm;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(prepend: [
            ResolveTenant::class,
        ]);
        $middleware->web(append: [
            CaptureUtm::class,
        ]);

        $middleware->alias([
            'tenant' => EnsureTenant::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '1c/*',
            '*/1c/*',
            'payment/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
