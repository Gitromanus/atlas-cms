<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetFilamentTenant;
use App\Services\Tenant\TenantContext;
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
 * Админ-панель предпринимателя (владельца магазина).
 *
 * Здесь владелец управляет СВОИМ магазином: каталог, заказы, покупатели,
 * настройки (тема, обмен с 1С). Данные изолированы тенантом.
 */
class ShopPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::USER_MENU_BEFORE,
            fn (): string => view('filament.partials.open-site', [
                'url' => $this->shopUrl(),
            ])->render(),
        );
    }

    /**
     * Ссылка на витрину текущего магазина владельца.
     */
    protected function shopUrl(): string
    {
        $user = auth()->user();

        if ($user === null) {
            return '#';
        }

        // В контексте панели /shop тенант — магазин владельца (либо контекст запроса)
        $tenant = $user->tenant ?? app(TenantContext::class)->current();

        return $tenant?->url() ?? '#';
    }
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('shop')
            ->path('shop')
            ->login()
            ->brandName('AtlasCMS')
            ->colors([
                'primary' => Color::Indigo,
            ])
            // Тёмная тема панели (по системным настройкам) — согласуется с тёмными темами витрин
            ->darkMode(true)
            ->discoverResources(in: app_path('Filament/Shop/Resources'), for: 'App\\Filament\\Shop\\Resources')
            ->discoverPages(in: app_path('Filament/Shop/Pages'), for: 'App\\Filament\\Shop\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Shop/Widgets'), for: 'App\\Filament\\Shop\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                \App\Filament\Shop\Widgets\ShopStats::class,
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
                // Контекст магазина владельца (изоляция данных)
                SetFilamentTenant::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}