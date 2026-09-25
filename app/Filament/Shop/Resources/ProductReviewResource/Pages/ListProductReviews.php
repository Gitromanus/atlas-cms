<?php

namespace App\Filament\Shop\Resources\ProductReviewResource\Pages;

use App\Filament\Shop\Resources\ProductReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductReviews extends ListRecords
{
    protected static string $resource = ProductReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Добавить'),
        ];
    }
}
