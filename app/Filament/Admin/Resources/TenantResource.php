<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TenantResource\Pages;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Магазины';

    protected static ?string $modelLabel = 'магазин';

    protected static ?string $pluralModelLabel = 'магазины';

    protected static ?string $navigationGroup = 'Платформа';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

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
                Forms\Components\Section::make('Обмен с 1С')
                    ->schema([
                        Forms\Components\TextInput::make('settings.exchange.login')
                            ->label('Логин обмена'),
                        Forms\Components\TextInput::make('settings.exchange.password')
                            ->label('Пароль обмена')
                            ->password()
                            ->helperText('Используется при подключении обмена CommerceML'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subdomain')
                    ->label('Поддомен')
                    ->searchable(),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Товаров')
                    ->counts('products'),
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