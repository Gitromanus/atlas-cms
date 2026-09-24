<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Order;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $tenantsCount = Tenant::query()->count();
        $activeTenants = Tenant::query()->where('is_active', true)->count();
        $ordersCount = Order::query()->count();
        $revenue = (float) Order::query()->sum('total');

        return [
            Stat::make('Магазинов', $tenantsCount)
                ->description("{$activeTenants} активных")
                ->color('primary'),
            Stat::make('Заказов на платформе', $ordersCount)
                ->description('Во всех магазинах')
                ->color('info'),
            Stat::make('Выручка платформы', number_format($revenue, 0, ',', ' ').' ₽')
                ->description('Сумма всех заказов')
                ->color('success'),
        ];
    }
}