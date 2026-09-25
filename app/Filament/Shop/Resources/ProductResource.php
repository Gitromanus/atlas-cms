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
                Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                    ->schema([
                        Forms\Components\Section::make('Изображения')
                            ->description('Первое по порядку — основное (крупно на сайте). Стрелки = порядок, крестик = удалить.')
                            ->schema([
                                Forms\Components\Repeater::make('images')
                                    ->relationship()
                                    ->label('Текущие фото')
                                    ->schema([
                                        Forms\Components\Placeholder::make('preview')
                                            ->label('Превью')
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
                                                    return new HtmlString('<span class="text-sm text-gray-400">нет файла</span>');
                                                }

                                                return new HtmlString(
                                                    '<img src="'.e((string) $url).'" class="h-24 w-24 rounded object-cover ring-1 ring-gray-200" alt="" />'
                                                );
                                            }),
                                        Forms\Components\TextInput::make('sort_order')
                                            ->label('Порядок')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('0 = основное'),
                                        Forms\Components\Hidden::make('path')->dehydrated(true),
                                        Forms\Components\Hidden::make('url')->dehydrated(true),
                                        Forms\Components\Hidden::make('source')->dehydrated(true),
                                        Forms\Components\Hidden::make('tenant_id')->dehydrated(true),
                                    ])
                                    ->orderColumn('sort_order')
                                    ->reorderable()
                                    ->reorderableWithButtons()
                                    ->collapsible()
                                    ->itemLabel(function (array $state): ?string {
                                        return 'Фото · порядок '.($state['sort_order'] ?? '0');
                                    })
                                    ->defaultItems(0)
                                    ->addable(false)
                                    ->deletable()
                                    ->columns(1),
                                Forms\Components\FileUpload::make('new_images')
                                    ->label('Добавить новые фото')
                                    ->disk('public')
                                    ->directory(fn (): string => 'products/'.self::tenantSlug().'/'.now()->format('Y/m'))
                                    ->visibility('public')
                                    ->image()
                                    ->multiple()
                                    ->reorderable()
                                    ->maxFiles(15)
                                    ->dehydrated(true),
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
                                Forms\Components\Hidden::make('tenant_id')
                                    ->default(fn () => app(TenantContext::class)->id()),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['tenant_id'] = $data['tenant_id'] ?? app(TenantContext::class)->id();

                                return $data;
                            }),
                    ])
                    ->collapsed(),
            ]);
    }

    /**
     * @param  array<int, mixed>  $paths
     */
    public static function storeNewImages(Product $product, array $paths): int
    {
        $paths = array_values(array_filter(
            $paths,
            fn ($p) => is_string($p) && $p !== ''
        ));

        if ($paths === []) {
            return 0;
        }

        $startOrder = (int) $product->images()->max('sort_order');
        $created = 0;

        foreach ($paths as $index => $path) {
            $path = ltrim($path, '/');

            ProductImage::query()->create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'path' => $path,
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
