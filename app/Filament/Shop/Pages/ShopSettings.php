<?php

namespace App\Filament\Shop\Pages;

use App\Models\Tenant;
use App\Models\Theme;
use App\Services\Tenant\TenantContext;
use Filament\Actions\Action;
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
        $tenant = $this->tenant();
        $settings = $tenant->settings ?? [];

        $this->form->fill([
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'theme_id' => $tenant->theme_id,
            'is_active' => $tenant->is_active,
            'phone' => $settings['phone'] ?? '',
            'email' => $settings['email'] ?? '',
            'address' => $settings['address'] ?? '',
            'hours' => $settings['hours'] ?? '',
            'about' => $settings['about'] ?? '',
            'min_order_sum' => $settings['min_order_sum'] ?? null,
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
                Section::make('Контакты для покупателей')
                    ->description('Отображаются в шапке и подвале витрины.')
                    ->schema([
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->placeholder('+7 (999) 123-45-67'),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->placeholder('shop@example.com'),
                        TextInput::make('address')
                            ->label('Адрес')
                            ->columnSpanFull(),
                        TextInput::make('hours')
                            ->label('Часы работы')
                            ->placeholder('Пн–Пт 10:00–20:00')
                            ->columnSpanFull(),
                        Textarea::make('about')
                            ->label('О магазине (коротко)')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Показывается на главной и в подвале.'),
                        TextInput::make('min_order_sum')
                            ->label('Мин. сумма заказа, ₽')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('0 или пусто — без ограничения'),
                    ])
                    ->columns(2),
                Section::make('Свой домен')
                    ->description('Подключение собственного домена появится в следующем обновлении. Сейчас витрина открывается по адресу платформы с slug магазина.')
                    ->schema([
                        Placeholder::make('custom_domain_hint')
                            ->label('Статус')
                            ->content(fn () => $this->customDomainStatus()),
                    ]),
                Section::make('Обмен с 1С')
                    ->schema([
                        Placeholder::make('exchange_url')
                            ->label('URL обмена')
                            ->content(fn () => $this->exchangeUrl()),
                    ])
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    protected function tenant(): Tenant
    {
        $user = auth()->user();
        if ($user?->tenant) {
            return $user->tenant->loadMissing('theme');
        }

        $ctx = app(TenantContext::class)->current();
        if ($ctx) {
            return $ctx->loadMissing('theme');
        }

        abort(403, 'Магазин не определён');
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

        $settings = $tenant->settings ?? [];
        $settings['phone'] = trim((string) ($data['phone'] ?? '')) ?: null;
        $settings['email'] = trim((string) ($data['email'] ?? '')) ?: null;
        $settings['address'] = trim((string) ($data['address'] ?? '')) ?: null;
        $settings['hours'] = trim((string) ($data['hours'] ?? '')) ?: null;
        $settings['about'] = trim((string) ($data['about'] ?? '')) ?: null;
        $min = $data['min_order_sum'] ?? null;
        $settings['min_order_sum'] = $min !== null && $min !== '' ? (float) $min : null;

        $tenant->update([
            'name' => $data['name'],
            'slug' => $slug,
            'subdomain' => $slug,
            'theme_id' => $data['theme_id'] ?: null,
            'is_active' => $data['is_active'] ?? false,
            'settings' => $settings,
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
            Action::make('openStorefront')
                ->label('Открыть витрину')
                ->url(fn (): string => $this->tenant()->url())
                ->openUrlInNewTab()
                ->color('gray'),
        ];
    }
}
