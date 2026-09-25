<?php

namespace Tests\Feature;

use App\Filament\Shop\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Shop\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Shop\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Shop\Resources\ProductResource\Pages\ListProducts;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\ProductVariant;
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

    public function test_product_pages_render_with_new_compact_form(): void
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

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'ext_id' => 'prod-v-1',
            'name' => 'Футболка с вариантами',
            'sku' => 'SKU-V1',
            'is_active' => true,
        ]);

        ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'ext_id' => 'v-1',
            'options' => ['Цвет' => 'Красный', 'Размер' => 'M'],
            'price' => 1500.00,
            'quantity' => 4,
        ]);

        $this->actingAs($user);

        Livewire::test(ListProducts::class)
            ->assertOk()
            ->assertSee('Товары');

        // Форма с новой компактной раскладкой и блоком вариантов рендерится без ошибок
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertOk()
            ->assertSee('Варианты')
            ->assertSee('Цена')
            ->assertSee('Остаток');
    }

    public function test_product_search_filters_by_name_and_sku(): void
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

        $first = Product::query()->create([
            'tenant_id' => $tenant->id,
            'ext_id' => 'prod-search-1',
            'name' => 'АКБ Аком 60 Ач',
            'sku' => 'AKB-ACOM-60',
            'is_active' => true,
        ]);

        $second = Product::query()->create([
            'tenant_id' => $tenant->id,
            'ext_id' => 'prod-search-2',
            'name' => 'Масло моторное 5W-40',
            'sku' => 'OIL-5W40',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        // Поиск по названию
        Livewire::test(ListProducts::class)
            ->set('tableSearch', 'Аком')
            ->assertCanSeeTableRecords([$first])
            ->assertCanNotSeeTableRecords([$second]);

        // Поиск по артикулу
        Livewire::test(ListProducts::class)
            ->set('tableSearch', 'OIL-5W40')
            ->assertCanSeeTableRecords([$second])
            ->assertCanNotSeeTableRecords([$first]);

        // Регистронезависимый поиск: другой регистр запроса тоже находит
        Livewire::test(ListProducts::class)
            ->set('tableSearch', 'аком')
            ->assertCanSeeTableRecords([$first])
            ->assertCanNotSeeTableRecords([$second]);

        Livewire::test(ListProducts::class)
            ->set('tableSearch', 'oil-5w40')
            ->assertCanSeeTableRecords([$second])
            ->assertCanNotSeeTableRecords([$first]);
    }
}