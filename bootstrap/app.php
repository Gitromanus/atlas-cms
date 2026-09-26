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

        // Гости личного кабинета покупателя → логин витрины (не route('login'), его нет)
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            $shop = $request->route('shop');
            if (! $shop) {
                $segments = $request->segments();
                $first = $segments[0] ?? null;
                $reserved = config('atlas.reserved_paths', []);
                if (is_string($first) && $first !== '' && ! in_array(strtolower($first), $reserved, true)) {
                    $shop = $first;
                }
            }
            if ($shop) {
                return route('customer.login', ['shop' => $shop]);
            }

            return url('/shop/login');
        });

        $middleware->validateCsrfTokens(except: [
            '1c/*',
            '*/1c/*',
            'payment/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
