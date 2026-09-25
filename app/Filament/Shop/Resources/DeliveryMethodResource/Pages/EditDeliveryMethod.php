<?php
namespace App\Filament\Shop\Resources\DeliveryMethodResource\Pages;
use App\Filament\Shop\Resources\DeliveryMethodResource; use Filament\Actions; use Filament\Resources\Pages\EditRecord;
class EditDeliveryMethod extends EditRecord { protected static string $resource = DeliveryMethodResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; } }
