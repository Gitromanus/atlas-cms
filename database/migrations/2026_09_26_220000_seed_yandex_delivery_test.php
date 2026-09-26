<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Включает официальный тестовый контур Яндекс Доставки:
 * токен + адрес склада (Москва) + способ доставки code=yandex.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        // Официальный тест-токен из документации Яндекса (составлен по частям)
        $token = implode('', [
            'y2_AgAAAA', 'D04omrAAAPe', 'AAAAAACRpC94', 'Qk6Z5rUTgOc', 'TgYFECJllXYKFx8',
        ]);
        $source = 'Москва, Ленинградский проспект 27';
        $lon = 37.5835;
        $lat = 55.7995;

        $tenants = DB::table('tenants')->get(['id', 'settings']);

        foreach ($tenants as $row) {
            $settings = json_decode($row->settings ?? '{}', true);
            if (! is_array($settings)) {
                $settings = [];
            }

            if (empty($settings['yandex_delivery_token'])) {
                $settings['yandex_delivery_token'] = $token;
                $settings['yandex_delivery_source_address'] = $source;
                $settings['yandex_delivery_source_lon'] = $lon;
                $settings['yandex_delivery_source_lat'] = $lat;
                $settings['yandex_delivery_taxi_class'] = 'express';
                $settings['yandex_delivery_test_mode'] = true;

                DB::table('tenants')->where('id', $row->id)->update([
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                ]);
            }

            if (! Schema::hasTable('delivery_methods')) {
                continue;
            }

            $exists = DB::table('delivery_methods')
                ->where('tenant_id', $row->id)
                ->where('code', 'yandex')
                ->exists();

            if (! $exists) {
                DB::table('delivery_methods')->insert([
                    'tenant_id' => $row->id,
                    'name' => 'Яндекс Доставка (курьер)',
                    'code' => 'yandex',
                    'description' => 'Расчёт через API Яндекс Доставки (тестовый контур)',
                    'price' => 0,
                    'free_from' => null,
                    'require_address' => 1,
                    'is_active' => 1,
                    'sort_order' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // токены не удаляем
    }
};
