<?php

namespace App\Filament\Shop\Resources\ProductResource\Pages;

use App\Filament\Shop\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->storeNewImages($this->record, $this->data['new_images'] ?? []);
    }

    /**
     * Сохраняет вновь загруженные изображения (FileUpload уже положил файлы на диск).
     */
    protected function storeNewImages(Product $product, array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $startOrder = ((int) $product->images()->max('sort_order')) + 1;

        foreach ($paths as $index => $path) {
            ProductImage::query()->create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'path' => $path,
                'url' => null,
                'source' => 'manual',
                'sort_order' => $startOrder + $index,
            ]);
        }
    }
}