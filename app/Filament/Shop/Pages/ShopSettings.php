<?php

namespace App\Filament\Shop\Pages;

use App\Models\Tenant;
use App\Models\Theme;
use App\Services\Tenant\TenantContext;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
    protected static ?int $navigationSort = 90;
    protected static string $view = 'filament.shop.pages.shop-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->fillFromTenant();
    }

    protected function fillFromTenant(): void
    {
        $tenant = $this->tenant()->refresh();
        $settings = is_array($tenant->settings) ? $tenant->settings : [];

        $this->form->fill([
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'theme_id' => $tenant->theme_id,
            'is_active' => (bool) $tenant->is_active,
            'phone' => (string) ($settings['phone'] ?? ''),
            'email' => (string) ($settings['email'] ?? ''),
            'address' => (string) ($settings['address'] ?? ''),
            'hours' => (string) ($settings['hours'] ?? ''),
            'about' => (string) ($settings['about'] ?? ''),
            'min_order_sum' => $settings['min_order_sum'] ?? null,
            'enable_articles' => (bool) ($settings['enable_articles'] ?? true),
            'enable_news' => (bool) ($settings['enable_news'] ?? true),
            'enable_reviews' => (bool) ($settings['enable_reviews'] ?? true),
            'yookassa_shop_id' => (string) ($settings['yookassa_shop_id'] ?? ''),
            'yookassa_secret_key' => (string) ($settings['yookassa_secret_key'] ?? ''),
            'yandex_delivery_token' => (string) ($settings['yandex_delivery_token'] ?? ''),
            'yandex_delivery_station_id' => (string) ($settings['yandex_delivery_station_id'] ?? ''),
            'yandex_delivery_default_city' => (string) ($settings['yandex_delivery_default_city'] ?? 'Москва'),
            'yandex_delivery_source_address' => (string) ($settings['yandex_delivery_source_address'] ?? ''),
            'yandex_delivery_source_lon' => $settings['yandex_delivery_source_lon'] ?? null,
            'yandex_delivery_source_lat' => $settings['yandex_delivery_source_lat'] ?? null,
            'yandex_delivery_taxi_class' => (string) ($settings['yandex_delivery_taxi_class'] ?? 'express'),
            'yandex_delivery_test_mode' => (bool) ($settings['yandex_delivery_test_mode'] ?? false),
            'dadata_api_key' => (string) ($settings['dadata_api_key'] ?? ''),
            'dadata_secret_key' => (string) ($settings['dadata_secret_key'] ?? ''),
            'logo_path' => $tenant->logo_path,
            'custom_domain' => (string) ($tenant->domains()->where('is_primary', true)->value('domain') ?? ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Основное')
                    ->schema([
                        TextInput::make('name')->label('Название магазина')->required(),
                        FileUpload::make('logo_path')
                            ->label('Логотип')
                            ->disk('public')
                            ->directory('logos')
                            ->image()
                            ->imagePreviewHeight('80')
                            ->maxSize(2048)
                            ->nullable(),
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
                Section::make('Контакты для покупателей')
                    ->description('Телефон — в подвале; адрес, часы и описание — в подвале.')
                    ->schema([
                        TextInput::make('phone')->label('Телефон')->tel()->placeholder('+7 (999) 123-45-67'),
                        TextInput::make('email')->label('Email')->email()->placeholder('shop@example.com')
                            ->helperText('Сюда приходят письма о новых заказах'),
                        TextInput::make('address')->label('Адрес')->columnSpanFull(),
                        TextInput::make('hours')->label('Часы работы')->placeholder('Пн–Пт 10:00–20:00')->columnSpanFull(),
                        Textarea::make('about')->label('О магазине (коротко)')->rows(3)->columnSpanFull(),
                        TextInput::make('min_order_sum')->label('Мин. сумма заказа, ₽')->numeric()->minValue(0)
                            ->helperText('Пусто — без ограничения'),
                    ])
                    ->columns(2),
                Section::make('Разделы витрины')
                    ->schema([
                        Toggle::make('enable_articles')->label('Статьи'),
                        Toggle::make('enable_news')->label('Новости'),
                        Toggle::make('enable_reviews')->label('Отзывы к товарам'),
                    ])
                    ->columns(3),
                Section::make('Онлайн-оплата (ЮKassa)')
                    ->schema([
                        TextInput::make('yookassa_shop_id')->label('Shop ID')->maxLength(64),
                        TextInput::make('yookassa_secret_key')->label('Секретный ключ')->password()->revealable()->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('Яндекс Доставка')
                    ->description('Боевой: OAuth + ID станции. Тумблер «Тестовый контур» — демо-токен и станция.')
                    ->schema([
                        TextInput::make('yandex_delivery_token')
                            ->label('Yandex API key (OAuth)')
                            ->password()->revealable()->maxLength(512)
                            ->helperText('dostavka.yandex.ru → Интеграции')
                            ->columnSpanFull(),
                        TextInput::make('yandex_delivery_station_id')
                            ->label('ID станции отгрузки')
                            ->helperText('platform_station_id из кабинета')
                            ->maxLength(64)->columnSpanFull(),
                        TextInput::make('yandex_delivery_default_city')->label('Город по умолчанию')->placeholder('Москва')->maxLength(120),
                        Select::make('yandex_delivery_taxi_class')
                            ->label('Тариф Express')
                            ->options(['courier' => 'Курьер', 'express' => 'Экспресс', 'cargo' => 'Грузовой'])
                            ->default('express'),
                        TextInput::make('yandex_delivery_source_address')
                            ->label('Адрес склада (Express)')
                            ->maxLength(500)->columnSpanFull(),
                        TextInput::make('yandex_delivery_source_lon')->label('Долгота')->numeric()->step(0.000001),
                        TextInput::make('yandex_delivery_source_lat')->label('Широта')->numeric()->step(0.000001),
                        Toggle::make('yandex_delivery_test_mode')
                            ->label('Тестовый контур')
                            ->helperText('Вкл.: tst + демо-токен и станция. Выкл.: ваш OAuth и ID станции.')
                            ->default(false)->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Подсказки адресов (DaData)')
                    ->description('Автодополнение адреса на checkout. Ключ из dadata.ru → API.')
                    ->schema([
                        TextInput::make('dadata_api_key')
                            ->label('API-ключ')
                            ->password()->revealable()->maxLength(128)->columnSpanFull(),
                        TextInput::make('dadata_secret_key')
                            ->label('Секретный ключ')
                            ->password()->revealable()->maxLength(128)
                            ->helperText('Для серверных методов; на витрине используется API-ключ.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Свой домен')
                    ->schema([
                        TextInput::make('custom_domain')
                            ->label('Домен')
                            ->placeholder('shop.example.com')
                            ->maxLength(253)
                            ->helperText(fn () => $this->customDomainStatus())
                            ->dehydrateStateUsing(fn ($state) => strtolower(trim(preg_replace('#^https?://#i', '', (string) $state), '/'))),
                    ]),
                Section::make('Обмен с 1С')
                    ->schema([
                        Placeholder::make('exchange_url')->label('URL обмена')->content(fn () => $this->exchangeUrl()),
                    ])
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    protected function tenant(): Tenant
    {
        $user = auth()->user();
        if ($user !== null && $user->tenant_id) {
            $tenant = $user->relationLoaded('tenant') ? $user->tenant : $user->tenant()->first();
            if ($tenant === null) {
                $tenant = Tenant::query()->find($user->tenant_id);
            }
            if ($tenant !== null) {
                return $tenant->loadMissing('theme');
            }
        }
        $ctx = app(TenantContext::class)->current();
        if ($ctx !== null) {
            return $ctx->loadMissing('theme');
        }
        if ($user !== null && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            $sessionId = session('filament_shop_tenant_id');
            $tenant = $sessionId ? Tenant::query()->find($sessionId) : Tenant::query()->orderBy('id')->first();
            if ($tenant !== null) {
                session(['filament_shop_tenant_id' => $tenant->id]);
                app(TenantContext::class)->set($tenant);

                return $tenant->loadMissing('theme');
            }
        }
        abort(403, 'Магазин не определён. Войдите учётной записью владельца магазина.');
    }

    protected function storefrontUrlPreview(): string
    {
        $slug = $this->data['slug'] ?? $this->tenant()->slug;

        return rtrim((string) config('app.url'), '/').'/'.$slug;
    }

    protected function customDomainStatus(): string
    {
        $domain = $this->tenant()->domains()->where('is_primary', true)->value('domain');
        if ($domain) {
            return 'Активен: https://'.$domain;
        }

        return 'Не подключён — '.$this->storefrontUrlPreview();
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
        $slug = strtolower(trim((string) ($data['slug'] ?? '')));

        if (in_array($slug, $reserved, true)) {
            Notification::make()->title('Slug «'.$slug.'» зарезервирован')->danger()->send();

            return;
        }
        if (Tenant::query()->where('slug', $slug)->where('id', '!=', $tenant->id)->exists()) {
            Notification::make()->title('Адрес «'.$slug.'» уже занят')->danger()->send();

            return;
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        foreach (['phone', 'email', 'address', 'hours', 'about'] as $key) {
            $val = trim((string) ($data[$key] ?? ''));
            $settings[$key] = $val !== '' ? $val : null;
        }
        $min = $data['min_order_sum'] ?? null;
        $settings['min_order_sum'] = ($min !== null && $min !== '') ? (float) $min : null;
        $settings['enable_articles'] = (bool) ($data['enable_articles'] ?? false);
        $settings['enable_news'] = (bool) ($data['enable_news'] ?? false);
        $settings['enable_reviews'] = (bool) ($data['enable_reviews'] ?? false);
        $settings['yookassa_shop_id'] = trim((string) ($data['yookassa_shop_id'] ?? '')) ?: null;
        $settings['yookassa_secret_key'] = trim((string) ($data['yookassa_secret_key'] ?? '')) ?: null;
        $settings['yandex_delivery_token'] = trim((string) ($data['yandex_delivery_token'] ?? '')) ?: null;
        $settings['yandex_delivery_station_id'] = trim((string) ($data['yandex_delivery_station_id'] ?? '')) ?: null;
        $settings['yandex_delivery_default_city'] = trim((string) ($data['yandex_delivery_default_city'] ?? '')) ?: null;
        $settings['yandex_delivery_source_address'] = trim((string) ($data['yandex_delivery_source_address'] ?? '')) ?: null;
        $lon = $data['yandex_delivery_source_lon'] ?? null;
        $lat = $data['yandex_delivery_source_lat'] ?? null;
        $settings['yandex_delivery_source_lon'] = ($lon !== null && $lon !== '') ? (float) $lon : null;
        $settings['yandex_delivery_source_lat'] = ($lat !== null && $lat !== '') ? (float) $lat : null;
        $settings['yandex_delivery_taxi_class'] = in_array(($data['yandex_delivery_taxi_class'] ?? ''), ['courier', 'express', 'cargo'], true)
            ? $data['yandex_delivery_taxi_class'] : 'express';
        $settings['yandex_delivery_test_mode'] = (bool) ($data['yandex_delivery_test_mode'] ?? false);
        $settings['dadata_api_key'] = trim((string) ($data['dadata_api_key'] ?? '')) ?: null;
        $settings['dadata_secret_key'] = trim((string) ($data['dadata_secret_key'] ?? '')) ?: null;

        $logo = $data['logo_path'] ?? null;
        if (is_array($logo)) {
            $logo = $logo[0] ?? null;
        }

        $tenant->forceFill([
            'name' => $data['name'],
            'slug' => $slug,
            'subdomain' => $slug,
            'theme_id' => $data['theme_id'] ?: null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'logo_path' => $logo ?: $tenant->logo_path,
            'settings' => $settings,
        ])->save();

        $this->syncCustomDomain($tenant, (string) ($data['custom_domain'] ?? ''));
        app(TenantContext::class)->set($tenant->fresh());
        $this->fillFromTenant();

        Notification::make()->title('Настройки сохранены')->success()->send();
    }

    protected function syncCustomDomain(Tenant $tenant, string $domain): void
    {
        $domain = strtolower(trim(preg_replace('#^https?://#i', '', $domain), '/'));
        $domain = preg_replace('#/.*$#', '', $domain) ?? '';
        if ($domain === '') {
            $tenant->domains()->delete();

            return;
        }
        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)) {
            Notification::make()->title('Некорректный домен')->warning()->send();

            return;
        }
        $taken = \App\Models\TenantDomain::query()
            ->where('domain', $domain)->where('tenant_id', '!=', $tenant->id)->exists();
        if ($taken) {
            Notification::make()->title('Домен уже занят')->danger()->send();

            return;
        }
        $tenant->domains()->delete();
        $tenant->domains()->create(['domain' => $domain, 'is_primary' => true]);
    }

    public function getFormActions(): array
    {
        return [
            Action::make('save')->label('Сохранить')->submit('save'),
            Action::make('openStorefront')
                ->label('Открыть витрину')
                ->url(fn (): string => $this->tenant()->fresh()->url())
                ->openUrlInNewTab()
                ->color('gray'),
        ];
    }
}
