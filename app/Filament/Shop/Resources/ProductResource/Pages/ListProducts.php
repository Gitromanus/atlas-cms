<?php

namespace App\Filament\Shop\Resources\ProductResource\Pages;

use App\Filament\Shop\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Создание товара без ухода со списка
            Actions\CreateAction::make()
                ->modal()
                ->slideOver()
                ->after(function (\App\Models\Product $record, array $data): void {
                    \App\Filament\Shop\Resources\ProductResource::storeNewImages($record, $data['new_images'] ?? []);
                }),
        ];
    }
}