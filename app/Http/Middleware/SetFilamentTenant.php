<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Определяет контекст магазина для пользователя админ-панели.
 *
 * Владелец магазина видит только данные своего тенанта.
 * Супер-админ платформы управляет всеми магазинами.
 */
class SetFilamentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isSuperAdmin() && $user->tenant_id !== null) {
            app(TenantContext::class)->set($user->tenant);
        }

        return $next($request);
    }
}