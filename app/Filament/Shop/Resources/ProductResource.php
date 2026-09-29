<?php

namespace App\Filament\Shop\Resources;

use App\Filament\Shop\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Tenant\TenantContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Товары';

    protected static ?string $modelLabel = 'товар';

    protected static ?string $pluralModelLabel = 'товары';

    /** @var array<int, string> */
    protected static array $pendingNewImages = [];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основное')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\Select::make('category_id')
                            ->label('Категория')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('sku')->label('Артикул'),
                        Forms\Components\TextInput::make('barcode')->label('Штрихкод'),
                        Forms\Components\TextInput::make('unit')->label('Ед. изм.')->maxLength(32),
                        Forms\Components\Textarea::make('description')
                            ->label('Описание')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Активен на витрине')
                            ->default(true)
                            ->inline(false),
                        Forms\Components\Toggle::make('is_deleted_from_1c')
                            ->label('Удалён в 1С')
                            ->inline(false),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Изображения')
                    ->description('Первое по порядку — основное на сайте.')
                    ->schema([
                        Forms\Components\Repeater::make('images')
                            ->relationship()
                            ->label('Текущие фото')
                            ->schema([
                                Forms\Components\Placeholder::make('preview')
                                    ->label('')
                                    ->content(function (Get $get): HtmlString {
                                        $url = $get('url');
                                        $path = $get('path');
                                        if (filled($path) && ! filled($url)) {
                                            $relative = str_starts_with((string) $path, 'products/')
                                                ? $path
                                                : 'products/'.$path;
                                            $url = asset('storage/'.$relative);
                                        }
                                        if (! filled($url)) {
                                            return new HtmlString(
                                                '<div class="flex h-24 w-24 items-center justify-center rounded-lg bg-gray-100 text-xs text-gray-400">нет файла</div>'
                                            );
                                        }

                                        return new HtmlString(
                                            '<img src="'.e((string) $url).'" alt="" '
                                            .'class="h-24 w-24 shrink-0 rounded-lg object-cover ring-1 ring-gray-200" '
                                            .'style="height:96px;width:96px;object-fit:cover;" />'
                                        );
                                    })
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Порядок')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->columnSpan(1),
                                Forms\Components\Hidden::make('path'),
                                Forms\Components\Hidden::make('url'),
                                Forms\Components\Hidden::make('source'),
                                Forms\Components\Hidden::make('tenant_id')
                                    ->default(fn () => app(TenantContext::class)->id()),
                            ])
                            ->columns(2)
                            ->grid(4)
                            ->reorderable()
                            ->orderColumn('sort_order')
                            ->addable(false)
                            ->deletable()
                            ->defaultItems(0),

                        Forms\Components\FileUpload::make('new_images')
                            ->label('Добавить фото')
                            ->disk('public')
                            ->directory(fn (): string => 'products/'.self::tenantSlug().'/'.now()->format('Y/m'))
                            ->visibility('public')
                            ->image()
                            ->imagePreviewHeight('96')
                            ->panelLayout('grid')
                            ->multiple()
                            ->reorderable()
                            ->maxFiles(15)
                            ->dehydrated(true),
                    ]),

                Forms\Components\Section::make('Характеристики')
                    ->schema([
                        Forms\Components\Repeater::make('features')
                            ->relationship()
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('name')->label('Название')->required()->maxLength(120),
                                Forms\Components\TextInput::make('value')->label('Значение')->required()->maxLength(255),
                                Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0),
                                Forms\Components\Toggle::make('is_variant')->label('Вариант')->inline(false),
                                Forms\Components\Hidden::make('tenant_id')
                                    ->default(fn () => app(TenantContext::class)->id()),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->addActionLabel('Добавить')
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['tenant_id'] = $data['tenant_id'] ?? app(TenantContext::class)->id();

                                return $data;
                            }),
                    ]),

                Forms\Components\Section::make('Варианты (из 1С)')
                    ->description('Остаток и цена по каждой комбинации характеристик')
                    ->schema([
                        Forms\Components\Repeater::make('variants')
                            ->relationship()
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('name')->label('Название')->columnSpan(2),
                                Forms\Components\TextInput::make('quantity')->label('Остаток')->numeric()->columnSpan(1),
                                Forms\Components\TextInput::make('price')->label('Цена')->numeric()->columnSpan(1),
                                Forms\Components\KeyValue::make('options')
                                    ->label('Характеристики')
                                    ->keyLabel('Свойство')
                                    ->valueLabel('Значение')
                                    ->columnSpanFull(),
                                Forms\Components\Hidden::make('tenant_id')
                                    ->default(fn () => app(TenantContext::class)->id()),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('Добавить вариант')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                    ])
                    ->collapsed(fn (?Product $record) => $record === null || $record->variants()->count() === 0),

                Forms\Components\Section::make('Склад')
                    ->schema([
                        Forms\Components\Repeater::make('stocks')
                            ->relationship()
                            ->label('')
                            ->schema([
                                Forms\Components\Select::make('warehouse_id')
                                    ->label('Склад')
                                    ->relationship('warehouse', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Количество')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->columnSpan(1),
                                Forms\Components\Hidden::make('tenant_id')
                                    ->default(fn () => app(TenantContext::class)->id()),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Добавить остаток')
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['tenant_id'] = $data['tenant_id'] ?? app(TenantContext::class)->id();

                                return $data;
                            }),
                    ]),

                Forms\Components\Section::make('Цены')
                    ->schema([
                        Forms\Components\Repeater::make('prices')
                            ->relationship('prices')
                            ->label('')
                            ->schema([
                                Forms\Components\Select::make('price_type_id')
                                    ->label('Тип цены')
                                    ->relationship('priceType', 'name')
                                    ->required(),
                                Forms\Components\TextInput::make('price')
                                    ->label('Цена')
                                    ->numeric()
                                    ->required()
                                    ->suffix('₽'),
                                Forms\Components\Hidden::make('tenant_id')
                                    ->default(fn () => app(TenantContext::class)->id()),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Добавить цену')
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['tenant_id'] = $data['tenant_id'] ?? app(TenantContext::class)->id();

                                return $data;
                            }),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function storeNewImages(Product $product, array $paths): int
    {
        $paths = array_values(array_filter($paths, fn ($p) => is_string($p) && $p !== ''));

        if ($paths === []) {
            return 0;
        }

        $startOrder = (int) $product->images()->max('sort_order');
        $created = 0;

        foreach ($paths as $index => $path) {
            ProductImage::query()->create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'path' => ltrim($path, '/'),
                'url' => null,
                'source' => 'manual',
                'sort_order' => $startOrder + $index + 1,
            ]);
            $created++;
        }

        return $created;
    }

    public static function takePendingImages(): array
    {
        $paths = self::$pendingNewImages;
        self::$pendingNewImages = [];

        return $paths;
    }

    public static function stashPendingImages(array $data): array
    {
        self::$pendingNewImages = is_array($data['new_images'] ?? null) ? $data['new_images'] : [];
        unset($data['new_images']);

        return $data;
    }

    protected static function tenantSlug(): string
    {
        return app(TenantContext::class)->current()?->slug ?? 'common';
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['images', 'features', 'stocks.warehouse', 'prices', 'variants']);
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
                Tables\Columns\TextColumn::make('stock_qty')
                    ->label('Остаток')
                    ->getStateUsing(function (Product $record): string {
                        $qty = $record->relationLoaded('variants') && $record->variants->isNotEmpty()
                            ? (float) $record->variants->sum('quantity')
                            : $record->stockTotal();

                        return number_format($qty, 0, ',', ' ');
                    })
                    ->alignRight(),
                Tables\Columns\TextColumn::make('images_count')
                    ->label('Фото')
                    ->counts('images')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')->label('Активен')->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Активность'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(null)
                    ->modal()
                    ->slideOver()
                    ->mutateFormDataUsing(fn (array $data): array => self::stashPendingImages($data))
                    ->after(function (Product $record): void {
                        self::storeNewImages($record, self::takePendingImages());
                    }),
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
