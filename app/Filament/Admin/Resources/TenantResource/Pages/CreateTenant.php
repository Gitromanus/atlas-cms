<?php

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Filament\Admin\Resources\TenantResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function afterCreate(): void
    {
        // Системные статусы заказов для нового магазина
        foreach ([
            ['code' => 'new', 'name' => 'Новый'],
            ['code' => 'processing', 'name' => 'В обработке'],
            ['code' => 'shipped', 'name' => 'Отправлен'],
            ['code' => 'completed', 'name' => 'Выполнен'],
            ['code' => 'cancelled', 'name' => 'Отменён'],
        ] as $status) {
            \App\Models\OrderStatus::query()->firstOrCreate(
                ['tenant_id' => $this->record->id, 'code' => $status['code']],
                ['name' => $status['name'], 'is_system' => true]
            );
        }
    }
}