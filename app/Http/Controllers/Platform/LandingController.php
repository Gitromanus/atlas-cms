<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Tenant\TenantContext;
use Illuminate\Http\RedirectResponse;

class LandingController extends Controller
{
    public function index(): RedirectResponse
    {
        $tenant = app(TenantContext::class)->resolveSingle();

        if ($tenant === null) {
            return redirect('/shop');
        }

        $slug = $tenant->slug ?: $tenant->subdomain ?: 'shop';

        return redirect('/'.$slug);
    }
}
