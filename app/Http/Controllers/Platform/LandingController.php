<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Главная страница платформы (лендинг SaaS).
 *
 * Витрина магазина открывается по path /{slug}/ — отдельный маршрут home.
 * Поддомены на бесплатном shared-хостинге не используются.
 */
class LandingController extends Controller
{
    public function index(): View
    {
        return view('platform.landing');
    }
}
