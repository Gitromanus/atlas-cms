<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Определяет контекст магазина для пользователя админ-панели.
 *
 * Владелец магазина (и супер-админ с tenant_id) видит данные своего тенанта.
 * Без tenant_id контекст не задаётся — ресурсы должны это переживать без 500.
 */
class SetFilamentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->tenant_id !== null) {
            $tenant = $user->tenant;
            if ($tenant !== null) {
                app(TenantContext::class)->set($tenant);
            }
        }

        return $next($request);
    }
}
