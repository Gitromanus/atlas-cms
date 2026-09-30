<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Админка /shop — всегда единственный магазин.
 */
class SetFilamentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        app(TenantContext::class)->resolveSingle();

        return $next($request);
    }
}
