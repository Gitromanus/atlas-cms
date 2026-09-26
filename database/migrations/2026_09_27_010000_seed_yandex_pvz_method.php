<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_methods') || ! Schema::hasTable('tenants')) {
            return;
        }

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ([
                [
                    'code' => 'yandex',
                    'name' => 'Яндекс Доставка',
                    'description' => 'До двери / пункт выдачи — расчёт по API',
                    'sort_order' => 10,
                ],
                [
                    'code' => 'yandex_pvz',
                    'name' => 'Яндекс — пункт выдачи',
                    'description' => 'Доставка в ПВЗ Яндекс',
                    'sort_order' => 11,
                ],
                [
                    'code' => 'yandex_russia',
                    'name' => 'Яндекс — до двери по России',
                    'description' => 'Доставка до двери 1–N дней',
                    'sort_order' => 12,
                ],
            ] as $row) {
                $exists = DB::table('delivery_methods')
                    ->where('tenant_id', $tenantId)
                    ->where('code', $row['code'])
                    ->exists();
                if (! $exists) {
                    DB::table('delivery_methods')->insert([
                        'tenant_id' => $tenantId,
                        'name' => $row['name'],
                        'code' => $row['code'],
                        'description' => $row['description'],
                        'price' => 0,
                        'free_from' => null,
                        'require_address' => 1,
                        'is_active' => 1,
                        'sort_order' => $row['sort_order'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
    }
};
