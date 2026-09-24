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

        if (! Auth::guard('customers')->attempt([$field => $validated['login'], 'password' => $validated['password']], $request->boolean('remember'))) {
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

        $customer = Customer::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'],
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