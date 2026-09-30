<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single-shop: всегда один магазин на весь сайт.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);
        $tenant = $context->resolveSingle();

        if ($tenant !== null) {
            $slug = $tenant->slug ?: $tenant->subdomain ?: 'shop';
            URL::defaults(['shop' => $slug]);
        }

        return $next($request);
    }
}
