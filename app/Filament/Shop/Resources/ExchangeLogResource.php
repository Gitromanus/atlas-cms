<?php

namespace App\Filament\Shop\Resources;

use App\Filament\Shop\Resources\ExchangeLogResource\Pages;
use App\Models\ExchangeLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExchangeLogResource extends Resource
{
    protected static ?string $model = ExchangeLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Обмен с 1С';

    protected static ?string $modelLabel = 'запись обмена';

    protected static ?string $pluralModelLabel = 'обмен с 1С';

    protected static bool $shouldRegisterNavigation = true;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Время')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'catalog' ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('mode')
                    ->label('Режим'),
                Tables\Columns\TextColumn::make('filename')
                    ->label('Файл')
                    ->limit(30),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failure' => 'danger',
                        'processing' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('message')
                    ->label('Сообщение')
                    ->limit(50)
                    ->tooltip(fn ($state) => $state),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Тип')
                    ->options(['catalog' => 'Каталог', 'sale' => 'Заказы']),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options(['success' => 'Успех', 'failure' => 'Ошибка', 'processing' => 'В обработке']),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExchangeLogs::route('/'),
        ];
    }
}