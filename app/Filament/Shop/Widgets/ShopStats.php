<?php

namespace App\Filament\Shop\Widgets;

use App\Models\Order;
use App\Models\OrderStatus;
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
        $productsCount = Product::query()->active()->count();
        $newToday = Order::query()->whereDate('created_at', today())->count();

        $newStatusIds = OrderStatus::query()
            ->whereIn('name', ['Новый', 'В обработке'])
            ->pluck('id');
        $pending = Order::query()->whereIn('status_id', $newStatusIds)->count();

        return [
            Stat::make('Заказов', $ordersCount)
                ->description($pending > 0 ? "В работе: {$pending}" : 'Всего в магазине')
                ->color($pending > 0 ? 'warning' : 'primary'),
            Stat::make('Выручка', number_format($revenue, 0, ',', ' ').' ₽')
                ->description('Сумма всех заказов')
                ->color('success'),
            Stat::make('Товаров', $productsCount)
                ->description('Активных на витрине')
                ->color('info'),
            Stat::make('Сегодня', $newToday)
                ->description(now()->format('d.m.Y'))
                ->color('warning'),
        ];
    }
}
