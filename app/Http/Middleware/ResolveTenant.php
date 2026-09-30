<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single-shop: всегда один магазин. Чужие slug → редирект на канонический.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);
        $tenant = $context->resolveSingle();

        if ($tenant === null) {
            return $next($request);
        }

        $slug = $tenant->slug ?: $tenant->subdomain ?: 'shop';
        URL::defaults(['shop' => $slug]);

        // /kds/... → /shop/... (один магазин)
        $routeShop = $request->route('shop');
        if (is_string($routeShop) && $routeShop !== '' && strcasecmp($routeShop, $slug) !== 0) {
            $segments = $request->segments();
            $segments[0] = $slug;
            $target = '/'.implode('/', $segments);
            $qs = $request->getQueryString();
            if ($qs) {
                $target .= '?'.$qs;
            }

            return redirect()->to($target, 301);
        }

        return $next($request);
    }
}
