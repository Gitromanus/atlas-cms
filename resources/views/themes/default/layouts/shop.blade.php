<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $currentTenant?->name ?? 'AtlasCMS')</title>
    <link rel="stylesheet" href="{{ asset('css/shop.css') }}">
    @if (\Illuminate\Support\Facades\Route::has('theme.css'))
        <link rel="stylesheet" href="{{ route('theme.css') }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: 'var(--color-primary)',
                        accent: 'var(--color-accent)',
                    },
                    fontFamily: {
                        sans: ['var(--font-family)'],
                    },
                    borderRadius: {
                        theme: 'var(--radius)',
                    },
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
    @stack('head')
</head>
<body class="flex min-h-screen flex-col bg-slate-50 text-slate-800 antialiased">
@php
    $tenant = $currentTenant ?? app(\App\Services\Tenant\TenantContext::class)->current();
    $settings = is_array($tenant?->settings) ? $tenant->settings : [];
    $shopName = $tenant?->name ?? 'Магазин';
    $phone = $settings['phone'] ?? null;
    $email = $settings['email'] ?? null;
    $address = $settings['address'] ?? null;
    $hours = $settings['hours'] ?? null;
    $about = $settings['about'] ?? null;
    $phoneHref = $phone ? preg_replace('/[^\d+]/', '', $phone) : null;
@endphp

{{-- atlas-layout-v3: no top bar --}}
<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2">
            @if ($tenant?->logo_path)
                <img src="{{ asset('storage/logos/'.$tenant->logo_path) }}" alt="{{ $shopName }}" class="h-8 w-8 rounded-theme object-cover">
            @else
                <span class="flex h-8 w-8 items-center justify-center rounded-theme bg-primary font-black text-white">A</span>
            @endif
            <span class="text-lg font-bold">{{ $shopName }}</span>
        </a>

        <nav class="hidden items-center gap-5 md:flex">
            <a href="{{ route('home') }}" class="font-medium transition hover:text-primary">Главная</a>
            <a href="{{ route('catalog.index') }}" class="font-medium transition hover:text-primary">Каталог</a>
            @php($categoryMenu = \App\Models\Category::menuTree())
            @foreach ($categoryMenu as $category)
                @include('shop.partials.category-menu', ['category' => $category, 'menuLevel' => 0])
            @endforeach
        </nav>

        <form action="{{ route('catalog.index') }}" method="GET" class="hidden max-w-xs flex-1 lg:block">
            <div class="relative">
                <input type="search" name="q" value="{{ request('q') }}"
                       placeholder="Поиск товаров…"
                       class="w-full rounded-full border border-slate-200 bg-slate-50 py-1.5 pl-3 pr-9 text-sm focus:border-primary focus:bg-white focus:outline-none">
                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary" aria-label="Найти">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                </button>
            </div>
        </form>

        <div class="flex items-center gap-3">
            @if (filled($phone))
                <a href="tel:{{ $phoneHref }}" class="header-phone hidden text-sm font-medium text-slate-600 transition hover:text-primary sm:inline" title="Позвонить">{{ $phone }}</a>
            @endif
            <a href="{{ route('catalog.index') }}" class="p-1.5 text-slate-600 transition hover:text-primary lg:hidden" title="Поиск / каталог">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
            </a>
            <a href="{{ route('cart.index') }}" class="relative p-1.5 text-slate-600 transition hover:text-primary" title="Корзина">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                </svg>
                @if (app()->bound(\App\Services\Cart\CartService::class))
                    @php($cartCount = app(\App\Services\Cart\CartService::class)->count())
                    @if ($cartCount > 0)
                        <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs font-bold text-white">{{ $cartCount }}</span>
                    @endif
                @endif
            </a>

            @auth('customers')
                <a href="{{ route('account.orders') }}" class="hidden text-sm font-medium transition hover:text-primary sm:block">Мои заказы</a>
                <form method="POST" action="{{ route('customer.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-theme border border-slate-300 px-3 py-1.5 text-sm font-medium transition hover:bg-slate-100">Выйти</button>
                </form>
            @else
                <a href="{{ route('customer.login') }}" class="rounded-theme border border-slate-300 px-3 py-1.5 text-sm font-medium transition hover:bg-slate-100">Войти</a>
            @endauth
        </div>
    </div>
</header>

<main class="mx-auto max-w-6xl flex-1 px-4 py-8">
    @if (session('status'))
        <div class="mb-6 rounded-theme border border-green-200 bg-green-50 px-4 py-3 text-green-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-theme border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

<footer class="mt-auto border-t border-slate-200 bg-white">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <p class="text-base font-bold text-slate-900">{{ $shopName }}</p>
            @if (filled($about))
                <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $about }}</p>
            @endif
        </div>
        <div class="text-sm text-slate-600">
            <p class="font-semibold text-slate-900">Контакты</p>
            <ul class="mt-2 space-y-1">
                @if (filled($phone))
                    <li><a href="tel:{{ $phoneHref }}" class="hover:text-primary">{{ $phone }}</a></li>
                @endif
                @if (filled($email))
                    <li><a href="mailto:{{ $email }}" class="hover:text-primary">{{ $email }}</a></li>
                @endif
                @if (filled($address))
                    <li>{{ $address }}</li>
                @endif
                @if (filled($hours))
                    <li class="text-slate-500">{{ $hours }}</li>
                @endif
                @if (! filled($phone) && ! filled($email) && ! filled($address))
                    <li class="text-slate-400">Укажите контакты в админке → Настройки магазина</li>
                @endif
            </ul>
        </div>
        <div class="text-sm text-slate-600">
            <p class="font-semibold text-slate-900">Покупателям</p>
            <ul class="mt-2 space-y-1">
                <li><a href="{{ route('catalog.index') }}" class="hover:text-primary">Каталог</a></li>
                <li><a href="{{ route('cart.index') }}" class="hover:text-primary">Корзина</a></li>
                <li><a href="{{ route('account.orders') }}" class="hover:text-primary">Мои заказы</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-slate-100">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-4 text-xs text-slate-400 md:flex-row">
            <span>© {{ date('Y') }} {{ $shopName }}</span>
            <span>Работает на AtlasCMS</span>
        </div>
    </div>
</footer>

@stack('scripts')
</body>
</html>
