<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Панель владельца SaaS-платформы.
 *
 * Только контроль магазинов: создание, блокировка, тарифы, метрики.
 * Без товаров, заказов и настроек 1С — этим занимается владелец магазина
 * в своей панели /shop.
 */
class PlatformPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::USER_MENU_BEFORE,
            fn (): string => view('filament.partials.open-site', [
                'url' => url('/'),
            ])->render(),
        );
    }
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('platform')
            ->path('platform')
            ->login()
            ->brandName('AtlasCMS Platform')
            ->colors([
                'primary' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Platform/Resources'), for: 'App\\Filament\\Platform\\Resources')
            ->discoverPages(in: app_path('Filament/Platform/Pages'), for: 'App\\Filament\\Platform\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Platform/Widgets'), for: 'App\\Filament\\Platform\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                \App\Filament\Platform\Widgets\PlatformStats::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}