<?php
namespace App\Filament\Shop\Resources;
use App\Filament\Shop\Resources\DeliveryMethodResource\Pages;
use App\Models\DeliveryMethod; use App\Services\Tenant\TenantContext;
use Filament\Forms; use Filament\Forms\Form; use Filament\Resources\Resource;
use Filament\Tables; use Filament\Tables\Table;
class DeliveryMethodResource extends Resource {
    protected static ?string $model = DeliveryMethod::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Доставка';
    protected static ?string $modelLabel = 'способ доставки';
    protected static ?string $pluralModelLabel = 'способы доставки';
    protected static ?int $navigationSort = 25;
    public static function form(Form $form): Form {
        return $form->schema([
            Forms\Components\Hidden::make('tenant_id')->default(fn()=>app(TenantContext::class)->id()),
            Forms\Components\TextInput::make('name')->label('Название')->required(),
            Forms\Components\TextInput::make('code')->label('Код')
                ->helperText('Для Яндекс Доставки укажите: yandex')
                ->maxLength(64)->nullable(),
            Forms\Components\TextInput::make('price')->label('Стоимость ₽')->numeric()->default(0)->required(),
            Forms\Components\TextInput::make('free_from')->label('Бесплатно от ₽')->numeric()->nullable(),
            Forms\Components\Textarea::make('description')->label('Описание')->rows(2)->columnSpanFull(),
            Forms\Components\Toggle::make('require_address')->label('Нужен адрес')->default(true),
            Forms\Components\Toggle::make('is_active')->label('Активен')->default(true),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0)->label('Сортировка'),
        ])->columns(2);
    }
    public static function table(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Название'),
            Tables\Columns\TextColumn::make('price')->money('RUB')->label('Цена'),
            Tables\Columns\IconColumn::make('is_active')->boolean()->label('Активен'),
        ])->actions([Tables\Actions\EditAction::make(),Tables\Actions\DeleteAction::make()]);
    }
    public static function getPages(): array {
        return [
            'index'=>Pages\ListDeliveryMethods::route('/'),
            'create'=>Pages\CreateDeliveryMethod::route('/create'),
            'edit'=>Pages\EditDeliveryMethod::route('/{record}/edit'),
        ];
    }
}
