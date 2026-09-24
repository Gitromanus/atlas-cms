@extends('layouts.shop')

@section('title', 'Вход')

@section('content')
    <div class="mx-auto max-w-md rounded-theme bg-white p-8 shadow-sm">
        <h1 class="mb-6 text-2xl font-bold">Вход в личный кабинет</h1>

        <form method="POST" action="{{ route('customer.login') }}" class="space-y-4">
            @csrf

            <div>
                <label class="mb-1 block text-sm font-medium">Телефон или Email</label>
                <input type="text" name="login" value="{{ old('login') }}" required autofocus
                       class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Пароль</label>
                <input type="password" name="password" required
                       class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded">
                Запомнить меня
            </label>

            <button type="submit" class="w-full rounded-theme bg-primary px-6 py-3 font-semibold text-white hover:opacity-90">
                Войти
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Нет аккаунта?
            <a href="{{ route('customer.register') }}" class="font-medium text-primary hover:underline">Зарегистрироваться</a>
        </p>
    </div>
@endsection