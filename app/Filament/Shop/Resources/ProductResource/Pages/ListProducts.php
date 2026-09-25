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
                ->using(function (array $data): Product {
                    $paths = $data['new_images'] ?? [];
                    unset($data['new_images'], $data['existing_hint']);

                    $record = Product::query()->create($data);
                    ProductResource::storeNewImages($record, is_array($paths) ? $paths : []);

                    return $record;
                }),
        ];
    }
}
