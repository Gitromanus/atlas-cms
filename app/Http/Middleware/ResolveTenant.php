<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Определяет текущий магазин (тенанта) по домену запроса.
 *
 * Поддерживаются:
 *  - собственные домены магазинов (таблица tenant_domains);
 *  - поддомены платформы вида {subdomain}.{root_domain}.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $tenant = null;

        // 1. Собственный (кастомный) домен магазина
        $domain = TenantDomain::query()->where('domain', $host)->first();

        if ($domain !== null) {
            $tenant = $domain->tenant;
        }

        // 2. Поддомен корневого домена платформы
        if ($tenant === null) {
            $root = strtolower((string) config('atlas.root_domain'));

            if ($root !== '' && str_ends_with($host, '.'.$root)) {
                $subdomain = substr($host, 0, -strlen('.'.$root));

                if ($subdomain !== '' && ! str_contains($subdomain, '.')) {
                    $tenant = Tenant::query()
                        ->where('subdomain', $subdomain)
                        ->where('is_active', true)
                        ->with('theme')
                        ->first();
                }
            }
        }

        app(TenantContext::class)->set($tenant);

        return $next($request);
    }
}