<?php

namespace App\Filament\Shop\Widgets;

use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class ShopStats extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        try {
            $ordersCount = Schema::hasTable('orders') ? Order::query()->count() : 0;
            $revenue = Schema::hasTable('orders') ? (float) Order::query()->sum('total') : 0;
            $productsCount = 0;
            if (Schema::hasTable('products')) {
                $q = Product::query();
                if (Schema::hasColumn('products', 'is_active')) {
                    $q->where('is_active', true);
                }
                if (Schema::hasColumn('products', 'is_deleted_from_1c')) {
                    $q->where('is_deleted_from_1c', false);
                }
                $productsCount = $q->count();
            }
            $newToday = Schema::hasTable('orders')
                ? Order::query()->whereDate('created_at', today())->count()
                : 0;
        } catch (\Throwable $e) {
            report($e);
            $ordersCount = $revenue = $productsCount = $newToday = 0;
        }

        return [
            Stat::make('Заказов', (string) $ordersCount)
                ->description('Всего в магазине')
                ->color('primary'),
            Stat::make('Выручка', number_format((float) $revenue, 0, ',', ' ').' ₽')
                ->description('Сумма всех заказов')
                ->color('success'),
            Stat::make('Товаров', (string) $productsCount)
                ->description('На витрине')
                ->color('info'),
            Stat::make('Сегодня', (string) $newToday)
                ->description(now()->format('d.m.Y'))
                ->color('warning'),
        ];
    }
}
