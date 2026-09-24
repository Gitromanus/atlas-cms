<?php

namespace App\Filament\Admin\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Состав заказа';

    protected static ?string $modelLabel = 'позиция';

    protected static ?string $pluralModelLabel = 'позиции';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Товар'),
                Tables\Columns\TextColumn::make('sku')
                    ->label('Артикул'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Кол-во'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB'),
                Tables\Columns\TextColumn::make('total')
                    ->label('Сумма')
                    ->money('RUB'),
            ]);
    }
}