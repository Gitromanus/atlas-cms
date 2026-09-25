<?php

namespace App\Filament\Shop\Resources\CustomerResource\Pages;

use App\Filament\Shop\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                // Явно отключаем URL страницы создания, иначе действие станет ссылкой, а не модалом
                ->url(null)
                ->modal()
                ->slideOver(),
        ];
    }
}