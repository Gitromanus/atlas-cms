<?php

namespace App\Filament\Shop\Pages;

use App\Models\Tenant;
use App\Models\Theme;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ShopSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Настройки магазина';

    protected static ?string $title = 'Настройки магазина';

    protected static string $view = 'filament.shop.pages.shop-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = $this->tenant();

        $this->form->fill([
            'name' => $tenant->name,
            'subdomain' => $tenant->subdomain,
            'theme_id' => $tenant->theme_id,
            'is_active' => $tenant->is_active,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Основное')
                    ->schema([
                        TextInput::make('name')->label('Название магазина')->required(),
                        TextInput::make('subdomain')->label('Поддомен')
                            ->helperText(fn () => 'Витрина: https://{поддомен}.'.config('atlas.root_domain')),
                        Select::make('theme_id')->label('Тема витрины')
                            ->options(Theme::query()->pluck('name', 'id')),
                        Toggle::make('is_active')->label('Магазин активен'),
                    ])
                    ->columns(2),
                Section::make('Обмен с 1С (CommerceML)')
                    ->description('Логин и пароль обмена — это учётная запись владельца магазина (панель /shop).')
                    ->schema([
                        Placeholder::make('exchange_url')
                            ->label('Адрес обмена для 1С')
                            ->content(fn () => $this->exchangeUrl()),
                        Placeholder::make('exchange_credentials')
                            ->label('Учётные данные')
                            ->content(fn () => $this->tenant()->owner?->email ?? '— владелец не создан'),
                    ]),
                Section::make('Журнал обмена с 1С')
                    ->description('Полный журнал операций: товары, цены, остатки и заказы')
                    ->schema([
                        Placeholder::make('exchange_log_link')
                            ->label('Журнал обмена')
                            ->content(fn () => '<a href="'.url('/shop/exchange-logs').'" class="font-medium text-primary-600 hover:underline">Открыть журнал обмена →</a>'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function tenant(): Tenant
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return \App\Services\Tenant\TenantContext::current()
                ?? Tenant::query()->firstOrFail();
        }

        return $user->tenant->loadMissing('theme');
    }

    protected function exchangeUrl(): string
    {
        $tenant = $this->tenant();
        $host = $tenant->domains()->where('is_primary', true)->value('domain')
            ?? ($tenant->subdomain ? $tenant->subdomain.'.'.config('atlas.root_domain') : null);

        return $host ? 'https://'.$host.'/1c/exchange' : '— задайте поддомен или домен';
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = $this->tenant();

        $tenant->update([
            'name' => $data['name'],
            'subdomain' => $data['subdomain'] ?: null,
            'theme_id' => $data['theme_id'] ?: null,
            'is_active' => $data['is_active'] ?? false,
        ]);

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }

    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить')
                ->submit('save'),
        ];
    }
}