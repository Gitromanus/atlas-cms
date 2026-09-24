<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Магазины платформы — единственный ресурс SaaS-владельца.
 *
 * Контроль: создание магазина с владельцем, блокировка, метрики.
 */
class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Магазины';

    protected static ?string $modelLabel = 'магазин';

    protected static ?string $pluralModelLabel = 'магазины';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Магазин')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('subdomain')
                            ->label('Поддомен')
                            ->helperText(fn () => 'Витрина: https://{поддомен}.'.config('atlas.root_domain')),
                        Forms\Components\Select::make('theme_id')
                            ->label('Тема витрины')
                            ->relationship('theme', 'name'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Магазин активен')
                            ->default(true),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Владелец магазина')
                    ->description('Предприниматель получит доступ в свою админку /shop')
                    ->schema([
                        Forms\Components\TextInput::make('owner_name')
                            ->label('Имя владельца')
                            ->visibleOn('create'),
                        Forms\Components\TextInput::make('owner_email')
                            ->label('Email владельца')
                            ->email()
                            ->visibleOn('create'),
                        Forms\Components\TextInput::make('owner_password')
                            ->label('Пароль владельца')
                            ->password()
                            ->helperText('Минимум 6 символов')
                            ->visibleOn('create'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Магазин')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subdomain')
                    ->label('Поддомен')
                    ->searchable(),
                Tables\Columns\TextColumn::make('owner.email')
                    ->label('Владелец')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Товаров')
                    ->counts('products'),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Заказов')
                    ->counts('orders'),
                Tables\Columns\TextColumn::make('revenue')
                    ->label('Выручка')
                    ->money('RUB'),
                Tables\Columns\TextColumn::make('theme.name')
                    ->label('Тема'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активность'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}