<?php

namespace App\Filament\Shop\Resources\CustomerResource\Pages;

use App\Filament\Shop\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}