<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Требует наличие активного магазина для запроса.
 * Используется для маршрутов витрины и личного кабинета покупателя.
 */
class EnsureTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(TenantContext::class)->has()) {
            abort(404, 'Магазин не найден');
        }

        return $next($request);
    }
}