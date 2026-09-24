<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Tenant\TenantContext;
use Illuminate\Http\Response;

/**
 * Динамический CSS витрины.
 *
 * Генерирует CSS-переменные дизайна из настроек магазина:
 * цвета, шрифты и другие параметры, которые владелец меняет в админке.
 */
class ThemeCssController extends Controller
{
    public function __invoke(): Response
    {
        $tenant = app(TenantContext::class)->current();
        $settings = $tenant?->settings ?? [];

        $primary = $settings['design']['primary_color'] ?? '#4f46e5';
        $accent = $settings['design']['accent_color'] ?? '#0f172a';
        $radius = $settings['design']['radius'] ?? '0.75rem';
        $font = $settings['design']['font_family'] ?? "'Inter', 'Segoe UI', system-ui, sans-serif";

        $css = <<<CSS
        :root {
          --color-primary: {$primary};
          --color-accent: {$accent};
          --radius: {$radius};
          --font-family: {$font};
        }
        CSS;

        return response($css, 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}