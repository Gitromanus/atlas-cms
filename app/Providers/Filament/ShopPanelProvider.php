<?php

namespace App\Providers\Filament;

use App\Filament\Shop\Widgets\RecentOrders;
use App\Filament\Shop\Widgets\ShopStats;
use App\Http\Middleware\SetFilamentTenant;
use App\Models\Tenant;
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

    protected function shopUrl(): string
    {
        $user = auth()->user();

        if ($user === null) {
            return '#';
        }

        $tenant = $user->relationLoaded('tenant')
            ? $user->tenant
            : $user->tenant()->first();

        if ($tenant === null && $user->tenant_id) {
            $tenant = Tenant::query()->find($user->tenant_id);
        }

        if ($tenant === null) {
            $tenant = app(TenantContext::class)->current();
        }

        if ($tenant === null) {
            return '#';
        }

        $slug = $tenant->slug ?: $tenant->subdomain;

        if ($slug === null || $slug === '') {
            return '#';
        }

        return url('/'.$slug);
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
            ->darkMode(true)
            ->discoverResources(in: app_path('Filament/Shop/Resources'), for: 'App\Filament\Shop\Resources')
            ->discoverPages(in: app_path('Filament/Shop/Pages'), for: 'App\Filament\Shop\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Shop/Widgets'), for: 'App\Filament\Shop\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                ShopStats::class,
                RecentOrders::class,
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
                SetFilamentTenant::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
