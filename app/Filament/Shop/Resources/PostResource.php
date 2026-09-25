<?php

namespace App\Filament\Shop\Resources;

use App\Filament\Shop\Resources\PostResource\Pages;
use App\Models\Post;
use App\Support\Slugger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'Статьи и новости';

    protected static ?string $modelLabel = 'материал';

    protected static ?string $pluralModelLabel = 'материалы';

    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label('Тип')
                        ->options([
                            Post::TYPE_ARTICLE => 'Статья',
                            Post::TYPE_NEWS => 'Новость',
                        ])
                        ->required()
                        ->default(Post::TYPE_ARTICLE),
                    Forms\Components\TextInput::make('title')
                        ->label('Заголовок')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Slugger::slug((string) $state))),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(255)
                        ->alphaDash(),
                    Forms\Components\Textarea::make('excerpt')
                        ->label('Краткое описание')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('body')
                        ->label('Текст')
                        ->columnSpanFull()
                        ->toolbarButtons([
                            'bold', 'italic', 'bulletList', 'orderedList', 'link', 'h2', 'h3', 'blockquote',
                        ]),
                    Forms\Components\FileUpload::make('cover_path')
                        ->label('Обложка')
                        ->disk('public')
                        ->directory('posts')
                        ->image()
                        ->imagePreviewHeight('120')
                        ->maxSize(4096),
                    Forms\Components\Toggle::make('is_published')
                        ->label('Опубликовано')
                        ->default(false),
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Дата публикации')
                        ->seconds(false),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Post::TYPE_NEWS ? 'Новость' : 'Статья')
                    ->color(fn (string $state): string => $state === Post::TYPE_NEWS ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Публ.')
                    ->boolean(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        Post::TYPE_ARTICLE => 'Статья',
                        Post::TYPE_NEWS => 'Новость',
                    ]),
                Tables\Filters\TernaryFilter::make('is_published')->label('Опубликовано'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
