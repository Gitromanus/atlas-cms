<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Контекст магазина для панели /shop.
 * Владелец — по tenant_id; супер-админ — сессия или первый магазин.
 */
class SetFilamentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $tenant = null;

        if ($user->tenant_id !== null) {
            $tenant = $user->relationLoaded('tenant')
                ? $user->tenant
                : $user->tenant()->first();

            if ($tenant === null) {
                $tenant = Tenant::query()->find($user->tenant_id);
            }
        }

        if ($tenant === null && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            $sessionId = $request->session()->get('filament_shop_tenant_id');
            if ($sessionId) {
                $tenant = Tenant::query()->find($sessionId);
            }
            if ($tenant === null) {
                $tenant = Tenant::query()->orderBy('id')->first();
            }
            if ($tenant !== null) {
                $request->session()->put('filament_shop_tenant_id', $tenant->id);
            }
        }

        if ($tenant !== null) {
            app(TenantContext::class)->set($tenant);
        }

        return $next($request);
    }
}
