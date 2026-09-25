<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Theme;
use App\Services\Tenant\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Фильтры по характеристикам и сортировки каталога витрины.
 */
class CatalogFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function makeShop(): array
    {
        $theme = Theme::query()->firstOrCreate(['slug' => 'default'], ['name' => 'Default']);

        $tenant = Tenant::query()->create([
            'name' => 'Фильтр-магазин',
            'subdomain' => 'filters',
            'slug' => 'filters-shop',
            'theme_id' => $theme->id,
            'is_active' => true,
        ]);

        app(TenantContext::class)->set($tenant);

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Одежда',
        ]);

        return [$tenant, $category];
    }

    protected function makeProduct(Tenant $tenant, Category $category, string $name, string $sku): Product
    {
        return Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => $name,
            'sku' => $sku,
            'is_active' => true,
        ]);
    }

    protected function setPrice(Tenant $tenant, Product $product, float $price): void
    {
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
    }

    public function test_catalog_filters_by_regular_and_variant_features(): void
    {
        [$tenant, $category] = $this->makeShop();

        // Обычная характеристика «Пол»
        $jacket = $this->makeProduct($tenant, $category, 'Куртка мужская', 'JACK-1');
        ProductFeature::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $jacket->id,
            'name' => 'Пол',
            'value' => 'Мужской',
            'is_variant' => false,
        ]);

        $dress = $this->makeProduct($tenant, $category, 'Платье', 'DRESS-1');
        ProductFeature::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $dress->id,
            'name' => 'Пол',
            'value' => 'Женский',
            'is_variant' => false,
        ]);

        // Вариантная характеристика «Цвет» (значения из реальных комбинаций)
        $tShirt = $this->makeProduct($tenant, $category, 'Футболка', 'TSHIRT-1');
        ProductFeature::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $tShirt->id,
            'name' => 'Цвет',
            'value' => 'Красный',
            'is_variant' => true,
            'options' => ['Красный', 'Синий'],
        ]);
        ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $tShirt->id,
            'ext_id' => 'tshirt#1',
            'options' => ['Цвет' => 'Красный'],
            'price' => 500.00,
            'quantity' => 2,
        ]);

        // Фильтр по обычной характеристике: f[Пол][]=Мужской
        $this->get('http://filters.atlascms.ru/catalog?'.http_build_query(['f' => ['Пол' => ['Мужской']]]))
            ->assertOk()
            ->assertSee('Куртка мужская')
            ->assertDontSee('Платье');

        // Фильтр по вариантной характеристике: f[Цвет][]=Красный
        $this->get('http://filters.atlascms.ru/catalog?'.http_build_query(['f' => ['Цвет' => ['Красный']]]))
            ->assertOk()
            ->assertSee('Футболка')
            ->assertDontSee('Куртка мужская')
            ->assertDontSee('Платье');

        // Несуществующее значение фильтра — пустой список
        $this->get('http://filters.atlascms.ru/catalog?'.http_build_query(['f' => ['Пол' => ['Детский']]]))
            ->assertOk()
            ->assertDontSee('Куртка мужская')
            ->assertDontSee('Платье')
            ->assertSee('Ничего не найдено');
    }

    public function test_catalog_sorts_by_name_and_price(): void
    {
        [$tenant, $category] = $this->makeShop();

        $cheap = $this->makeProduct($tenant, $category, 'Запчасть А', 'PART-A');
        $this->setPrice($tenant, $cheap, 500);

        $expensive = $this->makeProduct($tenant, $category, 'Запчасть Б', 'PART-B');
        $this->setPrice($tenant, $expensive, 1500);

        // По алфавиту (А–Я)
        $this->get('http://filters.atlascms.ru/catalog?sort=name_asc')
            ->assertOk()
            ->assertSeeInOrder(['Запчасть А', 'Запчасть Б']);

        // Сначала дешевле
        $this->get('http://filters.atlascms.ru/catalog?sort=price_asc')
            ->assertOk()
            ->assertSeeInOrder(['500', '1 500']);
    }
}