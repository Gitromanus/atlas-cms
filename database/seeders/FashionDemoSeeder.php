<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Tenant\TenantContext;
use Illuminate\Database\Seeder;

class FashionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->command?->warn('FashionDemoSeeder: подходящих магазинов не найдено.');

            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedCatalog($tenant);
            $this->command?->info('FashionDemoSeeder: «'.$tenant->name.'» (/'.$tenant->slug.') — каталог готов.');
        }
    }

    protected function resolveTenants()
    {
        $found = collect();

        $user = User::query()->whereRaw('LOWER(email) = ?', ['test@test.ru'])->first();
        if ($user?->tenant_id) {
            $t = Tenant::query()->find($user->tenant_id);
            if ($t) {
                $found->push($t);
            }
        }

        foreach (['odeza-i-obuv', 'odezhda-i-obuv', 'odezhda'] as $slug) {
            $t = Tenant::query()->where('slug', $slug)->first();
            if ($t) {
                $found->push($t);
            }
        }

        $found = $found->merge(
            Tenant::query()
                ->where(function ($q) {
                    $q->where('slug', 'like', '%odeza%')
                        ->orWhere('slug', 'like', '%odezh%')
                        ->orWhere('name', 'like', '%дежд%')
                        ->orWhere('name', 'like', '%дёж%')
                        ->orWhere('name', 'like', '%Одеж%');
                })
                ->get()
        );

        $found = $found->merge(
            Tenant::query()
                ->where('slug', '!=', 'demo')
                ->where('is_active', true)
                ->whereDoesntHave('products')
                ->get()
        );

        return $found->unique('id')->values();
    }

    public function seedCatalog(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant);

        $priceType = PriceType::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Розничная'],
            ['currency' => 'RUB']
        );

        $warehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Основной склад'],
            []
        );

        $categories = [
            'odezhda' => ['name' => 'Одежда', 'sort' => 10],
            'obuv' => ['name' => 'Обувь', 'sort' => 20],
            'aksessuary' => ['name' => 'Аксессуары', 'sort' => 30],
        ];

        $categoryIds = [];

        foreach ($categories as $slug => $meta) {
            $cat = Category::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                [
                    'name' => $meta['name'],
                    'sort_order' => $meta['sort'],
                    'is_active' => true,
                ]
            );
            $categoryIds[$slug] = $cat->id;
        }

        $sub = [
            ['parent' => 'odezhda', 'slug' => 'futbolki', 'name' => 'Футболки', 'sort' => 11],
            ['parent' => 'odezhda', 'slug' => 'kurtki', 'name' => 'Куртки', 'sort' => 12],
            ['parent' => 'obuv', 'slug' => 'krossovki', 'name' => 'Кроссовки', 'sort' => 21],
            ['parent' => 'obuv', 'slug' => 'botinki', 'name' => 'Ботинки', 'sort' => 22],
        ];

        foreach ($sub as $row) {
            $cat = Category::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'parent_id' => $categoryIds[$row['parent']],
                    'sort_order' => $row['sort'],
                    'is_active' => true,
                ]
            );
            $categoryIds[$row['slug']] = $cat->id;
        }

        $products = [
            ['sku' => 'FT-101', 'name' => 'Футболка Classic белая', 'category' => 'futbolki', 'price' => 1490, 'stock' => 40, 'color' => 'Белый', 'material' => '100% хлопок', 'img' => 'https://placehold.co/600x600/f8fafc/0f172a?text=T-Shirt'],
            ['sku' => 'FT-102', 'name' => 'Футболка Oversize чёрная', 'category' => 'futbolki', 'price' => 1790, 'stock' => 28, 'color' => 'Чёрный', 'material' => 'Хлопок / эластан', 'img' => 'https://placehold.co/600x600/1e293b/ffffff?text=Oversize'],
            ['sku' => 'FT-103', 'name' => 'Футболка с принтом Atlas', 'category' => 'futbolki', 'price' => 1990, 'stock' => 22, 'color' => 'Серый', 'material' => 'Хлопок', 'img' => 'https://placehold.co/600x600/64748b/ffffff?text=Print'],
            ['sku' => 'KT-201', 'name' => 'Куртка демисезонная Navy', 'category' => 'kurtki', 'price' => 7990, 'stock' => 12, 'color' => 'Тёмно-синий', 'material' => 'Полиэстер', 'img' => 'https://placehold.co/600x600/1e3a5f/ffffff?text=Jacket'],
            ['sku' => 'KT-202', 'name' => 'Бомбер хаки', 'category' => 'kurtki', 'price' => 6490, 'stock' => 15, 'color' => 'Хаки', 'material' => 'Нейлон', 'img' => 'https://placehold.co/600x600/3f6212/ffffff?text=Bomber'],
            ['sku' => 'KR-301', 'name' => 'Кроссовки Run Pro белые', 'category' => 'krossovki', 'price' => 5490, 'stock' => 25, 'color' => 'Белый', 'material' => 'Текстиль / резина', 'img' => 'https://placehold.co/600x600/e2e8f0/0f172a?text=Sneakers'],
            ['sku' => 'KR-302', 'name' => 'Кроссовки Street чёрные', 'category' => 'krossovki', 'price' => 4990, 'stock' => 30, 'color' => 'Чёрный', 'material' => 'Кожа / резина', 'img' => 'https://placehold.co/600x600/0f172a/ffffff?text=Street'],
            ['sku' => 'KR-303', 'name' => 'Кроссовки Color Block', 'category' => 'krossovki', 'price' => 6290, 'stock' => 18, 'color' => 'Мультицвет', 'material' => 'Текстиль', 'img' => 'https://placehold.co/600x600/6366f1/ffffff?text=Color'],
            ['sku' => 'BT-401', 'name' => 'Ботинки зимние Thermo', 'category' => 'botinki', 'price' => 8990, 'stock' => 10, 'color' => 'Коричневый', 'material' => 'Нубук / мех', 'img' => 'https://placehold.co/600x600/78350f/ffffff?text=Boots'],
            ['sku' => 'BT-402', 'name' => 'Челси классические', 'category' => 'botinki', 'price' => 7490, 'stock' => 14, 'color' => 'Чёрный', 'material' => 'Натуральная кожа', 'img' => 'https://placehold.co/600x600/171717/ffffff?text=Chelsea'],
            ['sku' => 'AX-501', 'name' => 'Рюкзак City 20L', 'category' => 'aksessuary', 'price' => 3490, 'stock' => 20, 'color' => 'Графит', 'material' => 'Оксфорд 600D', 'img' => 'https://placehold.co/600x600/334155/ffffff?text=Backpack'],
            ['sku' => 'AX-502', 'name' => 'Кепка Baseball', 'category' => 'aksessuary', 'price' => 990, 'stock' => 50, 'color' => 'Чёрный', 'material' => 'Хлопок', 'img' => 'https://placehold.co/600x600/111827/ffffff?text=Cap'],
            ['sku' => 'AX-503', 'name' => 'Шарф шерстяной', 'category' => 'aksessuary', 'price' => 1590, 'stock' => 35, 'color' => 'Бежевый', 'material' => 'Шерсть 80%', 'img' => 'https://placehold.co/600x600/d6d3d1/1c1917?text=Scarf'],
            ['sku' => 'FT-104', 'name' => 'Худи Soft Grey', 'category' => 'odezhda', 'price' => 3990, 'stock' => 16, 'color' => 'Серый меланж', 'material' => 'Футер 3-нитка', 'img' => 'https://placehold.co/600x600/94a3b8/0f172a?text=Hoodie'],
            ['sku' => 'FT-105', 'name' => 'Джинсы Slim Fit', 'category' => 'odezhda', 'price' => 4490, 'stock' => 20, 'color' => 'Синий', 'material' => 'Деним', 'img' => 'https://placehold.co/600x600/1e40af/ffffff?text=Jeans'],
        ];

        foreach ($products as $spec) {
            $product = Product::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'sku' => $spec['sku'],
                ],
                [
                    'category_id' => $categoryIds[$spec['category']] ?? $categoryIds['odezhda'],
                    'name' => $spec['name'],
                    'description' => 'Демо-товар «'.$spec['name'].'». Цвет: '.$spec['color'].'. Материал: '.$spec['material'].'.',
                    'unit' => 'шт.',
                    'is_active' => true,
                    'is_deleted_from_1c' => false,
                ]
            );

            ProductPrice::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'price_type_id' => $priceType->id,
                ],
                ['price' => $spec['price']]
            );

            ProductStock::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                ],
                ['quantity' => $spec['stock']]
            );

            $hasImage = ProductImage::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('product_id', $product->id)
                ->exists();

            if (! $hasImage) {
                ProductImage::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'url' => $spec['img'],
                    'sort_order' => 0,
                    'source' => 'demo',
                ]);
            }

            foreach (
                [
                    ['name' => 'Цвет', 'value' => $spec['color']],
                    ['name' => 'Материал', 'value' => $spec['material']],
                ] as $feature
            ) {
                ProductFeature::withoutGlobalScopes()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'product_id' => $product->id,
                        'name' => $feature['name'],
                    ],
                    ['value' => $feature['value']]
                );
            }
        }
    }
}
