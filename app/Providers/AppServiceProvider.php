<?php

namespace App\Providers;

use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\ResolveTenant;
use App\Services\Tenant\TenantContext;
use App\View\ThemeViewFinder;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Контекст текущего магазина — синглтон на весь запрос
        $this->app->singleton(TenantContext::class);

        // Поиск Blade-шаблонов с учётом активной темы витрины
        $this->app->singleton('view.finder', function ($app) {
            return new ThemeViewFinder($app['files'], $app['config']['view.paths']);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Текущий магазин доступен во всех представлениях
        View::composer('*', function ($view) {
            $view->with('currentTenant', app(TenantContext::class)->current());
        });
    }
}
