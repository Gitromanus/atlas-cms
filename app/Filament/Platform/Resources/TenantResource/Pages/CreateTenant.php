<?php

namespace App\Filament\Platform\Resources\TenantResource\Pages;

use App\Filament\Platform\Resources\TenantResource;
use App\Models\OrderStatus;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function afterCreate(): void
    {
        $tenant = $this->record;
        $data = $this->form->getState();

        // Системные статусы заказов для нового магазина
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

        // Владелец магазина (предприниматель) — доступ в панель /shop
        if (! empty($data['owner_email'])) {
            $exists = User::query()->where('email', $data['owner_email'])->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'owner_email' => 'Пользователь с таким email уже существует.',
                ]);
            }

            User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $data['owner_name'] ?? $data['owner_email'],
                'email' => $data['owner_email'],
                'password' => Hash::make($data['owner_password'] ?? \Illuminate\Support\Str::random(16)),
                'is_super_admin' => false,
            ]);
        }
    }
}