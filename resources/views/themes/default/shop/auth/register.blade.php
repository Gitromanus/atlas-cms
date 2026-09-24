@extends('layouts.shop')

@section('title', 'Регистрация')

@section('content')
    <div class="mx-auto max-w-md rounded-theme bg-white p-8 shadow-sm">
        <h1 class="mb-6 text-2xl font-bold">Регистрация</h1>

        <form method="POST" action="{{ route('customer.register') }}" class="space-y-4">
            @csrf

            <div>
                <label class="mb-1 block text-sm font-medium">Имя *</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Телефон *</label>
                <input type="tel" name="phone" value="{{ old('phone') }}" required
                       class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Email (необязательно)</label>
                <input type="email" name="email" value="{{ old('email') }}"
                       class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Пароль *</label>
                <input type="password" name="password" required minlength="6"
                       class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Повторите пароль *</label>
                <input type="password" name="password_confirmation" required minlength="6"
                       class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">
            </div>

            <button type="submit" class="w-full rounded-theme bg-primary px-6 py-3 font-semibold text-white hover:opacity-90">
                Создать аккаунт
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Уже есть аккаунт?
            <a href="{{ route('customer.login') }}" class="font-medium text-primary hover:underline">Войти</a>
        </p>
    </div>
@endsection