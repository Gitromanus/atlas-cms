<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\OrderStatus;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Самостоятельная регистрация магазина предпринимателем.
 *
 * Публичная форма на главном домене платформы: создаёт магазин (тенанта),
 * системные статусы заказов и аккаунт владельца для панели /shop.
 */
class ShopRegistrationController extends Controller
{
    public function create(): View
    {
        return view('platform.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $baseSlug = Str::slug($validated['shop_name']) ?: 'shop';
            $slug = $baseSlug;
            $suffix = 1;

            while (Tenant::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.(++$suffix);
            }

            $theme = Theme::query()->where('slug', 'default')->first()
                ?? Theme::query()->create([
                    'slug' => 'default',
                    'name' => 'Default',
                ]);

            $tenant = Tenant::query()->create([
                'name' => $validated['shop_name'],
                'slug' => $slug,
                'subdomain' => $slug,
                'theme_id' => $theme->id,
                'is_active' => true,
                'settings' => [
                    'design' => [
                        'primary_color' => '#4f46e5',
                        'accent_color' => '#0f172a',
                        'radius' => '0.75rem',
                        'font_family' => "'Inter', 'Segoe UI', system-ui, sans-serif",
                    ],
                ],
            ]);

            foreach ([
                ['code' => 'new', 'name' => 'Новый'],
                ['code' => 'processing', 'name' => 'В обработке'],
                ['code' => 'shipped', 'name' => 'Отправлен'],
                ['code' => 'completed', 'name' => 'Выполнен'],
                ['code' => 'cancelled', 'name' => 'Отменён'],
            ] as $status) {
                OrderStatus::query()->create([
                    'tenant_id' => $tenant->id,
                    'code' => $status['code'],
                    'name' => $status['name'],
                    'is_system' => true,
                ]);
            }

            return User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_super_admin' => false,
            ]);
        });

        // Если пользователь уже авторизован — не переключаем его сессию
        // на нового владельца магазина и отправляем в подходящую панель.
        if (! Auth::check()) {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->to('/shop')
                ->with('status', 'Магазин «'.$validated['shop_name'].'» создан. Добро пожаловать в панель управления!');
        }

        $current = Auth::user();

        return redirect()->to($current->isSuperAdmin() ? '/platform' : '/shop')
            ->with('status', 'Магазин «'.$validated['shop_name'].'» создан.');
    }
}