<?php

namespace App\Filament\Shop\Resources;

use App\Filament\Shop\Resources\ProductReviewResource\Pages;
use App\Models\ProductReview;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductReviewResource extends Resource
{
    protected static ?string $model = ProductReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Отзывы';

    protected static ?string $modelLabel = 'отзыв';

    protected static ?string $pluralModelLabel = 'отзывы';

    protected static ?int $navigationSort = 35;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')
                ->label('Товар')
                ->relationship('product', 'name')
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('author_name')
                ->label('Автор')
                ->required()
                ->maxLength(120),
            Forms\Components\Select::make('rating')
                ->label('Оценка')
                ->options([
                    5 => '5 — отлично',
                    4 => '4 — хорошо',
                    3 => '3 — нормально',
                    2 => '2 — плохо',
                    1 => '1 — ужасно',
                ])
                ->required(),
            Forms\Components\Textarea::make('body')
                ->label('Текст')
                ->rows(4)
                ->columnSpanFull(),
            Forms\Components\Toggle::make('is_approved')
                ->label('Одобрен')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Товар')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('author_name')
                    ->label('Автор')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('★')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_approved')
                    ->label('Одобрен')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved')->label('Одобрен'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Одобрить')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->visible(fn (ProductReview $record): bool => ! $record->is_approved)
                    ->action(fn (ProductReview $record) => $record->update(['is_approved' => true])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('approveSelected')
                        ->label('Одобрить выбранные')
                        ->icon('heroicon-m-check')
                        ->action(fn ($records) => $records->each->update(['is_approved' => true])),
                ]),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('is_approved', false)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductReviews::route('/'),
            'create' => Pages\CreateProductReview::route('/create'),
            'edit' => Pages\EditProductReview::route('/{record}/edit'),
        ];
    }
}
