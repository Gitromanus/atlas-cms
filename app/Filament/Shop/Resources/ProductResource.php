<?php

namespace App\Filament\Shop\Resources;

use App\Filament\Shop\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Services\Tenant\TenantContext;
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
                Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                    ->schema([
                        Forms\Components\Section::make('Изображения')
                            ->description('Первое в списке — основное. Можно менять порядок и удалять.')
                            ->schema([
                                Forms\Components\Repeater::make('images')
                                    ->relationship()
                                    ->label('Фотографии')
                                    ->schema([
                                        Forms\Components\FileUpload::make('path')
                                            ->label('Файл')
                                            ->disk('public')
                                            ->directory(fn (): string => 'products/'.self::tenantSlug().'/'.now()->format('Y/m'))
                                            ->visibility('public')
                                            ->image()
                                            ->imagePreviewHeight('120')
                                            ->nullable()
                                            ->dehydrated(true),
                                        Forms\Components\TextInput::make('url')
                                            ->label('Или внешний URL')
                                            ->url()
                                            ->nullable()
                                            ->helperText('Если нет файла — можно указать ссылку'),
                                        Forms\Components\Hidden::make('source')
                                            ->default('manual'),
                                        Forms\Components\Hidden::make('tenant_id')
                                            ->default(fn () => app(TenantContext::class)->id()),
                                    ])
                                    ->orderColumn('sort_order')
                                    ->reorderable()
                                    ->reorderableWithButtons()
                                    ->collapsible()
                                    ->cloneable(false)
                                    ->defaultItems(0)
                                    ->addActionLabel('Добавить фото')
                                    ->itemLabel(function (array $state): ?string {
                                        if (filled($state['path'] ?? null)) {
                                            return 'Файл: '.basename((string) $state['path']);
                                        }
                                        if (filled($state['url'] ?? null)) {
                                            return 'URL';
                                        }

                                        return 'Новое фото';
                                    })
                                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                        $data['tenant_id'] = $data['tenant_id']
                                            ?? app(TenantContext::class)->id();
                                        $data['source'] = $data['source'] ?? 'manual';
                                        if (filled($data['path'] ?? null)) {
                                            $data['path'] = ltrim((string) $data['path'], '/');
                                            $data['url'] = null;
                                        }

                                        return $data;
                                    })
                                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                        if (filled($data['path'] ?? null)) {
                                            $data['path'] = ltrim((string) $data['path'], '/');
                                            $data['url'] = null;
                                        }

                                        return $data;
                                    })
                                    ->deleteAction(
                                        fn (Forms\Components\Actions\Action $action) => $action->requiresConfirmation()
                                    ),
                            ])
                            ->columnSpan(['default' => 1, 'md' => 1]),
                        Forms\Components\Section::make('Основное')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Название')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('slug')->label('ЧПУ (slug)'),
                                Forms\Components\Select::make('category_id')
                                    ->label('Категория')
                                    ->relationship('category', 'name'),
                                Forms\Components\TextInput::make('sku')->label('Артикул'),
                                Forms\Components\TextInput::make('barcode')->label('Штрихкод'),
                                Forms\Components\TextInput::make('unit')->label('Ед. изм.'),
                                Forms\Components\Textarea::make('description')
                                    ->label('Описание')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpan(['default' => 1, 'md' => 2]),
                    ]),
                Forms\Components\Section::make('Статус')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Активен на витрине')
                            ->default(true),
                        Forms\Components\Toggle::make('is_deleted_from_1c')
                            ->label('Удалён в 1С'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Цены')
                    ->schema([
                        Forms\Components\Repeater::make('prices')
                            ->relationship('prices')
                            ->schema([
                                Forms\Components\Select::make('price_type_id')
                                    ->label('Тип')
                                    ->relationship('priceType', 'name')
                                    ->required(),
                                Forms\Components\TextInput::make('price')
                                    ->label('Цена')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['tenant_id'] = app(TenantContext::class)->id();

                                return $data;
                            }),
                    ])
                    ->collapsed(),
            ]);
    }

    protected static function tenantSlug(): string
    {
        return app(TenantContext::class)->current()?->slug ?? 'common';
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['images']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Tables\Columns\ImageColumn::make('thumb')
                    ->label('')
                    ->circular()
                    ->size(40)
                    ->getStateUsing(fn (Product $record): ?string => $record->images->sortBy('sort_order')->first()?->url),
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('sku')->label('Артикул'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->getStateUsing(fn (Product $record): ?string => $record->price !== null
                        ? number_format($record->price, 0, ',', ' ').' ₽'
                        : null)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('images_count')
                    ->label('Фото')
                    ->counts('images')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')->label('Активен')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Активность'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(null)
                    ->modal()
                    ->slideOver(),
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
        return [];
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
