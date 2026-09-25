<?php

namespace App\Filament\Shop\Resources\CategoryResource\Pages;

use App\Filament\Shop\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

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