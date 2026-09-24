<?php

namespace App\Filament\Admin\Pages;

use App\Models\Tenant;
use App\Services\Tenant\TenantContext;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;

class ShopSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Настройки магазина';

    protected static ?string $title = 'Настройки магазина';

    protected static string $view = 'filament.admin.pages.shop-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = $this->tenant();
        $settings = $tenant->settings ?? [];

        $this->form->fill([
            'name' => $tenant->name,
            'subdomain' => $tenant->subdomain,
            'theme_id' => $tenant->theme_id,
            'is_active' => $tenant->is_active,
            'design_primary_color' => data_get($settings, 'design.primary_color', '#4f46e5'),
            'design_accent_color' => data_get($settings, 'design.accent_color', '#0f172a'),
            'design_radius' => data_get($settings, 'design.radius', '0.75rem'),
            'exchange_login' => data_get($settings, 'exchange.login'),
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
                            ->relationship('theme', 'name')
                            ->preload(),
                        Toggle::make('is_active')->label('Магазин активен'),
                    ])
                    ->columns(2),
                Section::make('Дизайн витрины')
                    ->description('Изменения сразу применяются на витрине через CSS-переменные')
                    ->schema([
                        ColorPicker::make('design_primary_color')->label('Основной цвет'),
                        ColorPicker::make('design_accent_color')->label('Дополнительный цвет'),
                        TextInput::make('design_radius')->label('Радиус скруглений (CSS)')
                            ->placeholder('0.75rem'),
                    ])
                    ->columns(3),
                Section::make('Обмен с 1С (CommerceML)')
                    ->description('Укажите адрес обмена в 1С: https://{домен}/1c/exchange')
                    ->schema([
                        TextInput::make('exchange_login')->label('Логин обмена'),
                        TextInput::make('exchange_password')->label('Новый пароль обмена')
                            ->password()
                            ->helperText('Оставьте пустым, чтобы не менять пароль'),
                    ])
                    ->columns(2),
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

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = $this->tenant();

        $settings = $tenant->settings ?? [];

        $data['settings'] = [
            'design' => [
                'primary_color' => $data['design_primary_color'] ?? null,
                'accent_color' => $data['design_accent_color'] ?? null,
                'radius' => $data['design_radius'] ?? null,
            ],
            'exchange' => [
                'login' => $data['exchange_login'] ?? null,
                'password' => $settings['exchange']['password'] ?? null,
            ],
        ];

        // Смена пароля обмена
        if (! empty($data['exchange_password'])) {
            $data['settings']['exchange']['password'] = Hash::make($data['exchange_password']);
        }

        $tenant->update([
            'name' => $data['name'],
            'subdomain' => $data['subdomain'] ?: null,
            'theme_id' => $data['theme_id'] ?: null,
            'is_active' => $data['is_active'] ?? false,
            'settings' => $data['settings'],
        ]);

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить')
                ->submit('save'),
        ];
    }
}