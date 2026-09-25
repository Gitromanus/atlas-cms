<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(protected CartService $cart) {}

    public function showLogin(): View
    {
        return view('shop.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $credentials = $field === 'email'
            ? Customer::normalizeEmail($validated['login'])
            : Customer::normalizePhone($validated['login']);

        if ($credentials === null || ! Auth::guard('customers')->attempt([$field => $credentials, 'password' => $validated['password']], $request->boolean('remember'))) {
            return back()->withErrors(['login' => 'Неверный логин или пароль'])->onlyInput('login');
        }

        /** @var Customer $customer */
        $customer = Auth::guard('customers')->user();
        $this->cart->mergeGuestCart($customer);

        $request->session()->regenerate();

        return redirect()->intended(route('account.orders'));
    }

    public function showRegister(): View
    {
        return view('shop.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $email = Customer::normalizeEmail($validated['email'] ?? null);
        $phone = Customer::normalizePhone($validated['phone']);

        // Профиль мог появиться после заказа гостем — не даём 500 по unique-индексу,
        // а предлагаем войти или восстановить пароль
        $exists = Customer::query()
            ->when($email !== null, fn ($query) => $query->orWhere('email', $email))
            ->when($phone !== null, fn ($query) => $query->orWhere('phone', $phone))
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['email' => 'Покупатель с таким email или телефоном уже зарегистрирован. Войдите в личный кабинет или восстановите пароль.'])
                ->onlyInput('name', 'email', 'phone');
        }

        $customer = Customer::create([
            'name' => trim((string) $validated['name']) ?: 'Покупатель',
            'email' => $email,
            'phone' => $phone,
            'password' => $validated['password'],
        ]);

        Auth::guard('customers')->login($customer);
        $this->cart->mergeGuestCart($customer);

        $request->session()->regenerate();

        return redirect()->route('account.orders');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customers')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}