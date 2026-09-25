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
        $list = Livewire::test(ListOrders::class)
            ->assertOk()
            ->assertSee('Заказы');

        // Редактирование заказа открывается модалом, а не ведёт на страницу
        $table = $list->instance()->getTable();
        $this->assertNull($table->getRecordUrl($order), 'Клик по строке заказа не должен вести на страницу');
        $edit = collect($table->getActions())->first(fn ($a): bool => $a->getName() === 'edit');
        $this->assertNotNull($edit);
        $this->assertTrue($edit->shouldOpenModal(), 'EditAction заказа должен открывать модал');
        $this->assertNull($edit->getUrl(), 'EditAction заказа не должен иметь URL страницы');

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertOk()
            ->assertSee('Сумма');
    }
}