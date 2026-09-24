<?php

namespace App\Filament\Shop\Resources\ExchangeLogResource\Pages;

use App\Filament\Shop\Resources\ExchangeLogResource;
use Filament\Resources\Pages\ListRecords;

class ListExchangeLogs extends ListRecords
{
    protected static string $resource = ExchangeLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}