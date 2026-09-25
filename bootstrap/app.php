<?php

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
        // Определение магазина (path /{slug} или свой домен) для всех веб-запросов
        $middleware->web(append: [
            ResolveTenant::class,
        ]);

        $middleware->alias([
            'tenant' => EnsureTenant::class,
        ]);

        // Обмен с 1С: CSRF не применяется (Basic Auth / session_id)
        $middleware->validateCsrfTokens(except: [
            '1c/*',
            '*/1c/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
