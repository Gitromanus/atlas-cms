<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\OrderStatus;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ProductPrice;
use App\Models\Tenant;
use App\Models\Theme;
use App\Services\Cart\CartService;
use App\Services\Orders\OrderService;
use App\Services\Tenant\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTenant(string $slug): Tenant
    {
        $theme = Theme::query()->firstOrCreate(['slug' => 'default'], ['name' => 'Default']);

        return Tenant::query()->create([
            'name' => 'Магазин '.$slug,
            'slug' => $slug,
            'subdomain' => $slug,
            'theme_id' => $theme->id,
            'is_active' => true,
        ]);
    }

    public function test_slug_auto_transliterated_and_unique(): void
    {
        $tenant = $this->makeTenant('slugshop');
        app(TenantContext::class)->set($tenant);

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Смартфоны и планшеты',
        ]);

        $this->assertSame('smartfony-i-planshety', $category->slug);

        $first = Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Телефон Atlas',
        ]);
        $second = Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Телефон Atlas',
        ]);

        $this->assertSame('telefon-atlas', $first->slug);
        $this->assertSame('telefon-atlas-2', $second->slug);
    }

    public function test_product_is_available_without_stock_records(): void
    {
        $tenant = $this->makeTenant('nostock');
        app(TenantContext::class)->set($tenant);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Товар без остатков',
        ]);

        $this->assertTrue($product->isAvailable());
        $this->assertSame(0.0, $product->stockTotal());
        $this->assertTrue(Product::query()->inStock()->whereKey($product->id)->exists());
    }

    public function test_cart_and_order_preserve_variant_options(): void
    {
        $tenant = $this->makeTenant('variant');
        app(TenantContext::class)->set($tenant);

        OrderStatus::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Новый',
            'code' => 'new',
            'is_system' => true,
        ]);

        $category = Category::query()->create(['tenant_id' => $tenant->id, 'name' => 'Одежда']);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Футболка',
        ]);

        $priceType = PriceType::query()->create(['tenant_id' => $tenant->id, 'name' => 'Розничная']);
        ProductPrice::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'price_type_id' => $priceType->id,
            'price' => 990,
        ]);

        foreach ([['Цвет', 'Красный', ['Красный', 'Синий']], ['Размер', 'M', ['M', 'L']]] as [$name, $value, $options]) {
            ProductFeature::query()->create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'name' => $name,
                'value' => $value,
                'is_variant' => true,
                'options' => $options,
            ]);
        }

        $this->assertCount(2, $product->variantFeatures());

        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Иван',
            'phone' => '+7 900 000-00-00',
            'email' => 'ivan@example.com',
            'password' => 'secret123',
        ]);

        $this->actingAs($customer, 'customers');

        $cart = app(CartService::class);
        $cart->add($product, 2, ['Цвет' => 'Красный', 'Размер' => 'M']);
        $cart->add($product, 1, ['Цвет' => 'Синий', 'Размер' => 'L']);

        // Разные комбинации вариантов — разные строки корзины
        $this->assertSame(2, CartItem::query()->count());
        $this->assertSame(3, $cart->count());

        $order = app(OrderService::class)->create([
            'name' => 'Иван',
            'phone' => '+7 900 000-00-00',
        ]);

        $this->assertSame(2, $order->items()->count());

        $redItem = $order->items()->where('quantity', 2)->first();
        $this->assertNotNull($redItem);
        $this->assertSame(['Цвет' => 'Красный', 'Размер' => 'M'], $redItem->options);

        $blueItem = $order->items()->where('quantity', 1)->first();
        $this->assertNotNull($blueItem);
        $this->assertSame(['Цвет' => 'Синий', 'Размер' => 'L'], $blueItem->options);
    }

    public function test_cart_add_accepts_json_options_from_variant_picker(): void
    {
        $tenant = $this->makeTenant('cartjson');
        app(TenantContext::class)->set($tenant);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Футболка с вариантами',
            'is_active' => true,
        ]);

        // Пикер вариантов отправляет options JSON-строкой (см. product.blade.php)
        $this->post('http://cartjson.atlascms.ru/cart/add', [
            'product_id' => $product->id,
            'quantity' => 2,
            'options' => '{"Цвет":"Красный","Размер":"M"}',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $items = app(CartService::class)->items();
        $this->assertCount(1, $items);
        $this->assertSame(2, $items->first()->quantity);
        $this->assertSame(['Цвет' => 'Красный', 'Размер' => 'M'], $items->first()->options);
    }

    public function test_menu_tree_has_unique_children_and_counts_subcategories(): void
    {
        $tenant = $this->makeTenant('menutree');
        app(TenantContext::class)->set($tenant);

        $root = Category::query()->create(['tenant_id' => $tenant->id, 'name' => 'Корень']);
        $child = Category::query()->create(['tenant_id' => $tenant->id, 'parent_id' => $root->id, 'name' => 'Ребёнок']);
        Category::query()->create(['tenant_id' => $tenant->id, 'parent_id' => $child->id, 'name' => 'Внук']);

        Product::query()->create(['tenant_id' => $tenant->id, 'category_id' => $child->id, 'name' => 'Товар 1']);
        Product::query()->create(['tenant_id' => $tenant->id, 'category_id' => $child->id, 'name' => 'Товар 2']);

        $tree = Category::menuTree();

        $this->assertCount(1, $tree, 'В дереве один корень');
        $this->assertSame(2, $tree[0]->products_count_total, 'Счётчик корня учитывает товары подкатегорий');

        // Вложенные коллекции не должны дублировать категории
        $walk = function (Category $node) use (&$walk): void {
            $ids = $node->children->pluck('id')->all();
            $this->assertSame(array_values(array_unique($ids)), $ids, 'Дети узла «'.$node->name.'» не должны дублироваться');

            foreach ($node->children as $child) {
                $walk($child);
            }
        };

        foreach ($tree as $rootNode) {
            $walk($rootNode);
        }
    }
}