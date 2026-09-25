<?php

namespace App\Filament\Shop\Pages;

use App\Models\Tenant;
use App\Models\Theme;
use App\Services\Tenant\TenantContext;
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
            'slug' => $tenant->slug,
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
                        TextInput::make('slug')
                            ->label('Адрес витрины (slug)')
                            ->required()
                            ->alphaDash()
                            ->helperText(fn () => 'Витрина: '.$this->storefrontUrlPreview()),
                        Select::make('theme_id')->label('Тема витрины')
                            ->options(Theme::query()->pluck('name', 'id')),
                        Toggle::make('is_active')->label('Магазин активен'),
                    ])
                    ->columns(2),
                Section::make('Свой домен')
                    ->description('Подключение собственного домена появится в следующем обновлении. Сейчас витрина открывается по адресу платформы с slug магазина.')
                    ->schema([
                        Placeholder::make('custom_domain_hint')
                            ->label('Статус')
                            ->content(fn () => $this->customDomainStatus()),
                    ]),
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
                            ->content(fn () => new \Illuminate\Support\HtmlString(
                                '<a href="'.url('/shop/exchange-logs').'" class="font-medium text-primary-600 hover:underline">Открыть журнал обмена →</a>'
                            )),
                    ]),
            ])
            ->statePath('data');
    }

    protected function tenant(): Tenant
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return app(TenantContext::class)->current()
                ?? Tenant::query()->firstOrFail();
        }

        return $user->tenant->loadMissing('theme');
    }

    protected function storefrontUrlPreview(): string
    {
        $slug = $this->data['slug'] ?? $this->tenant()->slug;

        return rtrim((string) config('app.url'), '/').'/'.$slug;
    }

    protected function customDomainStatus(): string
    {
        $domain = $this->tenant()->domains()->where('is_primary', true)->value('domain');

        return $domain
            ? 'Подключён: '.$domain
            : 'Не подключён — витрина на path платформы (см. адрес выше).';
    }

    protected function exchangeUrl(): string
    {
        return $this->tenant()->exchangeUrl();
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = $this->tenant();

        $reserved = config('atlas.reserved_paths', []);
        $slug = strtolower((string) ($data['slug'] ?? ''));

        if (in_array($slug, $reserved, true)) {
            Notification::make()
                ->title('Slug «'.$slug.'» зарезервирован платформой')
                ->danger()
                ->send();

            return;
        }

        $exists = Tenant::query()
            ->where('slug', $slug)
            ->where('id', '!=', $tenant->id)
            ->exists();

        if ($exists) {
            Notification::make()
                ->title('Адрес «'.$slug.'» уже занят другим магазином')
                ->danger()
                ->send();

            return;
        }

        $tenant->update([
            'name' => $data['name'],
            'slug' => $slug,
            // subdomain синхронизируем со slug (на случай будущего subdomain-режима)
            'subdomain' => $slug,
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
