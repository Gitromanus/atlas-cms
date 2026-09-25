<?php
namespace App\Filament\Shop\Resources;
use App\Filament\Shop\Resources\PageResource\Pages;
use App\Models\Page; use App\Services\Tenant\TenantContext; use App\Support\Slugger;
use Filament\Forms; use Filament\Forms\Form; use Filament\Forms\Set;
use Filament\Resources\Resource; use Filament\Tables; use Filament\Tables\Table;
class PageResource extends Resource {
    protected static ?string $model = Page::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Страницы';
    protected static ?string $modelLabel = 'страница';
    protected static ?string $pluralModelLabel = 'страницы';
    protected static ?int $navigationSort = 35;
    public static function form(Form $form): Form {
        return $form->schema([
            Forms\Components\Hidden::make('tenant_id')->default(fn()=>app(TenantContext::class)->id()),
            Forms\Components\TextInput::make('title')->label('Заголовок')->required()->live(onBlur:true)
                ->afterStateUpdated(fn(Set $set,?string $state)=>$set('slug',Slugger::slug((string)$state)?:'page')),
            Forms\Components\TextInput::make('slug')->label('Slug')->required(),
            Forms\Components\Textarea::make('body')->label('Текст')->rows(12)->columnSpanFull(),
            Forms\Components\TextInput::make('meta_title')->label('SEO title'),
            Forms\Components\TextInput::make('meta_description')->label('SEO description'),
            Forms\Components\Toggle::make('is_published')->label('Опубликована')->default(true),
            Forms\Components\Toggle::make('show_in_menu')->label('В меню')->default(true),
            Forms\Components\TextInput::make('sort_order')->label('Сортировка')->numeric()->default(0),
        ])->columns(2);
    }
    public static function table(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->label('Заголовок')->searchable(),
            Tables\Columns\TextColumn::make('slug'),
            Tables\Columns\IconColumn::make('is_published')->boolean()->label('Публ.'),
            Tables\Columns\IconColumn::make('show_in_menu')->boolean()->label('Меню'),
        ])->actions([Tables\Actions\EditAction::make(),Tables\Actions\DeleteAction::make()]);
    }
    public static function getPages(): array {
        return [
            'index'=>Pages\ListPages::route('/'),
            'create'=>Pages\CreatePage::route('/create'),
            'edit'=>Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
