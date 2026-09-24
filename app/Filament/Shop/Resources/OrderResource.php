<?php

namespace App\Filament\Shop\Resources;

use App\Filament\Shop\Resources\OrderResource\Pages;
use App\Filament\Shop\Resources\OrderResource\RelationManagers\OrderItemsRelationManager;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Заказы';

    protected static ?string $modelLabel = 'заказ';

    protected static ?string $pluralModelLabel = 'заказы';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Заказ')
                    ->schema([
                        Forms\Components\TextInput::make('number')
                            ->label('Номер')
                            ->disabled(),
                        Forms\Components\Select::make('status_id')
                            ->label('Статус')
                            ->relationship('status', 'name'),
                        Forms\Components\Toggle::make('is_paid')
                            ->label('Оплачен'),
                        Forms\Components\Toggle::make('exported_to_1c')
                            ->label('Передан в 1С')
                            ->disabled(),
                        Forms\Components\TextInput::make('total')
                            ->label('Сумма')
                            ->money('RUB')
                            ->disabled(),
                        Forms\Components\TextInput::make('placed_at')
                            ->label('Дата заказа')
                            ->disabled()
                            ->datetime(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Покупатель')
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Имя')
                            ->disabled(),
                        Forms\Components\TextInput::make('customer_phone')
                            ->label('Телефон')
                            ->disabled(),
                        Forms\Components\TextInput::make('customer_email')
                            ->label('Email')
                            ->disabled(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Доставка и оплата')
                    ->schema([
                        Forms\Components\TextInput::make('delivery_method')
                            ->label('Доставка')
                            ->disabled(),
                        Forms\Components\TextInput::make('payment_method')
                            ->label('Оплата')
                            ->disabled(),
                        Forms\Components\Textarea::make('delivery_address')
                            ->label('Адрес доставки')
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('comment')
                            ->label('Комментарий')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('№')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Покупатель')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Новый' => 'info',
                        'В обработке' => 'warning',
                        'Выполнен' => 'success',
                        'Отменён' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_paid')
                    ->label('Оплачен')
                    ->boolean(),
                Tables\Columns\IconColumn::make('exported_to_1c')
                    ->label('В 1С')
                    ->boolean(),
                Tables\Columns\TextColumn::make('placed_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status_id')
                    ->label('Статус')
                    ->relationship('status', 'name'),
                Tables\Filters\TernaryFilter::make('exported_to_1c')
                    ->label('Передан в 1С'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrderItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}