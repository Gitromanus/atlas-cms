<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\OrderStatus;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\Theme;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Tenant\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $theme = Theme::query()->firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default', 'version' => '1.0.0', 'description' => 'Базовая тема AtlasCMS']
        );

        User::query()->firstOrCreate(
            ['email' => 'admin@atlascms.ru'],
            [
                'name' => 'AtlasCMS Admin',
                'password' => Hash::make('admin12345'),
                'is_super_admin' => true,
            ]
        );

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Демо-магазин',
                'subdomain' => 'demo',
                'theme_id' => $theme->id,
                'is_active' => true,
                'settings' => [
                    'design' => [
                        'primary_color' => '#4f46e5',
                        'accent_color' => '#0f172a',
                        'radius' => '0.75rem',
                        'font_family' => "'Inter', 'Segoe UI', system-ui, sans-serif",
                    ],
                ],
            ]
        );

        TenantDomain::query()->firstOrCreate(
            ['domain' => 'demo.localhost'],
            ['tenant_id' => $tenant->id, 'is_primary' => true]
        );

        User::query()->firstOrCreate(
            ['email' => 'owner@demo.ru'],
            [
                'name' => 'Владелец магазина',
                'password' => Hash::make('admin12345'),
                'tenant_id' => $tenant->id,
            ]
        );

        foreach ([
            ['code' => 'new', 'name' => 'Новый'],
            ['code' => 'processing', 'name' => 'В обработке'],
            ['code' => 'shipped', 'name' => 'Отправлен'],
            ['code' => 'completed', 'name' => 'Выполнен'],
            ['code' => 'cancelled', 'name' => 'Отменён'],
        ] as $status) {
            OrderStatus::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $status['code']],
                ['name' => $status['name'], 'is_system' => true]
            );
        }

        PriceType::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Розничная'],
            ['currency' => 'RUB']
        );

        Warehouse::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Основной склад'],
            []
        );

        if ($tenant->products()->count() === 0) {
            $this->seedDemoCatalog($tenant);
        }

        $this->call(FashionDemoSeeder::class);
    }

    protected function seedDemoCatalog(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant);

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Смартфоны',
            'slug' => 'smartfony',
            'sort_order' => 10,
        ]);

        $warehouse = Warehouse::query()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Основной склад')
            ->first();

        $priceType = PriceType::query()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Розничная')
            ->first();

        $specs = [
            ['sku' => 'PH-1001', 'name' => 'Смартфон Atlas X1', 'price' => 45990, 'stock' => 15, 'features' => [
                ['name' => 'Экран', 'value' => '6.7" AMOLED'],
                ['name' => 'Память', 'value' => '256 ГБ'],
                ['name' => 'Аккумулятор', 'value' => '5000 мА·ч'],
            ]],
            ['sku' => 'PH-1002', 'name' => 'Смартфон Atlas X2 Pro', 'price' => 68990, 'stock' => 8, 'features' => [
                ['name' => 'Экран', 'value' => '6.8" LTPO'],
                ['name' => 'Память', 'value' => '512 ГБ'],
                ['name' => 'Камера', 'value' => '200 Мп'],
            ]],
            ['sku' => 'PH-1003', 'name' => 'Смартфон Atlas Lite', 'price' => 21990, 'stock' => 32, 'features' => [
                ['name' => 'Экран', 'value' => '6.5" IPS'],
                ['name' => 'Память', 'value' => '128 ГБ'],
            ]],
        ];

        foreach ($specs as $i => $spec) {
            $product = Product::query()->create([
                'tenant_id' => $tenant->id,
                'category_id' => $category->id,
                'sku' => $spec['sku'],
                'name' => $spec['name'],
                'slug' => 'atlas-x'.$i.'-'.now()->timestamp,
                'description' => "Описание товара «{$spec['name']}» для демонстрации работы витрины.",
                'unit' => 'шт.',
                'is_active' => true,
            ]);

            ProductPrice::query()->create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'price_type_id' => $priceType->id,
                'price' => $spec['price'],
            ]);

            ProductStock::query()->create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => $spec['stock'],
            ]);

            ProductImage::query()->create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'url' => 'https://placehold.co/600x600/0f172a/ffffff?text=Atlas',
                'sort_order' => 0,
            ]);

            foreach ($spec['features'] as $feature) {
                ProductFeature::query()->create([
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'name' => $feature['name'],
                    'value' => $feature['value'],
                ]);
            }
        }
    }
}
