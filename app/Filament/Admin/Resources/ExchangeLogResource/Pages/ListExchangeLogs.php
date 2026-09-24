<?php

namespace App\Filament\Admin\Resources\ExchangeLogResource\Pages;

use App\Filament\Admin\Resources\ExchangeLogResource;
use Filament\Resources\Pages\ListRecords;

class ListExchangeLogs extends ListRecords
{
    protected static string $resource = ExchangeLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}