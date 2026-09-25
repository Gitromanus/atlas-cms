<?php

namespace App\Filament\Shop\Resources\ProductResource\Pages;

use App\Filament\Shop\Resources\ProductResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->url(null)
                ->modal()
                ->slideOver()
                ->mutateFormDataUsing(fn (array $data): array => ProductResource::stashPendingImages($data))
                ->after(function (Product $record): void {
                    ProductResource::storeNewImages($record, ProductResource::takePendingImages());
                }),
        ];
    }
}
