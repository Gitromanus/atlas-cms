<?php

namespace Tests\Feature;

use App\Filament\Shop\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Shop\Resources\OrderResource\Pages\ListOrders;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Рендер страниц панели магазина не должен падать из-за методов,
 * которых нет у компонентов Filament (money()/datetime() на TextInput и т.п.).
 */
class ShopAdminFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_pages_render_without_errors(): void
    {
        $theme = Theme::query()->create(['slug' => 'default', 'name' => 'Default']);
        $tenant = Tenant::query()->create([
            'name' => 'Тест-магазин',
            'subdomain' => 'test',
            'slug' => 'testshop',
            'theme_id' => $theme->id,
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Владелец',
            'email' => 'owner@test.ru',
            'password' => Hash::make('secret123'),
        ]);

        $status = OrderStatus::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Новый',
            'code' => 'new',
            'is_system' => true,
        ]);

        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'status_id' => $status->id,
            'number' => '000001',
            'ext_id' => 'order-1',
            'total' => 1500.00,
            'currency' => 'RUB',
            'customer_name' => 'Иван',
            'placed_at' => now(),
        ]);

        $this->actingAs($user);

        // Список и страница редактирования заказа рендерятся без исключений
        Livewire::test(ListOrders::class)
            ->assertOk()
            ->assertSee('Заказы');

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertOk()
            ->assertSee('Сумма');
    }
}