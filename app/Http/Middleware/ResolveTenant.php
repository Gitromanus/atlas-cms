<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Определяет текущий магазин (тенанта).
 *
 * Порядок:
 *  1. Собственный домен магазина (tenant_domains) — для будущего «свой домен».
 *  2. Path-режим: первый сегмент URL = slug магазина (/{slug}/…).
 *  3. Поддомен {slug}.{root_domain} — если ATLAS_TENANT_ROUTING=subdomain
 *     или как запасной вариант при wildcard-DNS.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $tenant = null;
        $shopParam = null;

        // 1. Собственный (кастомный) домен магазина
        $domain = TenantDomain::query()->where('domain', $host)->first();

        if ($domain !== null) {
            $tenant = $domain->tenant;
            if ($tenant !== null && $tenant->is_active) {
                $tenant->loadMissing('theme');
            } else {
                $tenant = null;
            }
        }

        // 2. Path: /{slug}/…
        if ($tenant === null) {
            $segments = $request->segments();
            $first = $segments[0] ?? null;
            $reserved = config('atlas.reserved_paths', []);

            if (is_string($first)
                && $first !== ''
                && ! in_array(strtolower($first), $reserved, true)
                && preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/i', $first)
            ) {
                $candidate = Tenant::query()
                    ->where('slug', $first)
                    ->where('is_active', true)
                    ->with('theme')
                    ->first();

                if ($candidate !== null) {
                    $tenant = $candidate;
                    $shopParam = $candidate->slug;
                }
            }
        }

        // 3. Поддомен корневого домена (опционально)
        if ($tenant === null) {
            $root = strtolower((string) config('atlas.root_domain'));

            if ($root !== '' && str_ends_with($host, '.'.$root)) {
                $subdomain = substr($host, 0, -strlen('.'.$root));

                if ($subdomain !== '' && ! str_contains($subdomain, '.')) {
                    $tenant = Tenant::query()
                        ->where(function ($q) use ($subdomain) {
                            $q->where('subdomain', $subdomain)
                                ->orWhere('slug', $subdomain);
                        })
                        ->where('is_active', true)
                        ->with('theme')
                        ->first();

                    if ($tenant !== null) {
                        $shopParam = $tenant->slug;
                    }
                }
            }
        }

        app(TenantContext::class)->set($tenant);

        // Параметр {shop} в именованных маршрутах витрины
        if ($shopParam !== null) {
            URL::defaults(['shop' => $shopParam]);
        }

        return $next($request);
    }
}
