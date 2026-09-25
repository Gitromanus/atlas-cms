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
                    ->description('Телефон и часы — в верхней полосе. Остальное — в подвале витрины.')
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
                            ->columnSpanFull(),
                        TextInput::make('min_order_sum')
                            ->label('Мин. сумма заказа, ₽')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Пусто — без ограничения'),
                    ])
                    ->columns(2),
                Section::make('Свой домен')
                    ->description('Сейчас витрина: path платформы /{slug}. Свой домен — в следующем обновлении.')
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
            : 'Не подключён — витрина на path платформы.';
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

        $tenant->forceFill([
            'name' => $data['name'],
            'slug' => $slug,
            'subdomain' => $slug,
            'theme_id' => $data['theme_id'] ?: null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'settings' => $settings,
        ])->save();

        app(TenantContext::class)->set($tenant->fresh());

        $this->fillFromTenant();

        Notification::make()
            ->title('Настройки сохранены')
            ->body('Контакты появятся на витрине после обновления страницы.')
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
                ->url(fn (): string => $this->tenant()->fresh()->url())
                ->openUrlInNewTab()
                ->color('gray'),
        ];
    }
}
