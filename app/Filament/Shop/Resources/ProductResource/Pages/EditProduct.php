<?php

namespace App\Filament\Shop\Resources\ProductResource\Pages;

use App\Filament\Shop\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /** @var array<int, string> */
    protected array $pendingImages = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingImages = is_array($data['new_images'] ?? null) ? $data['new_images'] : [];
        unset($data['new_images']);

        return $data;
    }

    protected function afterSave(): void
    {
        ProductResource::storeNewImages($this->record, $this->pendingImages);
        $this->pendingImages = [];
    }
}
