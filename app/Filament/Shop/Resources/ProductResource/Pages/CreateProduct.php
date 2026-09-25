<?php

namespace App\Filament\Shop\Resources\ProductResource\Pages;

use App\Filament\Shop\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /** @var array<int, string> */
    protected array $pendingImages = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingImages = is_array($data['new_images'] ?? null) ? $data['new_images'] : [];
        unset($data['new_images'], $data['existing_hint']);

        return $data;
    }

    protected function afterCreate(): void
    {
        ProductResource::storeNewImages($this->record, $this->pendingImages);
        $this->pendingImages = [];
    }
}
