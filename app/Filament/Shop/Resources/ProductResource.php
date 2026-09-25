<?php

namespace App\Filament\Shop\Resources;

use App\Filament\Shop\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\ProductImage;
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
                // Компактная раскладка: слева изображения, справа основные данные (как в классических админках)
                Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                    ->schema([
                        Forms\Components\Section::make('Изображения')
                            ->description('Основное фото и миниатюры; клик по миниатюре открывает её в предпросмотре')
                            ->schema([
                                // Компактная галерея: крупное фото + миниатюры (ссылки генерируются автоматически)
                                Forms\Components\ViewField::make('images_gallery')
                                    ->view('filament.shop.product-images-gallery')
                                    ->dehydrated(false),
                                Forms\Components\FileUpload::make('new_images')
                                    ->label('Добавить новые изображения')
                                    ->helperText('Файлы сохранятся вместе с товаром')
                                    ->disk('public')
                                    ->directory(fn () => 'products/'.self::tenantSlug().'/'.now()->format('Y/m'))
                                    ->image()
                                    ->multiple()
                                    ->reorderable(),
                            ])
                            ->columnSpan(['default' => 1, 'md' => 1]),
                        Forms\Components\Section::make('Основное')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Название')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('slug')
                                    ->label('ЧПУ (slug)')
                                    ->helperText('Пусто — сгенерируется из названия'),
                                Forms\Components\Select::make('category_id')
                                    ->label('Категория')
                                    ->relationship('category', 'name'),
                                Forms\Components\TextInput::make('sku')
                                    ->label('Артикул'),
                                Forms\Components\TextInput::make('barcode')
                                    ->label('Штрихкод'),
                                Forms\Components\TextInput::make('unit')
                                    ->label('Единица измерения'),
                                Forms\Components\Textarea::make('description')
                                    ->label('Описание')
                                    ->rows(5)
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
                            ->label('Помечен на удаление в 1С'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Варианты (из 1С)')
                    ->description('Реальные комбинации характеристик: цена и остаток по каждому варианту')
                    ->schema([
                        Forms\Components\ViewField::make('variants_preview')
                            ->view('filament.shop.product-variants')
                            ->dehydrated(false),
                    ])
                    ->collapsible(),
                Forms\Components\Section::make('Характеристики')
                    ->description('Перетаскивайте строки, чтобы задать порядок. Свойства с включённым «Вариантом» выбираются на витрине.')
                    ->schema([
                        Forms\Components\Repeater::make('features')
                            ->relationship('features')
                            ->label('Характеристики товара')
                            // Порядок сохраняется в sort_order (без orderColumn Filament его не пишет)
                            ->orderColumn('sort_order')
                            ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null)
                                ? trim(($state['name'] ?? '').(filled($state['value'] ?? null) ? ': '.$state['value'] : ''))
                                : null)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Название')
                                    ->required(),
                                Forms\Components\TextInput::make('value')
                                    ->label('Значение'),
                                Forms\Components\Toggle::make('is_variant')
                                    ->label('Вариант')
                                    ->helperText('Выбор на витрине'),
                                Forms\Components\TagsInput::make('options')
                                    ->label('Варианты значений')
                                    ->placeholder('Добавить значение…')
                                    ->reorderable()
                                    ->helperText('Перетаскивайте теги для ручного порядка; новые значения из 1С добавляются по алфавиту'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderableWithButtons()
                            ->collapsible(),
                    ])
                    ->collapsible(),
                Forms\Components\Section::make('Цены и остатки')
                    ->description('Приходят из 1С; можно править вручную')
                    ->schema([
                        Forms\Components\Repeater::make('prices')
                            ->relationship('prices')
                            ->label('Цены по типам цен')
                            ->itemLabel(fn (array $state): ?string => isset($state['price'])
                                ? 'Цена: '.number_format((float) $state['price'], 2, ',', ' ')
                                : null)
                            ->schema([
                                Forms\Components\Select::make('price_type_id')
                                    ->label('Тип цены')
                                    ->relationship('priceType', 'name')
                                    ->required(),
                                Forms\Components\TextInput::make('price')
                                    ->label('Цена')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderableWithButtons()
                            ->collapsible(),
                        Forms\Components\Repeater::make('stocks')
                            ->relationship('stocks')
                            ->label('Остатки по складам')
                            ->itemLabel(fn (array $state): ?string => isset($state['quantity'])
                                ? 'Остаток: '.number_format((float) $state['quantity'], 0, ',', ' ')
                                : null)
                            ->schema([
                                Forms\Components\Select::make('warehouse_id')
                                    ->label('Склад')
                                    ->relationship('warehouse', 'name')
                                    ->required(),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Количество')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderableWithButtons()
                            ->collapsible(),
                    ]),
            ]);
    }

    /**
     * Сохраняет вновь загруженные изображения (FileUpload уже положил файлы на диск).
     * Вызывается из модальных действий и страниц ресурса.
     *
     * @param  array<int, string>  $paths
     */
    public static function storeNewImages(Product $product, array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $startOrder = ((int) $product->images()->max('sort_order')) + 1;

        foreach ($paths as $index => $path) {
            ProductImage::query()->create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'path' => $path,
                'url' => null,
                'source' => 'manual',
                'sort_order' => $startOrder + $index,
            ]);
        }
    }

    protected static function tenantSlug(): string
    {
        return app(\App\Services\Tenant\TenantContext::class)->current()?->slug ?? 'common';
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Миникартинка и колонки «цена/остаток вариантов» используют отношения
        return parent::getEloquentQuery()->with(['mainImage', 'variants']);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Без перехода на страницу редактирования по клику на строку — редактирование в модале
            ->recordUrl(null)
            ->columns([
                Tables\Columns\ImageColumn::make('mainImage.url')
                    ->label('')
                    ->circular()
                    ->size(40),
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    // Регистронезависимый поиск: по нормализованной колонке search_name
                    // (name+sku в нижнем регистре; SQLite LOWER() не понимает кириллицу)
                    ->searchable(
                        'search_name',
                        query: fn (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder
                            => $query->where('search_name', 'like', '%'.mb_strtolower($search).'%'),
                    )
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('sku')
                    ->label('Артикул')
                    ->searchable(
                        'search_name',
                        query: fn (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder
                            => $query->where('search_name', 'like', '%'.mb_strtolower($search).'%'),
                    ),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    // Для товаров с вариантами — диапазон цен вариантов, иначе обычная цена
                    ->state(function (Product $record): ?string {
                        $range = $record->variantPriceRange();

                        if ($range !== null) {
                            $min = number_format($range['min'], 0, ',', ' ');
                            $max = number_format($range['max'], 0, ',', ' ');

                            return $min === $max ? $min.' ₽' : $min.' – '.$max.' ₽';
                        }

                        return $record->price !== null
                            ? number_format($record->price, 0, ',', ' ').' ₽'
                            : null;
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('variants_list')
                    ->label('Варианты')
                    // Все реальные комбинации: «Красный / M — 1500 ₽ — 4 шт», каждая с новой строки
                    ->state(function (Product $record): ?string {
                        if ($record->variants->isEmpty()) {
                            return null;
                        }

                        return $record->variants
                            ->map(fn ($variant): string => collect($variant->options ?? [])->implode(' / ')
                                .' — '.number_format((float) $variant->price, 0, ',', ' ').' ₽'
                                .' — '.number_format((float) $variant->quantity, 0, ',', ' ').' шт')
                            ->implode("\n");
                    })
                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null)
                    ->wrap()
                    ->placeholder('—')
                    ->extraAttributes(['class' => 'whitespace-pre-line text-xs leading-5']),
                Tables\Columns\TextColumn::make('stockTotal')
                    ->label('Остаток')
                    // У товаров с вариантами остаток считается по вариантам
                    ->state(fn (Product $record): float => $record->hasVariants()
                        ? $record->variantStockTotal()
                        : $record->stockTotal())
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
                // Редактирование в модальном окне — не нужно возвращаться из отдельной страницы
                Tables\Actions\EditAction::make()
                    // Явно отключаем URL страницы ресурса, иначе действие станет ссылкой, а не модалом
                    ->url(null)
                    ->modal()
                    ->slideOver()
                    ->after(fn (Product $record, array $data): mixed => self::storeNewImages($record, $data['new_images'] ?? [])),
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