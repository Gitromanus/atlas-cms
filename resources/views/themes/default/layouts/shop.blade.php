<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $currentTenant?->name ?? 'AtlasCMS')</title>
    <link rel="stylesheet" href="{{ asset('css/shop.css') }}">
    <link rel="stylesheet" href="{{ route('theme.css') }}">
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
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">

<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <a href="{{ route('home') }}" class="flex items-center gap-2">
            @if ($currentTenant?->logo_path)
                <img src="{{ asset('storage/logos/'.$currentTenant->logo_path) }}" alt="{{ $currentTenant->name }}" class="h-8 w-8 rounded-theme object-cover">
            @else
                <span class="flex h-8 w-8 items-center justify-center rounded-theme bg-primary font-black text-white">A</span>
            @endif
            <span class="text-lg font-bold">{{ $currentTenant?->name ?? 'Магазин' }}</span>
        </a>

        <nav class="hidden items-center gap-6 md:flex">
            <a href="{{ route('home') }}" class="hover:text-primary">Главная</a>

            @php($categoryMenu = \App\Models\Category::menuTree())
            <div class="relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
                <a href="{{ route('catalog.index') }}" class="inline-flex items-center gap-1 hover:text-primary">
                    Каталог
                    <svg class="h-3 w-3 transition" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                </a>
                <div x-show="open" x-cloak x-transition.opacity
                     class="absolute left-0 top-full z-50 mt-2 min-w-60 rounded-theme border border-slate-200 bg-white py-1 shadow-lg">
                    <a href="{{ route('catalog.index') }}" class="block px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Все товары</a>
                    @if (! empty($categoryMenu))
                        @include('shop.partials.category-menu', ['categories' => collect($categoryMenu)])
                    @endif
                </div>
            </div>
            <a href="{{ route('cart.index') }}" class="relative hover:text-primary">
                Корзина
                @if (\App\Services\Cart\CartService::class && app()->bound(\App\Services\Cart\CartService::class))
                    @php($cartCount = app(\App\Services\Cart\CartService::class)->count())
                    @if ($cartCount > 0)
                        <span class="absolute -right-3 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs font-bold text-white">{{ $cartCount }}</span>
                    @endif
                @endif
            </a>
        </nav>

        <div class="flex items-center gap-3">
            @auth('customers')
                <a href="{{ route('account.orders') }}" class="hover:text-primary">Мои заказы</a>
                <form method="POST" action="{{ route('customer.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-theme border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100">Выйти</button>
                </form>
            @else
                <a href="{{ route('customer.login') }}" class="rounded-theme border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100">Войти</a>
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

<footer class="border-t border-slate-200 bg-white">
    <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm text-slate-500 md:flex-row">
        <span>© {{ date('Y') }} {{ $currentTenant?->name ?? 'Магазин' }}</span>
        <span>Работает на AtlasCMS</span>
    </div>
</footer>

@stack('scripts')
</body>
</html>