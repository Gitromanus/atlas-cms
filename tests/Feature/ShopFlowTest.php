<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\Warehouse;
use App\Services\Cart\CartService;
use App\Services\Orders\OrderService;
use App\Services\Tenant\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTenant(string $slug, string $subdomain): Tenant
    {
        $theme = Theme::query()->firstOrCreate(['slug' => 'default'], ['name' => 'Default']);

        return Tenant::query()->create([
            'name' => ucfirst($subdomain).' магазин',
            'slug' => $slug,
            'subdomain' => $subdomain,
            'theme_id' => $theme->id,
            'is_active' => true,
        ]);
    }

    protected function makeProduct(Tenant $tenant, string $sku, float $price, float $stock): Product
    {
        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Категория',
        ]);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Товар '.$sku,
            'sku' => $sku,
        ]);

        $priceType = PriceType::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Розничная',
        ]);

        ProductPrice::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'price_type_id' => $priceType->id,
            'price' => $price,
        ]);

        $warehouse = Warehouse::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Склад',
        ]);

        ProductStock::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $stock,
        ]);

        return $product;
    }

    public function test_tenant_data_is_isolated(): void
    {
        $tenantA = $this->makeTenant('shop-a', 'a');
        $tenantB = $this->makeTenant('shop-b', 'b');

        $this->makeProduct($tenantA, 'SKU-A', 100, 5);
        $this->makeProduct($tenantB, 'SKU-B', 200, 3);

        // Контекст магазина B
        app(TenantContext::class)->set($tenantB);

        $products = Product::query()->pluck('sku')->all();

        $this->assertSame(['SKU-B'], $products);
    }

    public function test_cart_and_checkout_creates_order(): void
    {
        $tenant = $this->makeTenant('demo', 'demo');
        app(TenantContext::class)->set($tenant);

        foreach (['new' => 'Новый', 'processing' => 'В обработке'] as $code => $name) {
            OrderStatus::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'code' => $code,
                'is_system' => true,
            ]);
        }

        $product = $this->makeProduct($tenant, 'SKU-1', 1500, 10);

        // Авторизованный покупатель — корзина привязана к customer_id (без сессии)
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Иван Петров',
            'phone' => '+7 900 000-00-00',
            'email' => 'ivan@example.com',
            'password' => 'secret123',
        ]);

        $this->actingAs($customer, 'customers');

        // Корзина
        $cart = app(CartService::class);
        $cart->add($product, 2);
        $this->assertSame(2, $cart->count());

        $order = app(OrderService::class)->create([
            'name' => 'Иван Петров',
            'phone' => '+7 900 000-00-00',
            'email' => 'ivan@example.com',
            'delivery_method' => 'courier',
            'delivery_address' => 'Москва, ул. Тестовая, 1',
            'payment_method' => 'cash',
        ]);

        $this->assertNotNull($order);
        $this->assertSame('Иван Петров', $order->customer_name);
        $this->assertEquals(3000, (float) $order->total);
        $this->assertCount(1, $order->items);
        $this->assertSame(2, $order->items->first()->quantity);

        // Корзина текущей сессии очищена после заказа
        $this->assertSame(0, app(CartService::class)->count());

        // Заказ получил ext_id (UUID для обмена с 1С)
        $this->assertNotNull($order->ext_id);
    }

    public function test_guest_checkout_creates_customer_profile(): void
    {
        $tenant = $this->makeTenant('guestshop', 'guestshop');
        app(TenantContext::class)->set($tenant);

        OrderStatus::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Новый',
            'code' => 'new',
            'is_system' => true,
        ]);

        $product = $this->makeProduct($tenant, 'SKU-G', 900, 5);

        $cart = app(CartService::class);
        $cart->add($product, 1);

        // Гость без входа в личный кабинет оформляет заказ
        $order = app(OrderService::class)->create([
            'name' => 'Гость Гостевич',
            'phone' => '+7 900 111-22-33',
            'email' => 'guest@example.com',
            'delivery_method' => 'pickup',
            'payment_method' => 'cash',
        ]);

        $this->assertNotNull($order->customer_id, 'У заказа гостя должен появиться customer_id');
        $this->assertDatabaseHas('customers', [
            'tenant_id' => $tenant->id,
            'email' => 'guest@example.com',
            'phone' => '+7 900 111-22-33',
        ]);

        // Повторный заказ тем же email — покупатель не дублируется
        $cart->add($product, 1);
        $second = app(OrderService::class)->create([
            'name' => 'Гость Гостевич',
            'phone' => '+7 900 111-22-33',
            'email' => 'guest@example.com',
            'delivery_method' => 'pickup',
            'payment_method' => 'cash',
        ]);

        $this->assertEquals($order->customer_id, $second->customer_id);
        $this->assertSame(1, Customer::query()->where('email', 'guest@example.com')->count());
    }

    public function test_entrepreneur_can_create_shop_self_service(): void
    {
        // Предприниматель сам создаёт магазин через публичную форму
        $this->post('http://localhost/create-shop', [
            'shop_name' => 'Мой первый магазин',
            'name' => 'Пётр Иванов',
            'email' => 'petr@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect('/shop');

        $this->assertDatabaseHas('tenants', ['name' => 'Мой первый магазин']);
        $this->assertDatabaseHas('users', ['email' => 'petr@example.com']);

        $tenant = Tenant::query()->where('name', 'Мой первый магазин')->first();
        $this->assertNotNull($tenant);
        $this->assertNotNull($tenant->subdomain);

        // Системные статусы заказов созданы автоматически
        $this->assertSame(5, \App\Models\OrderStatus::query()
            ->where('tenant_id', $tenant->id)
            ->count());
    }

    public function test_customer_registration_and_login(): void
    {
        $tenant = $this->makeTenant('demo', 'demo');
        app(TenantContext::class)->set($tenant);

        // Регистрация
        $this->post('http://demo.atlascms.ru/register', [
            'name' => 'Мария',
            'phone' => '+7 911 111-11-11',
            'email' => 'maria@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('account.orders'));

        $this->assertDatabaseHas('customers', [
            'email' => 'maria@example.com',
        ]);

        $customer = Customer::query()->where('email', 'maria@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertSame($tenant->id, $customer->tenant_id);
    }
}