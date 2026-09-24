<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ShopStats extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $ordersCount = Order::query()->count();
        $revenue = (float) Order::query()->sum('total');
        $productsCount = Product::query()->count();
        $newToday = Order::query()->whereDate('created_at', today())->count();

        return [
            Stat::make('Заказов', $ordersCount)
                ->description('Всего в системе')
                ->color('primary'),
            Stat::make('Выручка', number_format($revenue, 0, ',', ' ').' ₽')
                ->description('Сумма всех заказов')
                ->color('success'),
            Stat::make('Товаров', $productsCount)
                ->description('В каталоге')
                ->color('info'),
            Stat::make('Заказов сегодня', $newToday)
                ->description(now()->format('d.m.Y'))
                ->color('warning'),
        ];
    }
}