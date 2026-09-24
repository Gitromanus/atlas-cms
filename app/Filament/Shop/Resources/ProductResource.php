<?php

namespace App\Filament\Shop\Resources;

use App\Filament\Shop\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Товары';

    protected static ?string $modelLabel = 'товар';

    protected static ?string $pluralModelLabel = 'товары';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основное')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->label('ЧПУ (slug)')
                            ->helperText('Оставьте пустым — сгенерируется из названия (кириллица → латиница)'),
                        Forms\Components\TextInput::make('sku')
                            ->label('Артикул'),
                        Forms\Components\TextInput::make('barcode')
                            ->label('Штрихкод'),
                        Forms\Components\Select::make('category_id')
                            ->label('Категория')
                            ->relationship('category', 'name'),
                        Forms\Components\TextInput::make('unit')
                            ->label('Единица измерения'),
                        Forms\Components\Textarea::make('description')
                            ->label('Описание')
                            ->rows(6)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Статус')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Активен на витрине')
                            ->default(true),
                        Forms\Components\Toggle::make('is_deleted_from_1c')
                            ->label('Помечен на удаление в 1С'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Изображения')
                    ->description('Загрузите файлы или укажите внешние ссылки (из 1С). Локальные файлы имеют приоритет.')
                    ->schema([
                        Forms\Components\Repeater::make('images')
                            ->relationship('images')
                            ->label('Изображения товара')
                            ->schema([
                                Forms\Components\FileUpload::make('path')
                                    ->label('Файл (локальная загрузка)')
                                    ->disk('public')
                                    ->directory(fn () => 'products/'.self::tenantSlug())
                                    ->image()
                                    ->imageEditor()
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('url')
                                    ->label('Внешняя ссылка (из 1С)')
                                    ->url()
                                    ->placeholder('https://example.com/image.jpg'),
                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Порядок')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderableWithButtons()
                            ->collapsible(),
                    ]),
            ]);
    }

    protected static function tenantSlug(): string
    {
        return app(\App\Services\Tenant\TenantContext::class)->current()?->slug ?? 'common';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('sku')
                    ->label('Артикул')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stockTotal')
                    ->label('Остаток')
                    ->state(fn (Product $record): float => $record->stockTotal())
                    ->formatStateUsing(fn (float $state): string => number_format($state, 0, ',', ' '))
                    ->badge()
                    ->color(fn (float $state): string => $state > 0 ? 'success' : 'gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активность'),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Категория')
                    ->relationship('category', 'name'),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}