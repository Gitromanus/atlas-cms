<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\HomeController;
use App\Services\Tenant\TenantContext;
use Illuminate\View\View;

/**
 * Главная страница платформы.
 *
 * На поддомене магазина (тенант определён) — витрина магазина.
 * На основном домене — лендинг SaaS-платформы с описанием
 * и кнопкой создания магазина.
 */
class LandingController extends Controller
{
    public function index(): View
    {
        if (app(TenantContext::class)->has()) {
            return app(HomeController::class)->index();
        }

        return view('platform.landing');
    }
}