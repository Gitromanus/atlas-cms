<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedDemoVariantProducts extends Command
{
    protected $signature = 'atlas:demo-variants {--tenant= : slug магазина}';

    protected $description = 'Демо-товары с цветами/размерами и картинками (Unsplash)';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q) => $q->where('slug', $this->option('tenant')))
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('Магазины не найдены');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->seedTenant($tenant);
        }

        $this->info('Готово.');

        return self::SUCCESS;
    }

    protected function seedTenant(Tenant $tenant): void
    {
        $this->line('Магазин: '.$tenant->slug);

        $category = Category::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'odezhda'],
            ['name' => 'Одежда', 'is_active' => true, 'sort_order' => 1]
        );

        $warehouse = Warehouse::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Основной склад'],
            ['ext_id' => 'main']
        );

        $priceType = PriceType::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Розничная'],
            ['ext_id' => 'retail', 'currency' => 'RUB']
        );

        $demos = [
            [
                'name' => 'Футболка Classic',
                'sku' => 'DEMO-TEE-01',
                'price' => 1290,
                'description' => 'Хлопковая футболка свободного кроя. Несколько цветов и размеров.',
                'colors' => ['Белый', 'Чёрный', 'Синий', 'Красный'],
                'sizes' => ['S', 'M', 'L', 'XL'],
                'images' => [
                    'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=800&q=80',
                    'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?w=800&q=80',
                    'https://images.unsplash.com/photo-1562157873-818bc0726f68?w=800&q=80',
                ],
            ],
            [
                'name' => 'Худи Urban',
                'sku' => 'DEMO-HOOD-01',
                'price' => 3490,
                'description' => 'Тёплое худи с капюшоном. Цвета и размеры на выбор.',
                'colors' => ['Серый', 'Чёрный', 'Бежевый'],
                'sizes' => ['M', 'L', 'XL'],
                'images' => [
                    'https://images.unsplash.com/photo-1556821840-3a63f95609a7?w=800&q=80',
                    'https://images.unsplash.com/photo-1578768079052-aa76e52ff62e?w=800&q=80',
                ],
            ],
            [
                'name' => 'Кроссовки Runner',
                'sku' => 'DEMO-SHOE-01',
                'price' => 5990,
                'description' => 'Лёгкие кроссовки для города. Несколько расцветок.',
                'colors' => ['Белый', 'Чёрный', 'Синий'],
                'sizes' => ['40', '41', '42', '43', '44'],
                'images' => [
                    'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&q=80',
                    'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&q=80',
                ],
            ],
            [
                'name' => 'Джинсы Slim',
                'sku' => 'DEMO-JEANS-01',
                'price' => 4290,
                'description' => 'Классические джинсы slim fit.',
                'colors' => ['Синий', 'Чёрный'],
                'sizes' => ['28', '30', '32', '34'],
                'images' => [
                    'https://images.unsplash.com/photo-1542272604-787c3835535d?w=800&q=80',
                    'https://images.unsplash.com/photo-1541099649105-f69ad21f3246?w=800&q=80',
                ],
            ],
        ];

        foreach ($demos as $demo) {
            $product = Product::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => $demo['sku']],
                [
                    'category_id' => $category->id,
                    'name' => $demo['name'],
                    'description' => $demo['description'],
                    'slug' => Str::slug($demo['name']).'-'.Str::lower($demo['sku']),
                    'unit' => 'шт',
                    'is_active' => true,
                    'is_deleted_from_1c' => false,
                ]
            );

            $product->features()->delete();
            $product->variants()->delete();
            $product->images()->delete();

            $sort = 0;
            foreach ($demo['colors'] as $color) {
                ProductFeature::query()->create([
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'name' => 'Цвет',
                    'value' => $color,
                    'is_variant' => true,
                    'sort_order' => $sort++,
                ]);
            }
            foreach ($demo['sizes'] as $size) {
                ProductFeature::query()->create([
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'name' => 'Размер',
                    'value' => $size,
                    'is_variant' => true,
                    'sort_order' => $sort++,
                ]);
            }
            ProductFeature::query()->create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'name' => 'Материал',
                'value' => 'Хлопок',
                'is_variant' => false,
                'sort_order' => 100,
            ]);

            foreach ($demo['colors'] as $color) {
                foreach ($demo['sizes'] as $size) {
                    ProductVariant::query()->create([
                        'tenant_id' => $tenant->id,
                        'product_id' => $product->id,
                        'name' => $color.' / '.$size,
                        'options' => ['Цвет' => $color, 'Размер' => $size],
                        'quantity' => random_int(3, 25),
                        'price' => $demo['price'],
                    ]);
                }
            }

            ProductPrice::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'price_type_id' => $priceType->id,
                ],
                ['price' => $demo['price']]
            );

            ProductStock::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                ],
                ['quantity' => 50]
            );

            foreach ($demo['images'] as $i => $url) {
                ProductImage::query()->create([
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'path' => null,
                    'url' => $url,
                    'source' => 'demo',
                    'sort_order' => $i,
                ]);
            }

            $this->info('  + '.$product->name.' ('.count($demo['colors']).'×'.count($demo['sizes']).' вариантов)');
        }
    }
}
