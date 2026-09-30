<?php

namespace Database\Seeders;

use App\Models\OrderStatus;
use App\Models\PriceType;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Чистая установка одного магазина без демо-товаров.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $theme = Theme::query()->firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default',
                'is_active' => true,
                'settings' => [],
            ]
        );

        $owner = User::query()->firstOrCreate(
            ['email' => 'admin@atlas.local'],
            [
                'name' => 'Администратор',
                'password' => Hash::make('password'),
            ]
        );

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'shop'],
            [
                'name' => 'Мой магазин',
                'subdomain' => 'shop',
                'theme_id' => $theme->id,
                'owner_id' => $owner->id,
                'is_active' => true,
                'settings' => [
                    'phone' => '',
                    'email' => 'admin@atlas.local',
                    'address' => '',
                    'enable_reviews' => true,
                ],
            ]
        );

        if (empty($owner->tenant_id)) {
            $owner->forceFill(['tenant_id' => $tenant->id])->save();
        }

        app(\App\Services\Tenant\TenantContext::class)->set($tenant);

        PriceType::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'retail'],
            ['name' => 'Розничная', 'currency' => 'RUB', 'is_default' => true]
        );

        Warehouse::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'main'],
            ['name' => 'Основной склад', 'is_default' => true]
        );

        foreach ([
            ['code' => 'new', 'name' => 'Новый'],
            ['code' => 'processing', 'name' => 'В обработке'],
            ['code' => 'shipped', 'name' => 'Отправлен'],
            ['code' => 'completed', 'name' => 'Выполнен'],
            ['code' => 'cancelled', 'name' => 'Отменён'],
        ] as $row) {
            OrderStatus::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $row['code']],
                ['name' => $row['name'], 'is_system' => true]
            );
        }
    }
}
